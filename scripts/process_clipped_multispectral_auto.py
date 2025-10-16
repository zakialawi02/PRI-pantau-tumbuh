"""Utility script to clip Sentinel-2 imagery based on AOI geometry.

This script now reads configuration from environment variables in order to be
driven by the Laravel application.  The most important variables are:

```
COPERNICUS_CLIENT_ID, COPERNICUS_CLIENT_SECRET  -> OAuth credentials
S2_CLIP_GEOJSON                                 -> Geometry (Feature/Geometry)
S2_CLIP_DATE_FROM / S2_CLIP_DATE_TO             -> Date range (ISO8601)
S2_CLIP_SCENE_DATETIME                          -> Optional exact scene time
S2_CLIP_PRODUCT_LEVEL                           -> S2MSI2A or S2MSI1C
S2_CLIP_MAX_CLOUD                               -> Cloud cover percentage
S2_CLIP_LIMIT                                   -> Max catalogue items
S2_CLIP_RESOLUTION                              -> Target resolution (m)
S2_CLIP_OUTPUT                                  -> Output GeoTIFF path
S2_CLIP_TILE_DIR                                -> Directory for tiles
```

The script selects the best matching scene (lowest cloud cover, most recent)
within the given period, downloads all spectral bands, mosaics them, and clips
the mosaic to the supplied AOI polygon before writing the final GeoTIFF.
"""

from __future__ import annotations

import json
import os
import shutil
from datetime import timedelta
from pathlib import Path
from typing import Iterable, List, Optional

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
    SHConfig,
    SentinelHubCatalog,
    SentinelHubRequest,
    bbox_to_dimensions,
)
from sentinelhub.areas import BBoxSplitter


def read_env(name: str, default: Optional[str] = None) -> Optional[str]:
    value = os.environ.get(name)
    if value is None:
        return default
    value = value.strip()
    return value if value else default


def ensure_directory(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)


def parse_geometry(value: str) -> Geometry:
    try:
        payload = json.loads(value)
    except json.JSONDecodeError as error:  # pragma: no cover - defensive
        raise SystemExit(f"Unable to parse S2_CLIP_GEOJSON: {error}") from error

    if not isinstance(payload, dict):
        raise SystemExit("S2_CLIP_GEOJSON must be a GeoJSON Feature or geometry dictionary")

    if payload.get("type") == "FeatureCollection":
        features = payload.get("features", [])
        if not features:
            raise SystemExit("FeatureCollection must contain at least one feature")
        geometry_dict = features[0].get("geometry")
    elif payload.get("type") == "Feature":
        geometry_dict = payload.get("geometry")
    else:
        geometry_dict = payload

    if not isinstance(geometry_dict, dict) or "type" not in geometry_dict:
        raise SystemExit("Invalid geometry payload provided in S2_CLIP_GEOJSON")

    return Geometry(geometry_dict, crs=CRS.WGS84)


def coerce_float(value: Optional[str], default: float) -> float:
    try:
        return float(value) if value is not None else default
    except ValueError:
        return default


def coerce_int(value: Optional[str], default: int) -> int:
    try:
        return int(value) if value is not None else default
    except ValueError:
        return default


def resolve_collection(product_level: str) -> DataCollection:
    mapping = {
        "S2MSI2A": DataCollection.SENTINEL2_L2A,
        "S2MSI1C": DataCollection.SENTINEL2_L1C,
    }
    return mapping.get(product_level, DataCollection.SENTINEL2_L2A)


def get_cloud(item: dict) -> Optional[float]:
    props = item.get("properties", {}) if isinstance(item, dict) else {}
    for key in ("eo:cloud_cover", "cloudCover"):
        if key in props and props[key] is not None:
            try:
                return float(props[key])
            except (TypeError, ValueError):  # pragma: no cover - defensive
                continue
    return None


