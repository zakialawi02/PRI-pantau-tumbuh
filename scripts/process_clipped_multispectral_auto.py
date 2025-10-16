import json
import os
from datetime import timedelta

import numpy as np
import rasterio
from dateutil import parser as dateparser
from rasterio import mask
from rasterio.merge import merge

from typing import Optional

from sentinelhub import (
    CRS,
    BBox,
    DataCollection,
    Geometry,
    MimeType,
    SHConfig,
    SentinelHubCatalog,
    SentinelHubRequest,
    bbox_to_dimensions,
)
from sentinelhub.areas import BBoxSplitter


def getenv(name: str, default=None):
    value = os.environ.get(name)
    if value is None or value == "":
        return default
    return value


def as_float(name: str, default: float):
    value = getenv(name)
    if value is None:
        return float(default)
    try:
        return float(value)
    except ValueError:
        return float(default)


def as_int(name: str, default: int):
    value = getenv(name)
    if value is None:
        return int(default)
    try:
        return int(value)
    except ValueError:
        return int(default)


def resolve_data_collection(product_level: Optional[str]):
    mapping = {
        "s2msi1c": DataCollection.SENTINEL2_L1C,
        "l1c": DataCollection.SENTINEL2_L1C,
        "s2msi2a": DataCollection.SENTINEL2_L2A,
        "l2a": DataCollection.SENTINEL2_L2A,
    }
    if not product_level:
        return DataCollection.SENTINEL2_L2A
    key = str(product_level).strip().lower()
    return mapping.get(key, DataCollection.SENTINEL2_L2A)


def parse_geometry(raw_geojson: str | None):
    if not raw_geojson:
        raise SystemExit("❌ Environment variable S2_GEOJSON is required.")
    try:
        geojson = json.loads(raw_geojson)
    except json.JSONDecodeError as exc:
        raise SystemExit(f"❌ Unable to parse GeoJSON payload: {exc}")

    if geojson.get("type") == "FeatureCollection":
        features = geojson.get("features") or []
        if not features:
            raise SystemExit("❌ GeoJSON feature collection does not contain any features.")
        geometry = features[0].get("geometry")
    elif geojson.get("type") == "Feature":
        geometry = geojson.get("geometry")
    else:
        geometry = geojson

    if not geometry:
        raise SystemExit("❌ GeoJSON payload does not include a geometry object.")

    return geometry


def ensure_directory(path: str):
    if not path:
        return
    os.makedirs(path, exist_ok=True)


