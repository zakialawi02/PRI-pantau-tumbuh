"""Utilities to clip Sentinel-2 multispectral data to an AOI.

This script is designed to be executed from a queued Laravel job. Runtime
configuration is provided entirely through environment variables so the same
script can support both manual and automatic scene selection.

Required environment variables:
    COPERNICUS_CLIENT_ID
    COPERNICUS_CLIENT_SECRET
    CLIP_GEOMETRY                    # GeoJSON geometry (Polygon/MultiPolygon)
    CLIP_DATE_FROM                   # ISO date (YYYY-MM-DD)
    CLIP_DATE_TO                     # ISO date

Optional variables:
    CLIP_MAX_CLOUD                   # percentage (default 60)
    CLIP_LIMIT                       # search limit (default 50)
    CLIP_RESOLUTION                  # metres per pixel (default 10)
    CLIP_NODATA                      # nodata value (default -9999)
    CLIP_TILE_DIR                    # directory for intermediate tiles
    CLIP_MERGED_TIF                  # path for merged mosaic
    CLIP_MASKED_TIF                  # path for final clipped raster
    CLIP_DATA_COLLECTION             # SENTINEL2_L2A / SENTINEL2_L1C (default L2A)
    CLIP_SCENE_ID                    # optional explicit item id/product identifier
    CLIP_ACQUISITION                 # ISO datetime to narrow time window
"""

import json
import math
import os
from datetime import datetime, timedelta
from typing import Any, Dict, Iterable, Optional

import numpy as np
import rasterio
from dateutil import parser as dateparser
from rasterio import mask
from rasterio.merge import merge

from sentinelhub import (
    BBox,
    CRS,
    DataCollection,
    Geometry,
    MimeType,
    SentinelHubCatalog,
    SentinelHubRequest,
    SHConfig,
    bbox_to_dimensions,
)
from sentinelhub.areas import BBoxSplitter


def read_env(name: str, default: Optional[str] = None) -> Optional[str]:
    value = os.environ.get(name)
    if value is None or value == "":
        return default
    return value


def read_float(name: str, default: float) -> float:
    raw = read_env(name)
    if raw is None:
        return default
    try:
        return float(raw)
    except (TypeError, ValueError):
        return default


def read_int(name: str, default: int) -> int:
    raw = read_env(name)
    if raw is None:
        return default
    try:
        return int(float(raw))
    except (TypeError, ValueError):
        return default


def resolve_data_collection(name: Optional[str]) -> DataCollection:
    mapping = {
        "SENTINEL2_L2A": DataCollection.SENTINEL2_L2A,
        "SENTINEL2_L1C": DataCollection.SENTINEL2_L1C,
    }
    if not name:
        return DataCollection.SENTINEL2_L2A
    key = name.strip().upper()
    return mapping.get(key, DataCollection.SENTINEL2_L2A)


def ensure_geometry(raw: Any) -> Geometry:
    if isinstance(raw, str):
        raw = raw.strip()
        if not raw:
            raise ValueError("CLIP_GEOMETRY is empty")
        geom_dict = json.loads(raw)
    else:
        geom_dict = raw

    if isinstance(geom_dict, dict) and geom_dict.get("type"):
        return Geometry(geom_dict, crs=CRS.WGS84)

    if isinstance(geom_dict, dict) and "features" in geom_dict:
        features = geom_dict.get("features") or []
        if not features:
            raise ValueError("CLIP_GEOMETRY features array is empty")
        geom = features[0].get("geometry")
        if not geom:
            raise ValueError("CLIP_GEOMETRY feature does not contain geometry")
        return Geometry(geom, crs=CRS.WGS84)

    raise ValueError("Unsupported CLIP_GEOMETRY format")


def get_cloud(item: Dict[str, Any]) -> Optional[float]:
    props = item.get("properties", {})
    for key in ("eo:cloud_cover", "cloudCover", "cloudcoverpercentage", "cloudCoverageAssessment"):
        value = props.get(key)
        if value is not None:
            try:
                return float(value)
            except (TypeError, ValueError):
                continue
    return None