def choose_scene(items: List[dict], max_cloud: Optional[float]) -> dict:
    if not items:
        raise SystemExit("Tidak ada scene cocok")

    items.sort(key=lambda it: it.get("properties", {}).get("datetime", ""), reverse=True)

    if max_cloud is not None:
        filtered = [item for item in items if get_cloud(item) is None or get_cloud(item) <= max_cloud]
    else:
        filtered = items

    if not filtered:
        filtered = items

    filtered.sort(
        key=lambda item: (
            get_cloud(item) if get_cloud(item) is not None else float("inf"),
            item.get("properties", {}).get("datetime", ""),
        )
    )

    return filtered[0]


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


def download_tiles(
    collection: DataCollection,
    geometry: Geometry,
    bbox: BBox,
    time_from: str,
    time_to: str,
    max_cloud: Optional[float],
    resolution: int,
    tile_dir: Path,
    config: SHConfig,
) -> List[Path]:
    evalscript = build_evalscript()

    width_all, height_all = bbox_to_dimensions(bbox, resolution=resolution)
    max_px = 2500
    n_cols = int(np.ceil(width_all / max_px)) if width_all > max_px else 1
    n_rows = int(np.ceil(height_all / max_px)) if height_all > max_px else 1
    print(f"Split grid: {n_cols} × {n_rows}")

    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(n_cols, n_rows))
    tile_bboxes = splitter.get_bbox_list()

    tile_paths: List[Path] = []
    for idx, tile_bbox in enumerate(tile_bboxes):
        width, height = bbox_to_dimensions(tile_bbox, resolution=resolution)
        width, height = min(width, max_px), min(height, max_px)
        print(f"Tile {idx + 1}/{len(tile_bboxes)}: {width}×{height}")

        data_filter = {
            "timeRange": {"from": time_from, "to": time_to},
            "mosaickingOrder": "mostRecent",
        }
        if max_cloud is not None:
            data_filter["maxCloudCoverage"] = max_cloud

        request = SentinelHubRequest(
            evalscript=evalscript,
            input_data=[{
                "dataCollection": collection,
                "dataFilter": data_filter,
                "processing": {
                    "upsampling": "BILINEAR",
                    "downsampling": "BILINEAR",
                    "harmonizeValues": True,
                },
            }],
            responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
            bbox=tile_bbox,
            size=(width, height),
            data_folder=str(tile_dir),
            config=config,
        )
        _ = request.get_data(save_data=True, show_progress=True)
        for filename in request.get_filename_list():
            tile_paths.append(tile_dir / filename)

    return tile_paths


def mosaic_and_clip(
    tile_paths: Iterable[Path],
    geometry_dict: dict,
    output_path: Path,
    nodata: float,
) -> None:
    tile_paths = list(tile_paths)
    if not tile_paths:
        raise SystemExit("Tidak ada tile yang diunduh untuk dibuat mosaic")

    sources = [rasterio.open(path) for path in tile_paths]
    try:
        mosaic, out_transform = merge(sources)

        base_meta = sources[0].meta.copy()
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

        temp_merged = output_path.with_name(output_path.stem + "_merged.tif")
        with rasterio.open(temp_merged, "w", **meta) as dst:
            dst.write(mosaic)
        print("Merged TIFF (bbox) disimpan:", temp_merged)

        with rasterio.open(temp_merged) as src:
            out_img, out_transform = mask.mask(src, [geometry_dict], crop=True, nodata=nodata)
            out_meta = src.meta.copy()
            out_meta.update({
                "driver": "GTiff",
                "height": out_img.shape[1],
                "width": out_img.shape[2],
                "transform": out_transform,
                "compress": "lzw",
                "nodata": nodata,
            })

        with rasterio.open(output_path, "w", **out_meta) as dst:
            dst.write(out_img)

        print("Merged TIFF masked (poligon AOI) disimpan:", output_path)
        temp_merged.unlink(missing_ok=True)
    finally:
        for src in sources:
            src.close()


