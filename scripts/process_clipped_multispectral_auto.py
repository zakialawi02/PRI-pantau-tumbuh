import json
import os
import sys
from datetime import timedelta
from pathlib import Path

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
    SentinelHubCatalog,
    SentinelHubRequest,
    bbox_to_dimensions,
)
from sentinelhub.areas import BBoxSplitter


def getenv(name: str, *, required: bool = False, default=None):
    value = os.getenv(name, default)
    if required and (value is None or value == ""):
        raise SystemExit(f"Missing required environment variable: {name}")
    return value


def ensure_directory(path: Path) -> Path:
    path.mkdir(parents=True, exist_ok=True)
    return path


def normalise_geometry(payload: dict) -> Geometry:
    payload_type = payload.get("type")

    if payload_type == "FeatureCollection":
        features = payload.get("features") or []
        if not features:
            raise SystemExit("FeatureCollection payload must contain at least one feature")
        feature = features[0]
    elif payload_type == "Feature":
        feature = payload
    else:
        feature = {
            "type": "Feature",
            "geometry": payload,
            "properties": {},
        }

    geometry = feature.get("geometry")
    if not geometry:
        raise SystemExit("GeoJSON payload is missing geometry information")

    return Geometry(geometry, crs=CRS.WGS84)


def resolve_collection(name: str) -> DataCollection:
    candidate = name or "SENTINEL2_L2A"
    if hasattr(DataCollection, candidate):
        return getattr(DataCollection, candidate)
    return DataCollection.SENTINEL2_L2A


def main() -> None:
    client_id = getenv("COPERNICUS_CLIENT_ID", required=True)
    client_secret = getenv("COPERNICUS_CLIENT_SECRET", required=True)
    start_date = getenv("S2_START_DATE", required=True)
    max_records = int(getenv("S2_MAX_RECORDS", default="10"))
    resolution = int(getenv("S2_RESOLUTION", default="10"))
    max_tile_pixels = int(getenv("S2_MAX_TILE_PIXELS", default="2500"))
    nodata_value = float(getenv("S2_NODATA_VALUE", default="0"))
    collection_name = getenv("S2_DATA_COLLECTION", default="SENTINEL2_L2A")

    geojson_raw = getenv("S2_GEOJSON", required=True)
    try:
        geojson_payload = json.loads(geojson_raw)
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Invalid GeoJSON payload: {exc}") from exc

    output_path = Path(getenv("S2_OUTPUT_PATH", required=True)).resolve()
    tiles_dir = ensure_directory(Path(getenv("S2_TILES_DIR", default=str(output_path.parent / "tiles"))).resolve())
    merged_path = Path(getenv("S2_MERGED_PATH", default=str(tiles_dir / "merged_fixed.tif"))).resolve()

    # Clean previous intermediate outputs
    if merged_path.exists():
        merged_path.unlink()
    if output_path.exists():
        output_path.unlink()
    for child in tiles_dir.iterdir():
        if child.is_file():
            child.unlink()

    config = SHConfig()
    config.sh_client_id = client_id
    config.sh_client_secret = client_secret
    config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
    config.sh_base_url = "https://sh.dataspace.copernicus.eu"

    geometry = normalise_geometry(geojson_payload)
    bbox = geometry.bbox

    data_collection = resolve_collection(collection_name)

    catalog = SentinelHubCatalog(config=config)
    search_iter = catalog.search(
        data_collection,
        geometry=geometry,
        time=(start_date, None),
        limit=max_records,
    )

    items = list(search_iter)
    if not items:
        raise SystemExit("No Sentinel-2 scenes found for the provided area and start date.")

    items.sort(key=lambda item: item["properties"]["datetime"], reverse=True)
    chosen = items[0]
    chosen_time = chosen["properties"]["datetime"]
    print(f"Selected scene: {chosen.get('id')} @ {chosen_time}")

    dt = dateparser.isoparse(chosen_time)
    t_from = (dt - timedelta(hours=1)).isoformat()
    t_to = (dt + timedelta(hours=1)).isoformat()

    width, height = bbox_to_dimensions(bbox, resolution=resolution)
    cols = max(int(np.ceil(width / max_tile_pixels)), 1)
    rows = max(int(np.ceil(height / max_tile_pixels)), 1)
    print(f"Splitting AOI into {cols} x {rows} tiles")

    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(cols, rows))
    tile_bboxes = splitter.get_bbox_list()

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

    tile_paths = []
    for idx, tile_bbox in enumerate(tile_bboxes, start=1):
        tile_width, tile_height = bbox_to_dimensions(tile_bbox, resolution=resolution)
        tile_width = min(tile_width, max_tile_pixels)
        tile_height = min(tile_height, max_tile_pixels)
        print(f"Requesting tile {idx}/{len(tile_bboxes)} sized {tile_width} x {tile_height}")

        request = SentinelHubRequest(
            evalscript=evalscript_all_bands,
            input_data=[
                SentinelHubRequest.input_data(
                    data_collection=data_collection,
                    time_interval=(t_from, t_to),
                )
            ],
            responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
            bbox=tile_bbox,
            size=(tile_width, tile_height),
            data_folder=str(tiles_dir),
            config=config,
        )

        request.get_data(save_data=True, show_progress=False)
        for saved in request.get_filename_list():
            tile_paths.append(str(Path(tiles_dir) / saved))

    if not tile_paths:
        raise SystemExit("Tile requests completed but no data was saved.")

    sources = [rasterio.open(path) for path in tile_paths]
    mosaic, out_transform = merge(sources)

    base_meta = sources[0].meta.copy()
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
        "nodata": nodata_value,
    }

    with rasterio.open(merged_path, "w", **meta) as dst:
        dst.write(mosaic)
    print(f"Merged raster saved to {merged_path}")

    for src in sources:
        src.close()

    with rasterio.open(merged_path) as src:
        out_image, out_transform = mask.mask(src, [geometry.geometry], crop=True, nodata=nodata_value)
        out_meta = src.meta.copy()
        out_meta.update({
            "driver": "GTiff",
            "height": out_image.shape[1],
            "width": out_image.shape[2],
            "transform": out_transform,
            "compress": "lzw",
            "nodata": nodata_value,
        })

    ensure_directory(output_path.parent)
    with rasterio.open(output_path, "w", **out_meta) as dst:
        dst.write(out_image)

    print(f"Clipped raster saved to {output_path}")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        print(f"Error during Sentinel-2 clipping: {exc}", file=sys.stderr)
        raise
