import os
import json
from datetime import date, timedelta
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

# ====== KONFIGURASI ======
def read_env(name: str, default: str | None = None) -> str | None:
    value = os.getenv(name)
    if value is not None and value != "":
        return value
    return default


COPERNICUS_CLIENT_ID = read_env(
    "COPERNICUS_CLIENT_ID",
    read_env("SENTINELHUB_CLIENT_ID", read_env("SH_CLIENT_ID", "")),
)
COPERNICUS_CLIENT_SECRET = read_env(
    "COPERNICUS_CLIENT_SECRET",
    read_env("SENTINELHUB_CLIENT_SECRET", read_env("SH_CLIENT_SECRET", "")),
)

if not COPERNICUS_CLIENT_ID or not COPERNICUS_CLIENT_SECRET:
    raise SystemExit(
        "Sentinel Hub credentials are not configured. Please set COPERNICUS_CLIENT_ID and COPERNICUS_CLIENT_SECRET in the environment."
    )

config = SHConfig()
config.sh_client_id = COPERNICUS_CLIENT_ID
config.sh_client_secret = COPERNICUS_CLIENT_SECRET
config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
config.sh_base_url  = "https://sh.dataspace.copernicus.eu"

# Folder untuk simpan
tiles_dir = read_env("SENTINEL_CLIP_TILE_DIR", "tiles")
merged_tif = read_env("SENTINEL_CLIP_MERGED_PATH", "merged_fixed.tif")
masked_tif = read_env("SENTINEL_CLIP_OUTPUT", "merged_masked.tif")

if tiles_dir:
    os.makedirs(tiles_dir, exist_ok=True)

if merged_tif:
    merged_dir = os.path.dirname(os.path.abspath(merged_tif))
    if merged_dir:
        os.makedirs(merged_dir, exist_ok=True)

if masked_tif:
    masked_dir = os.path.dirname(os.path.abspath(masked_tif))
    if masked_dir:
        os.makedirs(masked_dir, exist_ok=True)

# ====== PARAMETER ======
DEFAULT_WINDOW_DAYS = int(float(read_env("SENTINEL_CLIP_WINDOW_DAYS", "30")))
today = date.today()
default_start = (today - timedelta(days=DEFAULT_WINDOW_DAYS)).isoformat()
DATE_FROM = read_env("SENTINEL_CLIP_DATE_FROM", default_start)
DATE_TO   = read_env("SENTINEL_CLIP_DATE_TO", today.isoformat())
MAX_CLOUD = float(read_env("SENTINEL_CLIP_MAX_CLOUD", "60"))
LIMIT     = int(float(read_env("SENTINEL_CLIP_LIMIT", "100")))
RES       = int(float(read_env("SENTINEL_CLIP_RESOLUTION", "10")))
NODATA_VAL = float(read_env("SENTINEL_CLIP_NODATA", "0"))   # ganti ke -9999 kalau lebih cocok

# ====== AOI dari GEOJSON ======
default_geojson = {
    "type": "FeatureCollection",
    "features": [
        {
            "type": "Feature",
            "properties": {},
            "geometry": {
                "coordinates": [
                    [
                        [116.27304185903375, -2.0078305576641355],
                        [116.2726536402987, -1.9755778036038976],
                        [116.3076634368054, -1.977690826811994],
                        [116.31219359322546, -2.0359355805980357],
                        [116.35381797709942, -2.011447175632071],
                        [116.36624508466832, -1.9369590403119616],
                        [116.28423957447802, -1.8958275647812002],
                        [116.19661617154412, -1.8874306480325913],
                        [116.20085708080592, -1.980362609627889],
                        [116.1434742266315, -1.9809655442557812],
                        [116.14135085987988, -1.9383970949463531],
                        [116.09514742155028, -1.9429331710624353],
                        [116.09795074973675, -2.009900225897809],
                        [116.27304185903375, -2.0078305576641355],
                    ]
                ],
                "type": "Polygon",
            },
        }
    ],
}

geojson_env = read_env("SENTINEL_CLIP_GEOJSON") or read_env("CLIP_GEOJSON")
if geojson_env:
    try:
        AOI_GEOJSON = json.loads(geojson_env)
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Invalid SENTINEL_CLIP_GEOJSON provided: {exc}")
else:
    AOI_GEOJSON = default_geojson

# Geometry untuk query & masking
geom_dict = AOI_GEOJSON["features"][0]["geometry"]
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

def get_cloud(item):
    props = item.get("properties", {})
    for k in ("eo:cloud_cover", "cloudCover"):
        if k in props and props[k] is not None:
            return float(props[k])
    return None

if not items:
    raise SystemExit("Tidak ada scene cocok")

items.sort(key=lambda it: it["properties"]["datetime"], reverse=True)
filtered = [it for it in items if (get_cloud(it) is None or get_cloud(it) <= MAX_CLOUD)]

preferred_scene_id = read_env("SENTINEL_CLIP_SCENE_ID") or read_env("CLIP_SCENE_ID")
preferred_datetime = read_env("SENTINEL_CLIP_SCENE_DATETIME") or read_env("CLIP_SCENE_DATETIME")
chosen = None

if preferred_scene_id:
    chosen = next((it for it in items if str(it.get("id")) == preferred_scene_id), None)

if chosen is None and preferred_datetime:
    chosen = next(
        (
            it
            for it in items
            if str(it.get("properties", {}).get("datetime", "")).startswith(preferred_datetime)
        ),
        None,
    )

if chosen is None:
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
        absolute = os.path.join(tiles_dir, p) if not os.path.isabs(p) else p
        tile_paths.append(absolute)

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