def main() -> None:
    client_id = read_env("COPERNICUS_CLIENT_ID")
    client_secret = read_env("COPERNICUS_CLIENT_SECRET")

    if not client_id or not client_secret:
        raise SystemExit("COPERNICUS_CLIENT_ID dan COPERNICUS_CLIENT_SECRET wajib diisi")

    geometry_raw = read_env("S2_CLIP_GEOJSON")
    if not geometry_raw:
        raise SystemExit("S2_CLIP_GEOJSON tidak tersedia")

    geometry = parse_geometry(geometry_raw)
    bbox = geometry.bbox

    product_level = read_env("S2_CLIP_PRODUCT_LEVEL", "S2MSI2A")
    collection = resolve_collection(product_level)

    max_cloud = coerce_float(read_env("S2_CLIP_MAX_CLOUD"), None)
    limit = coerce_int(read_env("S2_CLIP_LIMIT"), 60)
    resolution = coerce_int(read_env("S2_CLIP_RESOLUTION"), 10)
    nodata = coerce_float(read_env("S2_CLIP_NODATA"), 0.0)

    date_from = read_env("S2_CLIP_DATE_FROM")
    date_to = read_env("S2_CLIP_DATE_TO")
    scene_datetime = read_env("S2_CLIP_SCENE_DATETIME")

    if scene_datetime:
        dt = dateparser.isoparse(scene_datetime)
        date_from = (dt - timedelta(hours=1)).isoformat()
        date_to = (dt + timedelta(hours=1)).isoformat()

    if not date_from or not date_to:
        raise SystemExit("Tanggal mulai dan akhir pencarian wajib diisi")

    output_path = read_env("S2_CLIP_OUTPUT")
    if not output_path:
        raise SystemExit("S2_CLIP_OUTPUT wajib diisi dengan path file GeoTIFF tujuan")

    output_path = Path(output_path).expanduser().resolve()
    ensure_directory(output_path.parent)

    tile_dir = read_env("S2_CLIP_TILE_DIR")
    tile_dir_path = Path(tile_dir).expanduser().resolve() if tile_dir else output_path.parent / "tiles"
    ensure_directory(tile_dir_path)

    config = SHConfig()
    config.sh_client_id = client_id
    config.sh_client_secret = client_secret
    config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
    config.sh_base_url = "https://sh.dataspace.copernicus.eu"

    catalog = SentinelHubCatalog(config=config)
    print(
        "Mencari scene dengan parameter:",
        {
            "collection": collection.api_id,
            "date_from": date_from,
            "date_to": date_to,
            "limit": limit,
            "max_cloud": max_cloud,
        },
    )

    search_iter = catalog.search(
        collection,
        geometry=geometry,
        time=(date_from, date_to),
        limit=limit,
    )
    items = list(search_iter)

    if scene_id := read_env("S2_CLIP_SCENE_ID"):
        items = [item for item in items if item.get("id") == scene_id]
        if not items:
            print("Scene ID yang diminta tidak ditemukan dalam hasil pencarian. Menggunakan hasil penuh.")
            search_iter = catalog.search(
                collection,
                geometry=geometry,
                time=(date_from, date_to),
                limit=limit,
            )
            items = list(search_iter)

    chosen = choose_scene(items, max_cloud)
    chosen_time = chosen.get("properties", {}).get("datetime")
    print(
        "Scene terpilih:",
        chosen.get("id"),
        "waktu:",
        chosen_time,
        "cloud:",
        get_cloud(chosen),
    )

    if chosen_time:
        dt = dateparser.isoparse(chosen_time)
        time_from = (dt - timedelta(hours=1)).isoformat()
        time_to = (dt + timedelta(hours=1)).isoformat()
    else:
        time_from, time_to = date_from, date_to

    tile_paths = download_tiles(
        collection=collection,
        geometry=geometry,
        bbox=bbox,
        time_from=time_from,
        time_to=time_to,
        max_cloud=max_cloud,
        resolution=resolution,
        tile_dir=tile_dir_path,
        config=config,
    )

    print("Tiles disimpan:", [str(path) for path in tile_paths])

    mosaic_and_clip(tile_paths, geometry.geometry, output_path, nodata)

    if read_env("S2_CLIP_KEEP_TILES", "false").lower() not in {"1", "true", "yes"}:
        shutil.rmtree(tile_dir_path, ignore_errors=True)


if __name__ == "__main__":  # pragma: no cover - script entry point
    main()
