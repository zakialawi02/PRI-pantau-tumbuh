import os
import json
from datetime import datetime, timedelta
from dateutil import parser as dateparser
import numpy as np
import rasterio
from rasterio.merge import merge
from rasterio import mask

from sentinelhub import (
    SHConfig,
    SentinelHubCatalog,
    SentinelHubRequest,
    BBox,
    CRS,
    bbox_to_dimensions,
    MimeType,
    DataCollection,
    Geometry,
)
from sentinelhub.areas import BBoxSplitter

# ====== KONFIGURASI ======
def read_env(name: str, default: str | None = None) -> str | None:
    value = os.getenv(name)
    if value is not None and value != "":
        return value
    return default

SH_CLIENT_ID = read_env(
    "COPERNICUS_CLIENT_ID",
    read_env("SENTINELHUB_CLIENT_ID", read_env("SH_CLIENT_ID", "")),
)
SH_CLIENT_SECRET = read_env(
    "COPERNICUS_CLIENT_SECRET",
    read_env("SENTINELHUB_CLIENT_SECRET", read_env("SH_CLIENT_SECRET", "")),
)

if not SH_CLIENT_ID or not SH_CLIENT_SECRET:
    raise SystemExit("Sentinel Hub credentials are not configured.")

config = SHConfig()
config.sh_client_id = SH_CLIENT_ID
config.sh_client_secret = SH_CLIENT_SECRET
config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
config.sh_base_url  = "https://sh.dataspace.copernicus.eu"

# Folder untuk simpan
tiles_dir = read_env("SENTINEL_CLIP_TILE_DIR", "tiles")
merged_tif = read_env("SENTINEL_CLIP_MERGED_PATH", "merged_fixed.tif")
masked_tif = read_env("SENTINEL_CLIP_OUTPUT", "merged_masked.tif")

if tiles_dir:
    os.makedirs(tiles_dir, exist_ok=True)
if merged_tif:
    os.makedirs(os.path.dirname(os.path.abspath(merged_tif)), exist_ok=True)
if masked_tif:
    os.makedirs(os.path.dirname(os.path.abspath(masked_tif)), exist_ok=True)

# ====== PARAMETER ======
today_utc = datetime.utcnow().date()
default_date_to = today_utc.isoformat()
default_date_from = (today_utc - timedelta(days=30)).isoformat()

DATE_FROM = read_env("SENTINEL_CLIP_DATE_FROM", default_date_from)
DATE_TO = read_env("SENTINEL_CLIP_DATE_TO", default_date_to)
LIMIT = int(float(read_env("SENTINEL_CLIP_LIMIT", "100")))
RES = int(float(read_env("SENTINEL_CLIP_RESOLUTION", "10")))
NODATA_VAL = float(read_env("SENTINEL_CLIP_NODATA", "0"))
MAX_CLOUD = float(read_env("SENTINEL_CLIP_MAX_CLOUD", "100"))

MAX_CLOUD = max(0.0, min(100.0, MAX_CLOUD))

try:
    parsed_from = dateparser.isoparse(DATE_FROM)
    parsed_to = dateparser.isoparse(DATE_TO)
except Exception as exc:
    raise SystemExit(f"Invalid date range supplied: {exc}")

if parsed_from > parsed_to:
    parsed_from, parsed_to = parsed_to, parsed_from

DATE_FROM = parsed_from.date().isoformat()
DATE_TO = parsed_to.date().isoformat()

collection_name_env = (read_env("SENTINEL_CLIP_COLLECTION", "SENTINEL2_L2A") or "").strip()
normalized_collection_name = collection_name_env.upper().replace("-", "_")
try:
    collection = getattr(DataCollection, normalized_collection_name)
except AttributeError:
    collection = DataCollection.SENTINEL2_L2A
    normalized_collection_name = "SENTINEL2_L2A"

