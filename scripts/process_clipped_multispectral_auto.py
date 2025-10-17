import json
import os
import shutil
import sys
from pathlib import Path
import numpy as np
import rasterio
from rasterio import mask
from rasterio.merge import merge

from sentinelhub import (
    SHConfig,
    SentinelHubCatalog,
    SentinelHubRequest,
    CRS,
    DataCollection,
    Geometry,
    MimeType,
    bbox_to_dimensions,
)
from sentinelhub.areas import BBoxSplitter


def log(message: str) -> None:
    """Print a message to stdout with flush for immediate feedback."""

    print(message, flush=True)


def require_env(name: str, allow_empty: bool = False) -> str:
    """Return an environment variable or exit with an error if missing."""

    value = os.environ.get(name)
    if value is None:
        raise SystemExit(f"Missing required environment variable: {name}")

    if not allow_empty and str(value).strip() == "":
        raise SystemExit(f"Environment variable {name} must not be empty.")

    return value


def load_geometry() -> dict:
    """Parse the AOI geometry from environment variables."""

    raw = require_env("CLIP_GEOMETRY")
    try:
        geometry = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Invalid CLIP_GEOMETRY JSON: {exc}") from exc

    if geometry.get("type") == "Feature" and geometry.get("geometry"):
        geometry = geometry["geometry"]

    geom_type = str(geometry.get("type", "")).upper()
    if geom_type not in {"POLYGON", "MULTIPOLYGON"}:
        raise SystemExit("CLIP_GEOMETRY must describe a Polygon or MultiPolygon.")

    return geometry


def resolve_collection(name: str):
    mapping = {
        "sentinel-2-l1c": (DataCollection.SENTINEL2_L1C, "sentinel-2-l1c"),
        "sentinel-2-l2a": (DataCollection.SENTINEL2_L2A, "sentinel-2-l2a"),
    }
    key = (name or "sentinel-2-l2a").lower()
    return mapping.get(key, mapping["sentinel-2-l2a"])


def ensure_directory(path: str) -> str:
    directory = Path(path)
    directory.mkdir(parents=True, exist_ok=True)
    return str(directory.resolve())


def clear_directory(path: str) -> None:
    directory = Path(path)
    if not directory.exists() or not directory.is_dir():
        return

    for item in directory.iterdir():
        try:
            if item.is_dir():
                shutil.rmtree(item)
            else:
                item.unlink()
        except Exception as exc:  # pragma: no cover - best effort cleanup
            log(f"Warning: unable to remove {item}: {exc}")