def sort_scenes(items: Iterable[Dict[str, Any]]) -> Iterable[Dict[str, Any]]:
    def score(item: Dict[str, Any]) -> tuple:
        props = item.get("properties", {})
        cloud = get_cloud(item)
        dt = props.get("datetime") or props.get("completionDate") or props.get("startDate")
        parsed = None
        if dt:
            try:
                parsed = dateparser.isoparse(dt)
            except (TypeError, ValueError):
                parsed = None
        cloud_score = cloud if cloud is not None else 100.0
        time_score = parsed.timestamp() if parsed else 0
        return (cloud_score, -time_score)

    return sorted(items, key=score)


def resolve_selected_scene(items: Iterable[Dict[str, Any]], target_id: Optional[str]) -> Dict[str, Any]:
    items = list(items)
    if not items:
        raise RuntimeError("No Sentinel-2 scenes matched the provided filters")

    if target_id:
        target_id = target_id.strip().lower()
        for item in items:
            if item.get("id", "").lower() == target_id:
                return item
            props = item.get("properties", {})
            identifiers = [
                props.get("productIdentifier"),
                props.get("title"),
                props.get("name"),
            ]
            if any(str(identifier).lower() == target_id for identifier in identifiers if identifier):
                return item

    return sort_scenes(items)[0]


def build_evalscript() -> str:
    return """
//VERSION=3
function setup() {
    return {
        input: [{
            bands: ["B01","B02","B03","B04","B05","B06","B07","B08","B8A","B09","B11","B12"],
            units: "DN"
        }],
        output: { bands: 12, sampleType: "INT16" }
    };
}
function evaluatePixel(s) {
    return [s.B01,s.B02,s.B03,s.B04,s.B05,s.B06,s.B07,s.B08,
            s.B8A,s.B09,s.B11,s.B12];
}
"""


def ensure_directory(path: str) -> None:
    directory = os.path.dirname(path) or "."
    os.makedirs(directory, exist_ok=True)


