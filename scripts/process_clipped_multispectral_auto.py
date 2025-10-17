#!/usr/bin/env python3
"""Process Sentinel-2 imagery by clipping to an AOI polygon.

This utility searches the Copernicus Data Space catalogue for Sentinel-2
collections intersecting the provided geometry and downloads the most
recent matching scene. The imagery is mosaicked, clipped to the AOI, and a
single multiband GeoTIFF is produced.

Configuration is provided via command line arguments or, if omitted, via
environment variables. The Laravel application injects the following env
variables when invoking the script:

    COPERNICUS_CLIENT_ID
    COPERNICUS_CLIENT_SECRET
    S2_CLIP_GEOMETRY            (GeoJSON string for the AOI feature)
    S2_CLIP_OUTPUT              (Absolute path to the desired output GeoTIFF)
    S2_CLIP_TILE_DIR            (Working directory for intermediate tiles)
    S2_CLIP_COLLECTION          (Product type, e.g. S2MSI2A or S2MSI1C)
    S2_CLIP_PRODUCT_ID          (Preferred Sentinel product identifier)
    S2_CLIP_SCENE_ID            (Optional feature identifier)
    S2_CLIP_SCENE_TIME          (ISO8601 acquisition datetime)
    S2_CLIP_TIME_BUFFER_MINUTES (Minutes before/after the acquisition time)
    S2_CLIP_RESOLUTION          (Target ground sampling distance in metres)
    S2_CLIP_LIMIT               (Maximum catalogue results to inspect)
    S2_CLIP_MAX_TILE_SIZE       (Maximum tile dimension in pixels)
"""

from __future__ import annotations

import argparse
import json
import math
import os
import sys
from dataclasses import dataclass
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

import rasterio
from rasterio import mask
from rasterio.merge import merge
from sentinelhub import (
    BBox,
    BBoxSplitter,
    CRS,
    DataCollection,
    Geometry,
    MimeType,
    SentinelHubCatalog,
    SentinelHubRequest,
    SHConfig,
    bbox_to_dimensions,
)


@dataclass
class ClipConfig:
    geometry: Dict[str, Any]
    output_path: Path
    tile_dir: Path
    data_collection: DataCollection
    product_id: Optional[str]
    scene_id: Optional[str]
    scene_time: Optional[datetime]
    time_buffer: timedelta
    resolution: int
    limit: int
    max_tile_size: int


def _load_env(name: str, default: Optional[str] = None) -> Optional[str]:
    value = os.environ.get(name)
    if value is None or value == "":
        return default
    return value


def _resolve_parameter(value: Optional[str], env_key: str, *, required: bool = False) -> Optional[str]:
    resolved = value if value not in (None, "") else _load_env(env_key)
    if required and (resolved is None or resolved == ""):
        raise ValueError(f"Missing required parameter: {env_key}")
    return resolved


def _parse_geometry(value: str) -> Dict[str, Any]:
    path = Path(value)
    if path.exists() and path.is_file():
        with path.open("r", encoding="utf-8") as handle:
            return json.load(handle)
    try:
        return json.loads(value)
    except json.JSONDecodeError as exc:  # pragma: no cover - explicit error path
        raise ValueError("Invalid GeoJSON geometry provided") from exc


def _resolve_geometry(value: Optional[str]) -> Dict[str, Any]:
    resolved = _resolve_parameter(value, "S2_CLIP_GEOMETRY", required=True)
    assert resolved is not None  # for mypy/linters
    geometry = _parse_geometry(resolved)
    if geometry.get("type") == "Feature":
        geom = geometry.get("geometry")
        if not geom:
            raise ValueError("GeoJSON feature is missing the geometry member")
        return geom
    return geometry


def _resolve_collection(value: Optional[str]) -> DataCollection:
    resolved = _resolve_parameter(value, "S2_CLIP_COLLECTION") or "S2MSI2A"
    normalized = resolved.upper()
    mapping = {
        "S2MSI2A": DataCollection.SENTINEL2_L2A,
        "S2MSI1C": DataCollection.SENTINEL2_L1C,
    }
    if normalized not in mapping:
        raise ValueError(f"Unsupported Sentinel-2 collection: {resolved}")
    return mapping[normalized]