def main() -> None:
    client_id = require_env("COPERNICUS_CLIENT_ID")
    client_secret = require_env("COPERNICUS_CLIENT_SECRET")

    geometry_dict = load_geometry()
    output_path = require_env("CLIP_OUTPUT_PATH")
    tiles_dir = ensure_directory(os.environ.get("CLIP_TILE_DIR", os.path.join(os.getcwd(), "tiles")))
    work_dir = str(Path(tiles_dir).parent)
    ensure_directory(work_dir)

    clear_directory(tiles_dir)

    merged_path = os.path.join(work_dir, "merged_bbox.tif")
    if os.path.exists(merged_path):
        os.remove(merged_path)

    output_parent = Path(output_path).parent
    ensure_directory(str(output_parent))

    collection_enum, collection_type = resolve_collection(os.environ.get("CLIP_COLLECTION"))

    scene_id = os.environ.get("CLIP_SCENE_ID") or None
    time_from = require_env("CLIP_TIME_FROM")
    time_to = require_env("CLIP_TIME_TO")

    max_cloud_env = os.environ.get("CLIP_MAX_CLOUD")
    search_limit = int(os.environ.get("CLIP_SEARCH_LIMIT", "5"))
    search_limit = max(1, min(search_limit, 10))

    resolution = int(os.environ.get("CLIP_RESOLUTION", "10"))
    max_tile_px = int(os.environ.get("CLIP_TILE_MAX_PX", "2500"))
    nodata_value = float(os.environ.get("CLIP_NODATA", "0"))

    config = SHConfig()
    config.sh_client_id = client_id
    config.sh_client_secret = client_secret
    config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
    config.sh_base_url = "https://sh.dataspace.copernicus.eu"

    geometry = Geometry(geometry_dict, crs=CRS.WGS84)
    bbox = geometry.bbox

    catalog = SentinelHubCatalog(config=config)

    query = {}
    if scene_id:
        query["id"] = {"eq": scene_id}
    if max_cloud_env is not None:
        try:
            query["eo:cloud_cover"] = {"lt": float(max_cloud_env)}
        except ValueError:
            pass
    if not query:
        query = None

    log("Searching Sentinel Hub catalogue for matching scenes...")
    search_iter = catalog.search(
        collection_enum,
        geometry=geometry,
        time=(time_from, time_to),
        limit=search_limit,
        query=query,
    )
    items = list(search_iter)

    if not items:
        raise SystemExit("No Sentinel-2 scenes matched the provided geometry and timeframe.")

    items.sort(key=lambda item: item.get("properties", {}).get("datetime"), reverse=True)

    chosen = None
    if scene_id:
        chosen = next((item for item in items if item.get("id") == scene_id), None)
    if chosen is None:
        chosen = items[0]

    chosen_time = chosen.get("properties", {}).get("datetime")
    log(f"Selected scene {chosen.get('id')} acquired at {chosen_time}.")

    bbox_dimensions = bbox_to_dimensions(bbox, resolution=resolution)
    w_all, h_all = bbox_dimensions
    n_cols = max(1, int(np.ceil(w_all / max_tile_px)))
    n_rows = max(1, int(np.ceil(h_all / max_tile_px)))

    log(f"Scene will be split into {n_cols} × {n_rows} tiles (max {max_tile_px}px each).")

    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(n_cols, n_rows))
    tile_bboxes = splitter.get_bbox_list()

    tile_paths = []
    evalscript = """
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
    return [s.B01,s.B02,s.B03,s.B04,s.B05,s.B06,s.B07,s.B08,s.B8A,s.B09,s.B11,s.B12];
}
"""

    data_filter = {
        "timeRange": {"from": time_from, "to": time_to},
        "mosaickingOrder": "mostRecent",
    }
    if scene_id:
        data_filter["id"] = scene_id

    processing = {
        "upsampling": "BILINEAR",
        "downsampling": "BILINEAR",
        "harmonizeValues": True,
    }

    for index, tile_bbox in enumerate(tile_bboxes, start=1):
        width, height = bbox_to_dimensions(tile_bbox, resolution=resolution)
        width = min(width, max_tile_px)
        height = min(height, max_tile_px)

        log(f"Requesting tile {index}/{len(tile_bboxes)} with size {width}×{height} pixels...")

        request = SentinelHubRequest(
            evalscript=evalscript,
            input_data=[{
                "type": collection_type,
                "dataFilter": data_filter,
                "processing": processing,
            }],
            responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
            bbox=tile_bbox,
            size=(width, height),
            data_folder=tiles_dir,
            config=config,
        )

        request.get_data(save_data=True, show_progress=False)

        for filename in request.get_filename_list():
            tile_path = os.path.join(tiles_dir, filename)
            tile_paths.append(tile_path)

    if not tile_paths:
        raise SystemExit("Tile download did not produce any files.")

    log(f"Downloaded {len(tile_paths)} tile(s). Merging...")

    src_datasets = [rasterio.open(path) for path in tile_paths]
    try:
        mosaic, out_transform = merge(src_datasets)
        base_meta = src_datasets[0].meta.copy()
    finally:
        for dataset in src_datasets:
            dataset.close()

    meta = {
        "driver": "GTiff",
        "height": mosaic.shape[1],
        "width": mosaic.shape[2],
        "count": mosaic.shape[0],
        "dtype": mosaic.dtype,
        "crs": base_meta["crs"],
        "transform": out_transform,
        "compress": "lzw",
        "nodata": nodata_value,
    }

    with rasterio.open(merged_path, "w", **meta) as dataset:
        dataset.write(mosaic)

    log(f"Merged intermediate raster saved to {merged_path}.")

    with rasterio.open(merged_path) as dataset:
        masked_image, masked_transform = mask.mask(
            dataset,
            [geometry_dict],
            crop=True,
            nodata=nodata_value,
        )
        masked_meta = dataset.meta.copy()
        masked_meta.update({
            "driver": "GTiff",
            "height": masked_image.shape[1],
            "width": masked_image.shape[2],
            "transform": masked_transform,
            "compress": "lzw",
            "nodata": nodata_value,
        })

    with rasterio.open(output_path, "w", **masked_meta) as dataset:
        dataset.write(masked_image)

    log(f"Masked raster saved to {output_path}.")


if __name__ == "__main__":
    try:
        main()
    except SystemExit as exc:
        log(str(exc))
        raise
    except Exception as exc:  # pragma: no cover - catch-all for logging
        log(f"Processing failed: {exc}")
        raise