def main() -> None:
    client_id = read_env("COPERNICUS_CLIENT_ID")
    client_secret = read_env("COPERNICUS_CLIENT_SECRET")
    if not client_id or not client_secret:
        raise RuntimeError("Copernicus credentials are not configured")

    geometry = ensure_geometry(read_env("CLIP_GEOMETRY"))
    date_from = read_env("CLIP_DATE_FROM")
    date_to = read_env("CLIP_DATE_TO")
    if not date_from or not date_to:
        raise RuntimeError("CLIP_DATE_FROM and CLIP_DATE_TO must be provided")

    max_cloud = read_float("CLIP_MAX_CLOUD", 60.0)
    limit = read_int("CLIP_LIMIT", 50)
    resolution = read_int("CLIP_RESOLUTION", 10)
    nodata = read_float("CLIP_NODATA", -9999)

    tiles_dir = read_env("CLIP_TILE_DIR", os.path.join(os.getcwd(), "tiles"))
    merged_tif = read_env("CLIP_MERGED_TIF", os.path.join(os.getcwd(), "merged_fixed.tif"))
    masked_tif = read_env("CLIP_MASKED_TIF", os.path.join(os.getcwd(), "merged_masked.tif"))
    data_collection = resolve_data_collection(read_env("CLIP_DATA_COLLECTION"))

    target_scene = read_env("CLIP_SCENE_ID")
    acquisition_override = read_env("CLIP_ACQUISITION")

    config = SHConfig()
    config.sh_client_id = client_id
    config.sh_client_secret = client_secret
    config.sh_token_url = read_env(
        "COPERNICUS_TOKEN_ENDPOINT",
        "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token",
    )
    config.sh_base_url = read_env("COPERNICUS_BASE_URL", "https://sh.dataspace.copernicus.eu")

    ensure_directory(merged_tif)
    ensure_directory(masked_tif)
    os.makedirs(tiles_dir, exist_ok=True)

    catalogue = SentinelHubCatalog(config=config)
    search_kwargs: Dict[str, Any] = {
        "geometry": geometry,
        "time": (date_from, date_to),
        "limit": limit,
        "data_collection": data_collection,
    }

    items = list(catalogue.search(**search_kwargs))

    if not items:
        raise RuntimeError("Tidak ada scene cocok pada rentang tanggal yang diberikan")

    selected = resolve_selected_scene(items, target_scene)
    props = selected.get("properties", {})
    chosen_id = selected.get("id")
    chosen_time = props.get("datetime") or props.get("completionDate") or props.get("startDate")

    print("Scene terpilih:", chosen_id, "waktu:", chosen_time, "cloud:", get_cloud(selected))

    if acquisition_override:
        try:
            acquisition_dt = dateparser.isoparse(acquisition_override)
        except (TypeError, ValueError) as exc:
            raise RuntimeError("CLIP_ACQUISITION is not a valid ISO datetime") from exc
    elif chosen_time:
        acquisition_dt = dateparser.isoparse(chosen_time)
    else:
        acquisition_dt = datetime.utcnow()

    t_from = (acquisition_dt - timedelta(hours=1)).isoformat()
    t_to = (acquisition_dt + timedelta(hours=1)).isoformat()

    bbox = geometry.bbox
    width, height = bbox_to_dimensions(bbox, resolution=resolution)
    max_px = 2500
    n_cols = math.ceil(width / max_px) if width > max_px else 1
    n_rows = math.ceil(height / max_px) if height > max_px else 1
    print(f"Split grid: {n_cols} × {n_rows}")

    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(n_cols, n_rows))
    tile_bboxes = splitter.get_bbox_list()

    evalscript = build_evalscript()

    tile_paths = []
    for idx, tile_bbox in enumerate(tile_bboxes):
        tile_width, tile_height = bbox_to_dimensions(tile_bbox, resolution=resolution)
        tile_width = min(tile_width, max_px)
        tile_height = min(tile_height, max_px)
        print(f"Tile {idx + 1}/{len(tile_bboxes)}: {tile_width}×{tile_height}")

        request = SentinelHubRequest(
            evalscript=evalscript,
            input_data=[
                {
                    "type": data_collection.api_id,
                    "dataFilter": {
                        "timeRange": {"from": t_from, "to": t_to},
                        "mosaickingOrder": "mostRecent",
                        "maxCloudCoverage": max_cloud,
                    },
                    "processing": {
                        "upsampling": "BILINEAR",
                        "downsampling": "BILINEAR",
                        "harmonizeValues": True,
                    },
                }
            ],
            responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
            bbox=tile_bbox,
            size=(tile_width, tile_height),
            data_folder=tiles_dir,
            config=config,
        )
        _ = request.get_data(save_data=True, show_progress=True)
        for path in request.get_filename_list():
            tile_paths.append(os.path.join(tiles_dir, path))

    if not tile_paths:
        raise RuntimeError("Sentinel Hub tidak mengembalikan tile apapun untuk area yang diminta")

    srcs = [rasterio.open(path) for path in tile_paths]
    try:
        mosaic, out_transform = merge(srcs)
        base_meta = srcs[0].meta.copy()
    finally:
        for src in srcs:
            src.close()

    meta = {
        "driver": "GTiff",
        "height": mosaic.shape[1],
        "width": mosaic.shape[2],
        "count": mosaic.shape[0],
        "dtype": mosaic.dtype,
        "crs": base_meta["crs"],
        "transform": out_transform,
        "compress": "lzw",
        "photometric": "MINISBLACK",
        "nodata": nodata,
    }

    with rasterio.open(merged_tif, "w", **meta) as dst:
        dst.write(mosaic)
    print("Merged TIFF (bbox) disimpan:", merged_tif)

    with rasterio.open(merged_tif) as src:
        out_img, out_transform = mask.mask(src, [geometry.geometry], crop=True, nodata=nodata)
        out_meta = src.meta.copy()
        out_meta.update(
            {
                "driver": "GTiff",
                "height": out_img.shape[1],
                "width": out_img.shape[2],
                "transform": out_transform,
                "compress": "lzw",
                "nodata": nodata,
            }
        )

    with rasterio.open(masked_tif, "w", **out_meta) as dst:
        dst.write(out_img)

    print("Merged TIFF masked (poligon AOI) disimpan:", masked_tif)


if __name__ == "__main__":
    main()
