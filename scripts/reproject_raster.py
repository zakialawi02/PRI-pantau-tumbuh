#!/usr/bin/env python3
"""Reproject raster datasets to a target CRS (default EPSG:4326).

Outputs JSON containing CRS metadata and whether reprojection was performed.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any, Dict

import rasterio
from rasterio.enums import Resampling
from rasterio.warp import calculate_default_transform, reproject


class RasterProcessingError(RuntimeError):
    """Raised when raster processing fails."""


def normalise_crs(crs) -> str | None:
    if not crs:
        return None
    try:
        string = crs.to_string()  # type: ignore[attr-defined]
    except Exception:  # pragma: no cover - defensive branch
        string = str(crs)
    if not string:
        return None
    string = string.strip()
    if not string:
        return None
    upper = string.upper()
    if upper.startswith("EPSG"):
        digits = "".join(ch for ch in upper if ch.isdigit())
        if digits:
            return f"EPSG:{digits}"
    if upper.isdigit():
        return f"EPSG:{upper}"
    return upper


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Reproject raster datasets")
    parser.add_argument("--input", required=True, dest="input_path", help="Path to the source raster")
    parser.add_argument(
        "--output",
        dest="output_path",
        help="Path to write the reprojected raster. Defaults to adding '_epsg4326' suffix.",
    )
    parser.add_argument(
        "--target",
        dest="target_crs",
        default="EPSG:4326",
        help="Target CRS expressed as an EPSG code (default: EPSG:4326)",
    )
    parser.add_argument(
        "--overwrite",
        action="store_true",
        help="Allow overwriting an existing output dataset when reprojection is required.",
    )
    return parser.parse_args()


def derive_output_path(input_path: Path, output_arg: str | None, target_label: str) -> Path:
    if output_arg:
        return Path(output_arg)

    suffix = input_path.suffix or ".tif"
    stem = input_path.stem
    return input_path.with_name(f"{stem}_{target_label.lower()}" + suffix)


def reproject_dataset(input_path: Path, output_path: Path, target_crs: str, overwrite: bool) -> Dict[str, Any]:
    if not input_path.exists():
        raise RasterProcessingError(f"Source file not found: {input_path}")

    target_label = target_crs.upper().replace(":", "")
    if output_path == input_path:
        output_path = input_path.with_name(f"{input_path.stem}_{target_label}{input_path.suffix or '.tif'}")

    if output_path.exists() and not overwrite and output_path != input_path:
        raise RasterProcessingError(
            f"Output file already exists and overwrite not allowed: {output_path}"
        )

    with rasterio.Env():
        with rasterio.open(input_path) as src:
            input_crs = normalise_crs(src.crs)
            if not input_crs:
                raise RasterProcessingError("Unable to determine CRS of source raster")

            target_crs_norm = normalise_crs(target_crs) or "EPSG:4326"
            native_bounds = [src.bounds.left, src.bounds.bottom, src.bounds.right, src.bounds.top]

            # If CRS already matches target, no reprojection needed.
            if input_crs.upper() == target_crs_norm.upper():
                return {
                    "input_path": str(input_path),
                    "output_path": str(input_path),
                    "input_crs": input_crs,
                    "output_crs": input_crs,
                    "reprojected": False,
                    "bounds": {
                        "native": native_bounds,
                        "target": native_bounds,
                    },
                }

            transform, width, height = calculate_default_transform(
                src.crs, target_crs_norm, src.width, src.height, *src.bounds
            )
            kwargs = src.meta.copy()
            kwargs.update(
                {
                    "crs": target_crs_norm,
                    "transform": transform,
                    "width": width,
                    "height": height,
                }
            )

            if src.nodata is not None:
                kwargs["nodata"] = src.nodata

            output_path.parent.mkdir(parents=True, exist_ok=True)
            with rasterio.open(output_path, "w", **kwargs) as dst:
                for band in range(1, src.count + 1):
                    reproject(
                        source=rasterio.band(src, band),
                        destination=rasterio.band(dst, band),
                        src_transform=src.transform,
                        src_crs=src.crs,
                        dst_transform=transform,
                        dst_crs=target_crs_norm,
                        resampling=Resampling.nearest,
                    )

    with rasterio.open(output_path) as dst:
        target_bounds = [dst.bounds.left, dst.bounds.bottom, dst.bounds.right, dst.bounds.top]
        output_crs = normalise_crs(dst.crs) or target_crs_norm

    return {
        "input_path": str(input_path),
        "output_path": str(output_path),
        "input_crs": input_crs,
        "output_crs": output_crs,
        "reprojected": True,
        "bounds": {
            "native": native_bounds,
            "target": target_bounds,
        },
    }


def main() -> int:
    args = parse_args()
    input_path = Path(args.input_path).expanduser().resolve()
    target_crs = args.target_crs or "EPSG:4326"

    try:
        output_path = derive_output_path(input_path, args.output_path, target_crs.replace(":", ""))
        result = reproject_dataset(input_path, output_path, target_crs, args.overwrite)
    except RasterProcessingError as exc:
        print(json.dumps({"error": str(exc)}))
        return 2
    except Exception as exc:  # pragma: no cover - unexpected error
        print(json.dumps({"error": f"Unexpected failure: {exc}"}))
        return 3

    print(json.dumps(result))
    return 0


if __name__ == "__main__":
    sys.exit(main())
