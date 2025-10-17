import json
import os
import sys
from datetime import datetime, timedelta

import numpy as np
import rasterio
from dateutil import parser as dateparser
from rasterio import mask
from rasterio.merge import merge
from sentinelhub import (
    CRS,
    SHConfig,
    DataCollection,
    Geometry,
    MimeType,
    MosaickingOrder,
    SentinelHubCatalog,
    SentinelHubRequest,
    bbox_to_dimensions,
)
from sentinelhub.areas import BBoxSplitter


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(1)


def getenv(name: str, default=None):
    value = os.getenv(name)
    if value is None:
        return default
    value = value.strip()
    return value if value else default


def ensure_directory(path: str) -> None:
    if not path:
        return
    os.makedirs(path, exist_ok=True)


def load_geometry() -> Geometry:
    payload = getenv("CLIP_GEOMETRY")
    if not payload:
        fail("CLIP_GEOMETRY is not provided.")

    try:
        data = json.loads(payload)
    except json.JSONDecodeError as exc:
        fail(f"Failed to decode CLIP_GEOMETRY: {exc}")

    if isinstance(data, dict) and data.get("type") == "Feature":
        geom_dict = data.get("geometry")
    else:
        geom_dict = data

    if not isinstance(geom_dict, dict) or not geom_dict.get("type"):
        fail("Geometry payload is invalid.")

    return Geometry(geom_dict, crs=CRS.WGS84)


def resolve_time_interval():
    start_raw = getenv("CLIP_START_DATE")
    if start_raw:
        try:
            start_dt = dateparser.isoparse(start_raw)
        except (ValueError, TypeError) as exc:
            fail(f"Unable to parse CLIP_START_DATE: {exc}")
    else:
        start_dt = datetime.utcnow() - timedelta(days=30)

    if start_dt.tzinfo is None:
        start_iso = start_dt.isoformat() + "Z"
    else:
        start_iso = start_dt.astimezone(tz=None).isoformat()

    end_dt = datetime.utcnow()
    end_iso = end_dt.isoformat() + "Z"
    return start_iso, end_iso


def select_scene(catalog: SentinelHubCatalog, geometry: Geometry, start_iso: str, end_iso: str, limit: int):
    search_iter = catalog.search(
        DataCollection.SENTINEL2_L2A,
        geometry=geometry,
        time=(start_iso, end_iso),
        limit=limit,
    )

    items = list(search_iter)
    if not items:
        fail("No Sentinel-2 scenes found for the provided area and date range.")

    items.sort(key=lambda it: it.get("properties", {}).get("datetime"), reverse=True)
    selected = items[0]
    properties = selected.get("properties", {})
    if "datetime" not in properties:
        fail("Selected scene is missing acquisition time information.")

    return selected


def build_request(tile_bbox, evalscript, config, time_from, time_to, resolution, tiles_dir):
    width, height = bbox_to_dimensions(tile_bbox, resolution=resolution)
    max_dim = 2500
    width = min(width, max_dim)
    height = min(height, max_dim)

    return SentinelHubRequest(
        evalscript=evalscript,
        input_data=[
            SentinelHubRequest.input_data(
                data_collection=DataCollection.SENTINEL2_L2A,
                time_interval=(time_from, time_to),
                mosaicking_order=MosaickingOrder.MOST_RECENT,
            )
        ],
        responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
        bbox=tile_bbox,
        size=(width, height),
        data_folder=tiles_dir,
        config=config,
    )