collection_type_lookup = {
    "SENTINEL2_L1C": "sentinel-2-l1c",
    "SENTINEL2_L2A": "sentinel-2-l2a",
}
collection_type = collection_type_lookup.get(
    normalized_collection_name, "sentinel-2-l2a"
)

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
            [
              113.10556948908851,
              -1.4785838381847753
            ],
            [
              113.13498704735412,
              -1.477397860955648
            ],
            [
              113.13260498344499,
              -1.4551146096165866
            ],
            [
              113.110312887968,
              -1.4572450555405538
            ],
            [
              113.11363295381193,
              -1.4361448121779006
            ],
            [
              113.13663824669305,
              -1.439465002506239
            ],
            [
              113.13305713167733,
              -1.4245759676015979
            ],
            [
              113.09608125915514,
              -1.4261803371710613
            ],
            [
              113.09750378353874,
              -1.454632040366974
            ],
            [
              113.07592870194452,
              -1.4544083417368086
            ],
            [
              113.07640527659169,
              -1.4764538947092234
            ],
            [
              113.0778250907295,
              -1.5169874677712585
            ],
            [
              113.10912758274577,
              -1.5172370048352803
            ],
            [
              113.10746676566316,
              -1.4902033659093803
            ],
            [
              113.10556948908851,
              -1.4785838381847753
            ]
          ]
        ],
        "type": "Polygon"
      }
    }
  ]
}

def load_geojson() -> dict:
    geojson_env = read_env("SENTINEL_CLIP_GEOJSON") or read_env("CLIP_GEOJSON")
    if not geojson_env:
        return default_geojson

    try:
        parsed = json.loads(geojson_env)
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Invalid SENTINEL_CLIP_GEOJSON provided: {exc}")

    gtype = parsed.get("type")

    if gtype == "FeatureCollection":
        if not parsed.get("features"):
            raise SystemExit("FeatureCollection requires a non-empty features array.")
        return parsed

    if gtype == "Feature":
        if not parsed.get("geometry"):
            raise SystemExit("Feature payload missing geometry definition.")
        return {"type": "FeatureCollection", "features": [parsed]}

    if gtype in {"Polygon", "MultiPolygon"}:
        return {
            "type": "FeatureCollection",
            "features": [
                {
                    "type": "Feature",
                    "properties": {},
                    "geometry": parsed,
                }
            ],
        }

    raise SystemExit(f"Unsupported geometry type for clipping: {gtype}")


AOI_GEOJSON = load_geojson()

if not AOI_GEOJSON["features"]:
    raise SystemExit("Geometry feature collection is empty.")

geom_dict = AOI_GEOJSON["features"][0]["geometry"]
geometry = Geometry(geom_dict, crs=CRS.WGS84)
bbox = geometry.bbox

# ====== CARI SCENE TANPA FILTER CLOUD ======
catalog = SentinelHubCatalog(config=config)
search_iter = catalog.search(
    collection,
    geometry=geometry,
    time=(DATE_FROM, DATE_TO),
    limit=LIMIT
)
items = list(search_iter)

if not items:
    raise SystemExit("Tidak ada scene cocok")

items.sort(key=lambda it: it["properties"]["datetime"], reverse=True)
filtered = items   # <- tidak ada filter cloud coverage

# pilih scene
preferred_scene_id = read_env("SENTINEL_CLIP_SCENE_ID") or read_env("CLIP_SCENE_ID")
chosen = None
if preferred_scene_id:
    chosen = next((it for it in items if str(it.get("id")) == preferred_scene_id), None)
if chosen is None:
    chosen = filtered[0]

chosen_time = chosen["properties"]["datetime"]
print("Scene terpilih:", chosen["id"], "waktu:", chosen_time)

# ====== Evalscript ======
evalscript_all_bands = """
//VERSION=3
function setup() {
    return {
        input: [{ bands: ["B01","B02","B03","B04","B05","B06","B07","B08","B8A","B09","B11","B12"], units: "DN" }],
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
            "type": collection_type,
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

    with rasterio.open(merged_tif, "w", **meta) as dst:
        dst.write(mosaic)
    print("Merged TIFF disimpan:", merged_tif)

    for s in srcs: s.close()

    # clip ke AOI
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

    print("Merged TIFF masked disimpan:", masked_tif)