def main():
    # ====== KONFIGURASI KREDENSIAL ======
    config = SHConfig()
    config.sh_client_id = getenv("SENTINELHUB_CLIENT_ID", getenv("COPERNICUS_CLIENT_ID"))
    config.sh_client_secret = getenv("SENTINELHUB_CLIENT_SECRET", getenv("COPERNICUS_CLIENT_SECRET"))
    token_url = getenv(
        "SENTINELHUB_TOKEN_URL",
        "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token",
    )
    base_url = getenv("SENTINELHUB_BASE_URL", "https://sh.dataspace.copernicus.eu")

    config.sh_token_url = token_url
    config.sh_base_url = base_url

    if not config.sh_client_id or not config.sh_client_secret:
        raise SystemExit("❌ Sentinel Hub credentials are not configured in environment variables.")

    # ====== PARAMETER ======
    raw_geojson = getenv("S2_GEOJSON")
    geom_dict = parse_geometry(raw_geojson)
    geometry = Geometry(geom_dict, crs=CRS.WGS84)

    date_from = getenv("S2_DATE_FROM")
    date_to = getenv("S2_DATE_TO")
    scene_id = getenv("S2_SCENE_ID")
    scene_datetime = getenv("S2_SCENE_DATETIME")
    cloud_limit = as_float("S2_MAX_CLOUD", 60)
    limit = as_int("S2_SEARCH_LIMIT", 50)
    resolution = as_float("S2_RESOLUTION", 10)
    nodata_val = as_float("S2_NODATA", 0)
    buffer_hours = as_float("S2_TIME_BUFFER_HOURS", 1)
    product_level = getenv("S2_PRODUCT_LEVEL", "S2MSI2A")

    data_collection = resolve_data_collection(product_level)

    tiles_dir = getenv("S2_TILES_DIR", "tiles")
    merged_tif = getenv("S2_MERGED_PATH", os.path.join(tiles_dir, "merged_fixed.tif"))
    output_tif = getenv("S2_OUTPUT_PATH", os.path.join(tiles_dir, "merged_masked.tif"))

    ensure_directory(tiles_dir)
    ensure_directory(os.path.dirname(merged_tif) or tiles_dir)
    ensure_directory(os.path.dirname(output_tif) or tiles_dir)

    # ====== CARI SCENE ======
    catalog = SentinelHubCatalog(config=config)

    search_kwargs = {
        "data_collection": data_collection,
        "limit": limit,
    }

    if scene_id:
        search_kwargs["ids"] = [scene_id]
    else:
        search_kwargs["geometry"] = geometry
        if date_from and date_to:
            search_kwargs["time"] = (date_from, date_to)
        elif date_from:
            search_kwargs["time"] = (date_from, None)
        elif date_to:
            search_kwargs["time"] = (None, date_to)

    search_iter = catalog.search(**search_kwargs)
    items = list(search_iter)

    def get_cloud(item):
        props = item.get("properties", {})
        for key in ("eo:cloud_cover", "cloudCover"):
            if key in props and props[key] is not None:
                try:
                    return float(props[key])
                except (TypeError, ValueError):
                    return None
        return None

    if not items:
        raise SystemExit("❌ Tidak ada scene yang ditemukan untuk parameter yang diberikan.")

    if not scene_id:
        # Sortir berdasarkan tanggal terbaru dan filter cloud cover
        items.sort(key=lambda it: it["properties"]["datetime"], reverse=True)
        filtered = [it for it in items if (get_cloud(it) is None or get_cloud(it) <= cloud_limit)]
        chosen = filtered[0] if filtered else items[0]
    else:
        chosen = items[0]

    chosen_time = chosen["properties"].get("datetime") or scene_datetime
    if not chosen_time:
        raise SystemExit("❌ Scene terpilih tidak memiliki informasi waktu yang valid.")

    print(
        "Scene terpilih:",
        chosen.get("id"),
        "waktu:",
        chosen_time,
        "cloud:",
        get_cloud(chosen),
    )

    dt = dateparser.isoparse(chosen_time)
    t_from = (dt - timedelta(hours=buffer_hours)).isoformat()
    t_to = (dt + timedelta(hours=buffer_hours)).isoformat()

    # ====== Evalscript ======
    evalscript_all_bands = """
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

    bbox: BBox = geometry.bbox
    w_all, h_all = bbox_to_dimensions(bbox, resolution=resolution)
    max_px = as_int("S2_TILE_MAX_PX", 2500)
    n_cols = int(np.ceil(w_all / max_px)) if w_all > max_px else 1
    n_rows = int(np.ceil(h_all / max_px)) if h_all > max_px else 1
    print(f"Split grid: {n_cols} × {n_rows}")

    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(n_cols, n_rows))
    tile_bboxes = splitter.get_bbox_list()

    tile_paths = []
    for idx, tile_bb in enumerate(tile_bboxes):
        w, h = bbox_to_dimensions(tile_bb, resolution=resolution)
        w, h = min(w, max_px), min(h, max_px)
        print(f"Tile {idx + 1}/{len(tile_bboxes)}: {w}×{h}")

        request = SentinelHubRequest(
            evalscript=evalscript_all_bands,
            input_data=[
                {
                    "type": data_collection.api_id,
                    "dataFilter": {
                        "timeRange": {"from": t_from, "to": t_to},
                        "mosaickingOrder": "mostRecent",
                        "maxCloudCoverage": cloud_limit,
                    },
                    "processing": {
                        "upsampling": getenv("S2_UPSAMPLING", "BILINEAR"),
                        "downsampling": getenv("S2_DOWNSAMPLING", "BILINEAR"),
                        "harmonizeValues": True,
                    },
                }
            ],
            responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
            bbox=tile_bb,
            size=(w, h),
            data_folder=tiles_dir,
            config=config,
        )
        _ = request.get_data(save_data=True, show_progress=True)
        for path in request.get_filename_list():
            tile_paths.append(os.path.join(tiles_dir, path))

    if not tile_paths:
        raise SystemExit("❌ Tidak ada tile yang berhasil diunduh dari Sentinel Hub.")

    print("Tiles disimpan:", tile_paths)

    # ====== Gabung & Masking ======
    srcs = [rasterio.open(path) for path in tile_paths]
    mosaic, out_transform = merge(srcs)

    base_meta = srcs[0].meta.copy()
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
        "nodata": nodata_val,
    }

    with rasterio.open(merged_tif, "w", **meta) as dst:
        dst.write(mosaic)
    print("Merged TIFF (bbox) disimpan:", merged_tif)

    for src in srcs:
        src.close()

    with rasterio.open(merged_tif) as src:
        out_img, out_transform = mask.mask(src, [geom_dict], crop=True, nodata=nodata_val)
        out_meta = src.meta.copy()
        out_meta.update(
            {
                "driver": "GTiff",
                "height": out_img.shape[1],
                "width": out_img.shape[2],
                "transform": out_transform,
                "compress": "lzw",
                "nodata": nodata_val,
            }
        )

    with rasterio.open(output_tif, "w", **out_meta) as dst:
        dst.write(out_img)

    print("Merged TIFF masked (poligon AOI) disimpan:", output_tif)


if __name__ == "__main__":
    main()