def main() -> None:
    client_id = getenv("COPERNICUS_CLIENT_ID")
    client_secret = getenv("COPERNICUS_CLIENT_SECRET")
    if not client_id or not client_secret:
        fail("COPERNICUS_CLIENT_ID and COPERNICUS_CLIENT_SECRET must be set.")

    tiles_dir = getenv("CLIP_TILES_DIR", os.path.join(os.getcwd(), "tiles"))
    output_path = getenv("CLIP_OUTPUT_PATH", os.path.join(os.getcwd(), "sentinel_clip.tif"))
    ensure_directory(tiles_dir)
    ensure_directory(os.path.dirname(output_path) or ".")

    try:
        limit = int(getenv("CLIP_LIMIT", "10") or 10)
    except ValueError:
        limit = 10
    limit = max(1, min(limit, 50))

    try:
        resolution = int(getenv("CLIP_RESOLUTION", "10") or 10)
    except ValueError:
        resolution = 10
    resolution = max(10, resolution)

    nodata_value = int(getenv("CLIP_NODATA", "0") or 0)

    geometry = load_geometry()
    bbox = geometry.bbox
    start_iso, end_iso = resolve_time_interval()

    config = SHConfig()
    config.sh_client_id = client_id
    config.sh_client_secret = client_secret
    config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
    config.sh_base_url = "https://sh.dataspace.copernicus.eu"

    catalog = SentinelHubCatalog(config=config)
    selected = select_scene(catalog, geometry, start_iso, end_iso, limit)

    selected_time = selected["properties"]["datetime"]
    scene_id = selected.get("id")
    print(f"Selected scene: {scene_id} at {selected_time}")

    acquisition_dt = dateparser.isoparse(selected_time)
    time_from = (acquisition_dt - timedelta(hours=1)).isoformat()
    time_to = (acquisition_dt + timedelta(hours=1)).isoformat()

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
function evaluatePixel(sample) {
    return [sample.B01,sample.B02,sample.B03,sample.B04,sample.B05,sample.B06,sample.B07,sample.B08,
            sample.B8A,sample.B09,sample.B11,sample.B12];
}
"""

    width_all, height_all = bbox_to_dimensions(bbox, resolution=resolution)
    max_dim = 2500
    cols = int(np.ceil(width_all / max_dim)) if width_all > max_dim else 1
    rows = int(np.ceil(height_all / max_dim)) if height_all > max_dim else 1

    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(rows, cols))
    tile_bboxes = splitter.get_bbox_list()

    tile_paths = []
    for index, tile_bbox in enumerate(tile_bboxes, start=1):
        print(f"Requesting tile {index}/{len(tile_bboxes)}")
        request = build_request(tile_bbox, evalscript, config, time_from, time_to, resolution, tiles_dir)
        request.get_data(save_data=True)
        for filename in request.get_filename_list():
            tile_paths.append(os.path.join(tiles_dir, filename))

    if not tile_paths:
        fail("No imagery tiles were downloaded for the requested scene.")

    src_files = [rasterio.open(path) for path in tile_paths]
    try:
        mosaic, out_transform = merge(src_files)
        meta = src_files[0].meta.copy()
    finally:
        for src in src_files:
            src.close()

    meta.update(
        driver="GTiff",
        height=mosaic.shape[1],
        width=mosaic.shape[2],
        count=mosaic.shape[0],
        dtype=mosaic.dtype,
        crs=meta.get("crs"),
        transform=out_transform,
        compress="lzw",
        photometric="MINISBLACK",
        nodata=nodata_value,
    )

    temp_merged_path = os.path.join(tiles_dir, "merged_full.tif")
    with rasterio.open(temp_merged_path, "w", **meta) as dst:
        dst.write(mosaic)

    with rasterio.open(temp_merged_path) as src:
        masked_image, masked_transform = mask.mask(src, [geometry.geojson], crop=True, nodata=nodata_value)
        masked_meta = src.meta.copy()
        masked_meta.update(
            driver="GTiff",
            height=masked_image.shape[1],
            width=masked_image.shape[2],
            transform=masked_transform,
            compress="lzw",
            nodata=nodata_value,
        )

    with rasterio.open(output_path, "w", **masked_meta) as dst:
        dst.write(masked_image)

    print(f"Sentinel-2 clip saved to {output_path}")


if __name__ == "__main__":
    main()