def _resolve_time_buffer(value: Optional[str]) -> timedelta:
    raw = _resolve_parameter(value, "S2_CLIP_TIME_BUFFER_MINUTES") or "60"
    try:
        minutes = int(raw)
    except ValueError as exc:
        raise ValueError("Time buffer must be an integer representing minutes") from exc
    minutes = max(1, minutes)
    return timedelta(minutes=minutes)


def _resolve_int(value: Optional[str], env_key: str, default: int, *, minimum: int = 1) -> int:
    raw = _resolve_parameter(value, env_key) or str(default)
    try:
        parsed = int(raw)
    except ValueError as exc:
        raise ValueError(f"{env_key} must be an integer") from exc
    return max(minimum, parsed)


def _resolve_scene_time(value: Optional[str]) -> Optional[datetime]:
    resolved = _resolve_parameter(value, "S2_CLIP_SCENE_TIME")
    if not resolved:
        return None
    try:
        return datetime.fromisoformat(resolved.replace("Z", "+00:00"))
    except ValueError as exc:
        raise ValueError("Scene time must be a valid ISO-8601 datetime") from exc


def _ensure_directory(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)


def _clean_directory(path: Path) -> None:
    if path.exists() and path.is_dir():
        for child in path.iterdir():
            if child.is_file():
                child.unlink(missing_ok=True)
            elif child.is_dir():
                _clean_directory(child)
                try:
                    child.rmdir()
                except OSError:
                    pass


def _prepare_config(args: argparse.Namespace) -> ClipConfig:
    geometry = _resolve_geometry(args.geometry)
    output_path = Path(_resolve_parameter(args.output, "S2_CLIP_OUTPUT", required=True))
    tile_dir = Path(_resolve_parameter(args.tiles_dir, "S2_CLIP_TILE_DIR", required=True))
    data_collection = _resolve_collection(args.collection)
    product_id = _resolve_parameter(args.product_id, "S2_CLIP_PRODUCT_ID")
    scene_id = _resolve_parameter(args.scene_id, "S2_CLIP_SCENE_ID")
    scene_time = _resolve_scene_time(args.scene_time)
    time_buffer = _resolve_time_buffer(args.time_buffer)
    resolution = _resolve_int(args.resolution, "S2_CLIP_RESOLUTION", default=10)
    limit = _resolve_int(args.limit, "S2_CLIP_LIMIT", default=10)
    max_tile_size = _resolve_int(args.max_tile_size, "S2_CLIP_MAX_TILE_SIZE", default=2500)

    return ClipConfig(
        geometry=geometry,
        output_path=output_path,
        tile_dir=tile_dir,
        data_collection=data_collection,
        product_id=product_id,
        scene_id=scene_id,
        scene_time=scene_time,
        time_buffer=time_buffer,
        resolution=resolution,
        limit=limit,
        max_tile_size=max_tile_size,
    )


def _build_sentinel_config() -> SHConfig:
    client_id = _load_env("COPERNICUS_CLIENT_ID")
    client_secret = _load_env("COPERNICUS_CLIENT_SECRET")
    if not client_id or not client_secret:
        raise RuntimeError("COPERNICUS credentials are not configured")

    config = SHConfig()
    config.sh_client_id = client_id
    config.sh_client_secret = client_secret
    config.sh_token_url = "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
    config.sh_base_url = "https://sh.dataspace.copernicus.eu"
    return config


def _calculate_grid(bbox: BBox, resolution: int, max_tile_size: int) -> Tuple[int, int]:
    width, height = bbox_to_dimensions(bbox, resolution=resolution)
    if width <= 0 or height <= 0:
        raise RuntimeError("Computed bounding box dimensions are invalid")

    cols = max(1, math.ceil(width / max_tile_size)) if width > max_tile_size else 1
    rows = max(1, math.ceil(height / max_tile_size)) if height > max_tile_size else 1
    return cols, rows


