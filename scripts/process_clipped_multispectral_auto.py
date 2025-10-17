import json
import os
import shutil
from datetime import datetime, timedelta, timezone
from typing import Optional

from dateutil import parser as dateparser
import numpy as np
import rasterio
from rasterio.merge import merge
from rasterio import mask

from sentinelhub import (
    SHConfig,
    SentinelHubCatalog,
    SentinelHubRequest,
    CRS,
    bbox_to_dimensions,
    MimeType,
    DataCollection,
    Geometry,
)
from sentinelhub.areas import BBoxSplitter


def require_env(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        raise SystemExit(f"Environment variable {name} is required")
    return value


def parse_start_date(raw: Optional[str]) -> datetime:
    if not raw:
        return datetime.now(timezone.utc) - timedelta(days=30)

    dt = dateparser.isoparse(raw)
    if isinstance(dt, datetime):
        if dt.tzinfo is None:
            return dt.replace(tzinfo=timezone.utc)
        return dt.astimezone(timezone.utc)

    raise SystemExit("Unable to parse CLIP_START_DATE")


SH_CLIENT_ID = require_env("COPERNICUS_CLIENT_ID")
SH_CLIENT_SECRET = require_env("COPERNICUS_CLIENT_SECRET")

config = SHConfig()
config.sh_client_id = SH_CLIENT_ID
config.sh_client_secret = SH_CLIENT_SECRET
config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
config.sh_base_url = "https://sh.dataspace.copernicus.eu"

tiles_dir = os.environ.get("CLIP_TILES_DIR") or "tiles"
output_path = os.environ.get("CLIP_OUTPUT") or os.path.join(os.getcwd(), "merged_masked.tif")
merged_tif = os.path.join(tiles_dir, "merged_fixed.tif")
os.makedirs(tiles_dir, exist_ok=True)

for entry in os.listdir(tiles_dir):
    entry_path = os.path.join(tiles_dir, entry)
    if os.path.isdir(entry_path):
        shutil.rmtree(entry_path)
    else:
        os.remove(entry_path)

geometry_raw = require_env("CLIP_GEOMETRY")
try:
    geometry_obj = json.loads(geometry_raw)
except json.JSONDecodeError as exc:
    raise SystemExit(f"Unable to parse CLIP_GEOMETRY: {exc}")

if geometry_obj.get("type") == "Feature" and geometry_obj.get("geometry"):
    geom_dict = geometry_obj["geometry"]
else:
    geom_dict = geometry_obj

if not isinstance(geom_dict, dict):
    raise SystemExit("CLIP_GEOMETRY must describe a valid GeoJSON geometry")

geometry = Geometry(geom_dict, crs=CRS.WGS84)
bbox = geometry.bbox

start_date = parse_start_date(os.environ.get("CLIP_START_DATE"))
end_date = datetime.now(timezone.utc)
max_records = int(os.environ.get("CLIP_MAX_RECORDS", "10"))
if max_records <= 0:
    max_records = 10

resolution = int(os.environ.get("CLIP_RESOLUTION", "10"))
if resolution <= 0:
    resolution = 10

catalog = SentinelHubCatalog(config=config)
search_iter = catalog.search(
    DataCollection.SENTINEL2_L2A,
    geometry=geometry,
    time=(start_date.isoformat(), end_date.isoformat()),
    limit=max_records,
)
items = list(search_iter)

if not items:
    raise SystemExit("No Sentinel-2 scenes found for the provided area and date range")

items.sort(key=lambda it: it.get("properties", {}).get("datetime", ""), reverse=True)
chosen = items[0]
chosen_props = chosen.get("properties", {})
chosen_time = chosen_props.get("datetime")
if not chosen_time:
    raise SystemExit("Selected Sentinel-2 item is missing acquisition datetime")

print("Selected scene:", chosen.get("id"), "acquired at:", chosen_time)

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
if dt.tzinfo is None:
    dt = dt.replace(tzinfo=timezone.utc)
else:
    dt = dt.astimezone(timezone.utc)

t_from = (dt - timedelta(hours=1)).isoformat()
t_to = (dt + timedelta(hours=1)).isoformat()

w_all, h_all = bbox_to_dimensions(bbox, resolution=resolution)
max_px = 2500
n_cols = int(np.ceil(w_all / max_px)) if w_all > max_px else 1
n_rows = int(np.ceil(h_all / max_px)) if h_all > max_px else 1
print(f"Split grid: {n_cols} × {n_rows}")

splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(n_cols, n_rows))
tile_bboxes = splitter.get_bbox_list()

tile_paths = []
for idx, tile_bb in enumerate(tile_bboxes):
    w, h = bbox_to_dimensions(tile_bb, resolution=resolution)
    w = min(w, max_px)
    h = min(h, max_px)
    print(f"Tile {idx + 1}/{len(tile_bboxes)}: {w}×{h}")

    request = SentinelHubRequest(
        evalscript=evalscript_all_bands,
        input_data=[
            SentinelHubRequest.input_data(
                data_collection=DataCollection.SENTINEL2_L2A,
                time_interval=(t_from, t_to),
                mosaicking_order="mostRecent",
            )
        ],
        responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
        bbox=tile_bb,
        size=(w, h),
        data_folder=tiles_dir,
        config=config,
    )

    request.get_data(save_data=True, show_progress=True)
    for path in request.get_filename_list():
        tile_paths.append(os.path.join(tiles_dir, path))

if not tile_paths:
    raise SystemExit("No tile data was downloaded for the requested area")

srcs = [rasterio.open(p) for p in tile_paths]
try:
    mosaic, out_transform = merge(srcs)
finally:
    for src in srcs:
        src.close()

with rasterio.open(tile_paths[0]) as sample_src:
    base_meta = sample_src.meta.copy()
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
    "nodata": 0,
}

with rasterio.open(merged_tif, "w", **meta) as dst:
    dst.write(mosaic)
print("Merged TIFF saved:", merged_tif)

with rasterio.open(merged_tif) as src:
    out_img, out_transform = mask.mask(src, [geom_dict], crop=True, nodata=0)
    out_meta = src.meta.copy()
    out_meta.update(
        {
            "driver": "GTiff",
            "height": out_img.shape[1],
            "width": out_img.shape[2],
            "transform": out_transform,
            "compress": "lzw",
            "nodata": 0,
        }
    )

with rasterio.open(output_path, "w", **out_meta) as dst:
    dst.write(out_img)

print("Clipped Sentinel-2 imagery saved to:", output_path)
