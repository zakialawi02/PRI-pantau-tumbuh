import json
import os
import shutil
from datetime import timedelta

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


def _load_json_from_env(var_name: str, default=None):
    value = os.getenv(var_name)
    if value is None or value.strip() == "":
        return default
    try:
        return json.loads(value)
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Invalid JSON provided for {var_name}: {exc}")


def _ensure_directory(path: str) -> str:
    if not path:
        return path
    os.makedirs(path, exist_ok=True)
    return path


def _resolve_geometry(aoi_geojson):
    if not isinstance(aoi_geojson, dict):
        raise SystemExit("AOI_GEOJSON must be a GeoJSON FeatureCollection dictionary")

    features = aoi_geojson.get("features") or []
    if not features:
        raise SystemExit("AOI_GEOJSON does not contain any features")

    geometry_dict = features[0].get("geometry")
    if not geometry_dict:
        raise SystemExit("First feature in AOI_GEOJSON does not include geometry")

    return Geometry(geometry_dict, crs=CRS.WGS84)


# ====== KONFIGURASI ======
config = SHConfig()
config.sh_client_id = os.getenv("SH_CLIENT_ID", "")
config.sh_client_secret = os.getenv("SH_CLIENT_SECRET", "")
config.sh_token_url = os.getenv(
    "SH_TOKEN_URL",
    "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token",
)
config.sh_base_url = os.getenv("SH_BASE_URL", "https://sh.dataspace.copernicus.eu")

tiles_dir = _ensure_directory(os.getenv("CLIP_TILE_DIR", "tiles"))
merged_tif = os.getenv("CLIP_MERGED_TIF", "merged_fixed.tif")
masked_tif = os.getenv("CLIP_MASKED_TIF", "merged_masked.tif")
output_tif = os.getenv("OUTPUT_TIF", masked_tif)


# ====== PARAMETER ======
DATE_FROM = os.getenv("DATE_FROM", "2025-08-01")
DATE_TO = os.getenv("DATE_TO", "2025-08-31")
MAX_CLOUD = float(os.getenv("MAX_CLOUD", "60"))
LIMIT = int(os.getenv("LIMIT", "100"))
RES = int(os.getenv("RES", "10"))
NODATA_VAL = float(os.getenv("NODATA_VAL", "0"))
SCENE_ID = os.getenv("SCENE_ID")

AOI_GEOJSON = _load_json_from_env("AOI_GEOJSON")
if AOI_GEOJSON is None:
    raise SystemExit("AOI_GEOJSON environment variable is required")

geometry = _resolve_geometry(AOI_GEOJSON)
bbox = geometry.bbox

# ====== CARI SCENE ======
catalog = SentinelHubCatalog(config=config)
search_iter = catalog.search(
    DataCollection.SENTINEL2_L1C,
    geometry=geometry,
    time=(DATE_FROM, DATE_TO),
    limit=LIMIT,
)
items = list(search_iter)


def get_cloud(item):
    props = item.get("properties", {})
    for k in ("eo:cloud_cover", "cloudCover"):
        if k in props and props[k] is not None:
            return float(props[k])
    return None

if not items:
    raise SystemExit("Tidak ada scene cocok")

items.sort(key=lambda it: it["properties"].get("datetime"), reverse=True)
filtered = [it for it in items if (get_cloud(it) is None or get_cloud(it) <= MAX_CLOUD)]

if SCENE_ID:
    chosen = next((it for it in filtered if it.get("id") == SCENE_ID), None)
    if chosen is None:
        chosen = next((it for it in items if it.get("id") == SCENE_ID), None)
        if chosen is None:
            raise SystemExit(f"Scene dengan ID {SCENE_ID} tidak ditemukan dalam hasil pencarian")
else:
    chosen = filtered[0] if filtered else items[0]

chosen_time = chosen["properties"]["datetime"]
print("Scene terpilih:", chosen["id"], "waktu:", chosen_time, "cloud:", get_cloud(chosen))

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

dt = dateparser.isoparse(chosen_time)
t_from = (dt - timedelta(hours=1)).isoformat()
t_to   = (dt + timedelta(hours=1)).isoformat()

# ====== Tiling ======
w_all, h_all = bbox_to_dimensions(bbox, resolution=RES)
max_px = 2500
n_cols = int(np.ceil(w_all / max_px)) if w_all > max_px else 1
n_rows = int(np.ceil(h_all / max_px)) if h_all > max_px else 1
print(f"Split grid: {n_cols} × {n_rows}")

splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(n_cols, n_rows))
tile_bboxes = splitter.get_bbox_list()

tile_paths = []
for idx, tile_bb in enumerate(tile_bboxes):
    w, h = bbox_to_dimensions(tile_bb, resolution=RES)
    w, h = min(w, max_px), min(h, max_px)
    print(f"Tile {idx+1}/{len(tile_bboxes)}: {w}×{h}")

    request = SentinelHubRequest(
        evalscript=evalscript_all_bands,
        input_data=[
            {
                "type": "sentinel-2-l1c",
                "dataFilter": {
                    "timeRange": {"from": t_from, "to": t_to},
                    "mosaickingOrder": "mostRecent",
                    "maxCloudCoverage": MAX_CLOUD,
                },
                "processing": {
                    "upsampling": "BILINEAR",
                    "downsampling": "BILINEAR",
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
    for p in request.get_filename_list():
        tile_paths.append(os.path.join(tiles_dir, p))

print("Tiles disimpan:", tile_paths)

# ====== Gabung & Masking ======
if tile_paths:
    srcs = [rasterio.open(p) for p in tile_paths]
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
        "nodata": NODATA_VAL
    }

    # tulis hasil merge sementara
    with rasterio.open(merged_tif, "w", **meta) as dst:
        dst.write(mosaic)
    print("Merged TIFF (bbox) disimpan:", merged_tif)

    for s in srcs: s.close()

    # mask ke poligon AOI
    with rasterio.open(merged_tif) as src:
        out_img, out_transform = mask.mask(src, [geom_dict], crop=True, nodata=NODATA_VAL)
        out_meta = src.meta.copy()
        out_meta.update({
            "driver": "GTiff",
            "height": out_img.shape[1],
            "width": out_img.shape[2],
            "transform": out_transform,
            "compress": "lzw",
            "nodata": NODATA_VAL
        })

    with rasterio.open(masked_tif, "w", **out_meta) as dst:
        dst.write(out_img)

    print("Merged TIFF masked (poligon AOI) disimpan:", masked_tif)

    if output_tif:
        output_dir = os.path.dirname(output_tif)
        if output_dir:
            os.makedirs(output_dir, exist_ok=True)

        if os.path.abspath(masked_tif) != os.path.abspath(output_tif):
            shutil.move(masked_tif, output_tif)
            print("Final clipped imagery disimpan:", output_tif)
        else:
            print("Final clipped imagery disimpan pada lokasi default:", output_tif)
else:
    raise SystemExit("Tidak ada tile yang diunduh. Periksa parameter pencarian Anda.")