def _list_tile_paths(request: SentinelHubRequest, base_dir: Path) -> List[Path]:
    filenames = request.get_filename_list()
    paths = []
    for filename in filenames:
        candidate = base_dir / filename
        if candidate.exists():
            paths.append(candidate)
    return paths


def _load_catalog_items(cfg: ClipConfig, config: SHConfig) -> List[Dict[str, Any]]:
    geometry = Geometry(cfg.geometry, crs=CRS.WGS84)
    bbox = geometry.bbox

    time_from: Optional[str]
    time_to: Optional[str]
    if cfg.scene_time is not None:
        start = cfg.scene_time - cfg.time_buffer
        end = cfg.scene_time + cfg.time_buffer
        time_from = start.isoformat()
        time_to = end.isoformat()
    else:
        now = datetime.utcnow()
        start = now - timedelta(days=14)
        time_from = start.isoformat()
        time_to = now.isoformat()

    catalog = SentinelHubCatalog(config=config)
    search_iterator = catalog.search(
        cfg.data_collection,
        geometry=geometry,
        time=(time_from, time_to),
        limit=cfg.limit,
    )
    items = list(search_iterator)

    if cfg.product_id:
        items = [item for item in items if cfg.product_id in (item.get("properties", {}).get("productIdentifier") or "")]
    if cfg.scene_id:
        items = [item for item in items if item.get("id") == cfg.scene_id]

    if not items:
        raise RuntimeError("No Sentinel-2 scenes found that match the provided parameters")

    items.sort(key=lambda item: item.get("properties", {}).get("datetime"), reverse=True)
    selected = items[0]

    props = selected.get("properties", {})
    print("Selected scene:")
    print(f"  ID: {selected.get('id')}")
    print(f"  Product: {props.get('productIdentifier')}")
    print(f"  Datetime: {props.get('datetime')}")
    print(f"  Cloud cover: {props.get('eo:cloud_cover') or props.get('cloudCover')}%")

    return [selected]


def _download_tiles(cfg: ClipConfig, selected_items: Iterable[Dict[str, Any]], config: SHConfig) -> List[Path]:
    geometry = Geometry(cfg.geometry, crs=CRS.WGS84)
    bbox = geometry.bbox

    cols, rows = _calculate_grid(bbox, cfg.resolution, cfg.max_tile_size)
    splitter = BBoxSplitter([bbox.geometry], CRS.WGS84, split_shape=(cols, rows))
    tile_bboxes = splitter.get_bbox_list()

    print(f"Grid split: {cols} × {rows} (total {len(tile_bboxes)} tiles)")

    cfg.tile_dir.mkdir(parents=True, exist_ok=True)
    tile_paths: List[Path] = []

    # Use the acquisition datetime from the selected item for the time interval
    selected = next(iter(selected_items))
    props = selected.get("properties", {})
    scene_datetime = props.get("datetime")
    if not scene_datetime:
        raise RuntimeError("Selected scene is missing the acquisition datetime")
    centre_time = datetime.fromisoformat(scene_datetime.replace("Z", "+00:00"))
    time_from = (centre_time - cfg.time_buffer).isoformat()
    time_to = (centre_time + cfg.time_buffer).isoformat()

    evalscript = """
//VERSION=3
function setup() {
    return {
        input: [{
            bands: ["B01","B02","B03","B04","B05","B06","B07","B08","B8A","B09","B11","B12"],
            units: "DN"
        }],
        output: {
            bands: 12,
            sampleType: "INT16"
        }
    };
}
function evaluatePixel(sample) {
    return [sample.B01, sample.B02, sample.B03, sample.B04, sample.B05, sample.B06,
            sample.B07, sample.B08, sample.B8A, sample.B09, sample.B11, sample.B12];
}
"""

    for index, tile_bbox in enumerate(tile_bboxes, start=1):
        width, height = bbox_to_dimensions(tile_bbox, resolution=cfg.resolution)
        width = min(width, cfg.max_tile_size)
        height = min(height, cfg.max_tile_size)
        print(f"Requesting tile {index}/{len(tile_bboxes)} with size {width}×{height} pixels")

        request = SentinelHubRequest(
            evalscript=evalscript,
            input_data=[
                SentinelHubRequest.input_data(
                    data_collection=cfg.data_collection,
                    time_interval=(time_from, time_to),
                    mosaicking_order="mostRecent",
                )
            ],
            responses=[SentinelHubRequest.output_response("default", MimeType.TIFF)],
            bbox=tile_bbox,
            size=(width, height),
            data_folder=str(cfg.tile_dir),
            config=config,
        )

        request.get_data(save_data=True, show_progress=False)
        tile_paths.extend(_list_tile_paths(request, cfg.tile_dir))

    if not tile_paths:
        raise RuntimeError("No raster tiles were downloaded for the selected scene")

    return tile_paths


