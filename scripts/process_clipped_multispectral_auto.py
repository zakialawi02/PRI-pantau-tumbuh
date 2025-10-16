import os
import json
from datetime import timedelta
from dateutil import parser as dateparser
import numpy as np
import rasterio
from rasterio.merge import merge
from rasterio import mask

from sentinelhub import (
    SHConfig, SentinelHubCatalog, SentinelHubRequest,
    BBox, CRS, bbox_to_dimensions, MimeType, DataCollection, Geometry
)
from sentinelhub.areas import BBoxSplitter


def _env(name, default=None):
    value = os.environ.get(name)
    if value is None or str(value).strip() == "":
        return default
    return value


def _env_json(name, default=None):
    raw = os.environ.get(name)
    if raw is None or str(raw).strip() == "":
        return default
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return default


def _env_float(name, default):
    value = _env(name)
    if value is None:
        return default
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def _env_int(name, default):
    value = _env(name)
    if value is None:
        return default
    try:
        return int(float(value))
    except (TypeError, ValueError):
        return default

# ====== KONFIGURASI ======
SH_CLIENT_ID = _env('SH_CLIENT_ID', _env('SENTINEL_HUB_CLIENT_ID', ''))
SH_CLIENT_SECRET = _env('SH_CLIENT_SECRET', _env('SENTINEL_HUB_CLIENT_SECRET', ''))

config = SHConfig()
config.sh_client_id = SH_CLIENT_ID
config.sh_client_secret = SH_CLIENT_SECRET
config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
config.sh_base_url  = "https://sh.dataspace.copernicus.eu"

# Folder untuk simpan
tiles_dir = _env('CLIP_TILES_DIR', "tiles")
merged_tif = _env('CLIP_MERGED_TIF', "merged_fixed.tif")
masked_tif = _env('CLIP_MASKED_TIF', "merged_masked.tif")

if not tiles_dir:
    tiles_dir = "tiles"
os.makedirs(tiles_dir, exist_ok=True)

merged_dir = os.path.dirname(merged_tif)
if merged_dir:
    os.makedirs(merged_dir, exist_ok=True)

masked_dir = os.path.dirname(masked_tif)
if masked_dir:
    os.makedirs(masked_dir, exist_ok=True)

# ====== PARAMETER ======
DATE_FROM = _env('CLIP_DATE_FROM', "2025-08-01")
DATE_TO   = _env('CLIP_DATE_TO', "2025-08-31")
MAX_CLOUD = _env_float('CLIP_MAX_CLOUD', 60)
LIMIT     = _env_int('CLIP_LIMIT', 100)
RES       = _env_int('CLIP_RESOLUTION', 10)
NODATA_VAL = 0   # ganti ke -9999 kalau lebih cocok

# ====== AOI dari GEOJSON ======
AOI_GEOJSON = _env_json('CLIP_GEOJSON', AOI_GEOJSON)

if not AOI_GEOJSON:
    raise SystemExit("AOI GeoJSON is required for clipped processing.")

if AOI_GEOJSON.get("type") == "FeatureCollection":
    features = AOI_GEOJSON.get("features") or []
    if not features:
        raise SystemExit("AOI GeoJSON feature collection is empty.")
    geom_dict = features[0].get("geometry")
elif AOI_GEOJSON.get("type") == "Feature":
    geom_dict = AOI_GEOJSON.get("geometry")
else:
    geom_dict = AOI_GEOJSON

if not geom_dict:
    raise SystemExit("AOI geometry is invalid.")

# Geometry untuk query & masking
geometry = Geometry(geom_dict, crs=CRS.WGS84)
bbox = geometry.bbox

# ====== CARI SCENE ======
catalog = SentinelHubCatalog(config=config)
search_iter = catalog.search(
    DataCollection.SENTINEL2_L1C,
    geometry=geometry,
    time=(DATE_FROM, DATE_TO),
    limit=LIMIT
)
items = list(search_iter)

target_scene_id = (_env('CLIP_SCENE_ID', '') or '').strip()
target_product_id = (_env('CLIP_SCENE_PRODUCT_ID', '') or '').strip()

def get_cloud(item):
    props = item.get("properties", {})
    for k in ("eo:cloud_cover", "cloudCover"):
        if k in props and props[k] is not None:
            return float(props[k])
    return None

if not items:
    raise SystemExit("Tidak ada scene cocok")

target_item = None
if target_scene_id or target_product_id:
    for candidate in items:
        props = candidate.get("properties", {})
        candidate_id = str(candidate.get("id", "")).strip()
        product_identifier = str(props.get("productIdentifier", "")).strip()
        if target_scene_id and candidate_id == target_scene_id:
            target_item = candidate
            break
        if target_product_id and product_identifier == target_product_id:
            target_item = candidate
            break

items.sort(key=lambda it: it["properties"]["datetime"], reverse=True)
filtered = [it for it in items if (get_cloud(it) is None or get_cloud(it) <= MAX_CLOUD)]

if target_item is not None:
    chosen = target_item
else:
    chosen = filtered[0] if filtered else items[0]
    if target_scene_id or target_product_id:
        print("Target scene not found; falling back to best available scene.")

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
        input_data=[{
            "type": "sentinel-2-l1c",
            "dataFilter": {
                "timeRange": {"from": t_from, "to": t_to},
                "mosaickingOrder": "mostRecent",
                "maxCloudCoverage": MAX_CLOUD
            },
            "processing": {"upsampling": "BILINEAR","downsampling": "BILINEAR","harmonizeValues": True}
        }],
        responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
        bbox=tile_bb,
        size=(w, h),
        data_folder=tiles_dir,
        config=config
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
