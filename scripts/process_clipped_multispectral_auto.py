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

DATASPACE_AUTH_BASE = "https://identity.dataspace.copernicus.eu"
DATASPACE_TOKEN_URL = f"{DATASPACE_AUTH_BASE}/auth/realms/CDSE/protocol/openid-connect/token"
DATASPACE_SH_BASE = "https://sh.dataspace.copernicus.eu"

# Ensure downstream Sentinel Hub helpers default to Copernicus Data Space endpoints.
# Some sentinelhub-py internals read the SH_* env vars directly, so make sure we
# override any pre-existing configuration that could still point to the legacy
# services.sentinel-hub.com host.
os.environ["SH_AUTH_BASE_URL"] = DATASPACE_AUTH_BASE
os.environ["SH_BASE_URL"] = DATASPACE_SH_BASE
os.environ["SH_PROCESSING_API_URL"] = f"{DATASPACE_SH_BASE}/api/v1/process"
os.environ["SH_ONLINE_PROCESSING_BASE_URL"] = DATASPACE_SH_BASE

config = SHConfig()
config.sh_client_id = SH_CLIENT_ID
config.sh_client_secret = SH_CLIENT_SECRET
config.sh_auth_base_url = DATASPACE_AUTH_BASE
config.sh_token_url = DATASPACE_TOKEN_URL
config.sh_base_url = DATASPACE_SH_BASE
config.sh_processing_api_url = f"{DATASPACE_SH_BASE}/api/v1/process"
config.sh_online_processing_base_url = DATASPACE_SH_BASE
config.instance_id = None

print("Configured Copernicus Data Space endpoints:", config.sh_base_url)
print("Processing API endpoint:", getattr(config, "sh_processing_api_url", "<not set>"))

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


def bounds_from_tuple(bounds_tuple):
    return (
        float(bounds_tuple[0]),
        float(bounds_tuple[1]),
        float(bounds_tuple[2]),
        float(bounds_tuple[3]),
    )


target_bounds = bounds_from_tuple(
    (
        bbox.min_x,
        bbox.min_y,
        bbox.max_x,
        bbox.max_y,
    )
)

try:
    target_shape = geometry.geometry
except Exception:
    target_shape = None


def intersects_target(scene):
    geom_dict_item = scene.get("geometry")
    candidate_geom = None
    candidate_bounds = None

    if geom_dict_item:
        try:
            candidate_geom = Geometry(geom_dict_item, CRS.WGS84)
            candidate_bounds = bounds_from_tuple(
                (
                    candidate_geom.bbox.min_x,
                    candidate_geom.bbox.min_y,
                    candidate_geom.bbox.max_x,
                    candidate_geom.bbox.max_y,
                )
            )
        except Exception:
            candidate_geom = None
            candidate_bounds = None

    if candidate_bounds is None:
        bbox_coords = scene.get("bbox")
        if isinstance(bbox_coords, (list, tuple)) and len(bbox_coords) == 4:
            try:
                candidate_bounds = bounds_from_tuple(bbox_coords)
            except Exception:
                candidate_bounds = None

    if candidate_bounds is None:
        return False

    a_min_x, a_min_y, a_max_x, a_max_y = candidate_bounds
    b_min_x, b_min_y, b_max_x, b_max_y = target_bounds
    intersects = not (
        a_max_x < b_min_x
        or a_min_x > b_max_x
        or a_max_y < b_min_y
        or a_min_y > b_max_y
    )

    if not intersects:
        return False

    if target_shape is not None and candidate_geom is not None:
        try:
            candidate_shape = candidate_geom.geometry
        except Exception:
            candidate_shape = None

        if candidate_shape is not None and hasattr(candidate_shape, "intersects"):
            try:
                return bool(candidate_shape.intersects(target_shape))
            except Exception:
                return True

    return True


items = []
search_start = start_date

for attempt in range(6):
    search_iter = catalog.search(
        DataCollection.SENTINEL2_L2A,
        bbox=bbox,
        time=(search_start.isoformat(), end_date.isoformat()),
        limit=max_records,
    )
    attempt_items = [scene for scene in search_iter if intersects_target(scene)]

    if attempt_items:
        items = attempt_items
        break

    search_start = search_start - timedelta(days=30)
    if search_start < datetime(2015, 6, 23, tzinfo=timezone.utc):
        break

    print(
        "No Sentinel-2 scenes intersect the area between",
        search_start.isoformat(),
        "and",
        end_date.isoformat(),
        "- extending search window",
    )

if not items:
    raise SystemExit("No Sentinel-2 scenes intersect the provided area and date range")

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
