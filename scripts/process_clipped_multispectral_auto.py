import os
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

# ====== KONFIGURASI ======
SH_CLIENT_ID = COPERNICUS_CLIENT_ID
SH_CLIENT_SECRET = COPERNICUS_CLIENT_SECRET

config = SHConfig()
config.sh_client_id = SH_CLIENT_ID
config.sh_client_secret = SH_CLIENT_SECRET
config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
config.sh_base_url  = "https://sh.dataspace.copernicus.eu"

# Folder untuk simpan
tiles_dir = "tiles"
merged_tif = "merged_fixed.tif"
masked_tif = "merged_masked.tif"
os.makedirs(tiles_dir, exist_ok=True)

# ====== PARAMETER ======
DATE_FROM = "2025-08-01"
DATE_TO   = "2025-08-31"
LIMIT     = 10
RES       = 10
NODATA_VAL = 0   # ganti ke -9999 kalau lebih cocok

# ====== AOI dari GEOJSON ======
AOI_GEOJSON = {}

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