def _merge_and_clip(cfg: ClipConfig, tile_paths: Iterable[Path]) -> None:
    src_files = [rasterio.open(path) for path in tile_paths]
    try:
        mosaic, transform = merge(src_files)
        metadata = src_files[0].meta.copy()
    finally:
        for src in src_files:
            src.close()

    metadata.update(
        {
            "driver": "GTiff",
            "height": mosaic.shape[1],
            "width": mosaic.shape[2],
            "count": mosaic.shape[0],
            "dtype": mosaic.dtype,
            "transform": transform,
            "compress": "lzw",
            "nodata": 0,
        }
    )

    temp_path = cfg.output_path.with_suffix(".tmp.tif")

    with rasterio.open(temp_path, "w", **metadata) as dst:
        dst.write(mosaic)

    geom = [cfg.geometry]
    with rasterio.open(temp_path) as src:
        out_image, out_transform = mask.mask(src, geom, crop=True, nodata=0)
        out_meta = src.meta.copy()
        out_meta.update(
            {
                "height": out_image.shape[1],
                "width": out_image.shape[2],
                "transform": out_transform,
                "compress": "lzw",
                "nodata": 0,
            }
        )

    _ensure_directory(cfg.output_path.parent)
    with rasterio.open(cfg.output_path, "w", **out_meta) as dst:
        dst.write(out_image)

    temp_path.unlink(missing_ok=True)


def parse_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Clip Sentinel-2 scenes to a GeoJSON AOI")
    parser.add_argument("--geometry", help="GeoJSON feature/geometry string or file path")
    parser.add_argument("--output", help="Absolute output path for the clipped GeoTIFF")
    parser.add_argument("--tiles-dir", help="Directory for temporary Sentinel tiles")
    parser.add_argument("--collection", help="Sentinel product type (S2MSI2A or S2MSI1C)")
    parser.add_argument("--product-id", help="Preferred product identifier")
    parser.add_argument("--scene-id", help="Preferred catalogue scene identifier")
    parser.add_argument("--scene-time", help="Acquisition datetime in ISO-8601 format")
    parser.add_argument("--time-buffer", help="Minutes before/after the acquisition time")
    parser.add_argument("--resolution", help="Spatial resolution in metres", default=None)
    parser.add_argument("--limit", help="Maximum catalogue items to inspect", default=None)
    parser.add_argument("--max-tile-size", help="Maximum tile dimension in pixels", default=None)
    return parser.parse_args(argv)


def main(argv: Optional[List[str]] = None) -> int:
    try:
        args = parse_args(argv)
        clip_config = _prepare_config(args)
        sentinel_config = _build_sentinel_config()

        _ensure_directory(clip_config.tile_dir)
        _clean_directory(clip_config.tile_dir)

        items = _load_catalog_items(clip_config, sentinel_config)
        tile_paths = _download_tiles(clip_config, items, sentinel_config)
        _merge_and_clip(clip_config, tile_paths)

        print(f"Clipped imagery written to: {clip_config.output_path}")
        return 0
    except Exception as exc:  # pragma: no cover - runtime error reporting
        print(f"Error: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
