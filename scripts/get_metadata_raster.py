"""Utility to inspect metadata from GeoTIFF/COG raster files.

This script prints a comprehensive summary of the information stored in a
GeoTIFF/TIFF raster, including dataset-level metadata, per-band details, and
basic statistics for the pixel values.  Use the CLI by passing the path to a
GeoTIFF file as an argument.
"""

from __future__ import annotations

import json
import sys
from typing import Any, Dict, Sequence

import numpy as np
import rasterio
from affine import Affine


def _format_affine(transform: Affine) -> Dict[str, float]:
    """Convert an Affine transform to a serialisable dictionary."""

    return {
        "a": transform.a,
        "b": transform.b,
        "c": transform.c,
        "d": transform.d,
        "e": transform.e,
        "f": transform.f,
    }


def _band_statistics(array: np.ma.MaskedArray) -> Dict[str, Any]:
    """Calculate statistics for a masked array representing a raster band."""

    mask = np.ma.getmaskarray(array)
    if mask.size == 0 or mask.all():
        return {"count": 0}

    data = array.compressed()
    return {
        "count": int(data.size),
        "min": float(data.min()),
        "max": float(data.max()),
        "mean": float(data.mean()),
        "std": float(data.std()),
    }


def collect_raster_metadata(file_path: str) -> Dict[str, Any]:
    """Read metadata and statistics from a raster file."""

    with rasterio.open(file_path) as src:
        dataset_meta: Dict[str, Any] = {
            "driver": src.driver,
            "width": src.width,
            "height": src.height,
            "count": src.count,
            "crs": src.crs.to_string() if src.crs else None,
            "bounds": {
                "left": src.bounds.left,
                "bottom": src.bounds.bottom,
                "right": src.bounds.right,
                "top": src.bounds.top,
            },
            "resolution": src.res,
            "transform": _format_affine(src.transform),
            "dtype": src.dtypes[0] if src.count else None,
            "nodata": src.nodata,
            "color_interpretation": [ci.name for ci in src.colorinterp],
            "metadata_tags": src.tags(),
            "subdatasets": src.subdatasets,
        }

        units: Sequence[Any] = (
            getattr(src, "units", None) or [None] * src.count
        )

        band_details = []
        for band_index in range(1, src.count + 1):
            band_array = src.read(band_index, masked=True)
            band_details.append(
                {
                    "index": band_index,
                    "description": src.descriptions[band_index - 1],
                    "dtype": src.dtypes[band_index - 1],
                    "unit": units[band_index - 1]
                    if band_index - 1 < len(units)
                    else None,
                    "statistics": _band_statistics(band_array),
                    "metadata_tags": src.tags(band_index),
                }
            )

        dataset_meta["bands"] = band_details

    return dataset_meta


def _print_usage(program: str) -> None:
    message = (
        "Usage: {program} <path> [--indent <spaces>]\n\n"
        "Print metadata and statistics from a GeoTIFF/TIFF raster file."
    ).format(program=program)
    print(message, file=sys.stderr)


def _parse_cli(argv: Sequence[str]) -> tuple[str, int]:
    program = argv[0] if argv else "get_metadata_raster.py"
    path: str | None = None
    indent = 2
    args_iter = iter(argv[1:])

    for arg in args_iter:
        if arg == "--indent":
            try:
                indent_value = next(args_iter)
            except StopIteration as exc:
                raise SystemExit("--indent requires an integer value") from exc
            try:
                indent = int(indent_value)
            except ValueError as exc:
                raise SystemExit("--indent requires an integer value") from exc
            continue

        if arg.startswith("--indent="):
            indent_value = arg.split("=", 1)[1]
            try:
                indent = int(indent_value)
            except ValueError as exc:
                raise SystemExit("--indent requires an integer value") from exc
            continue

        if arg.startswith("-"):
            raise SystemExit(f"Unknown option: {arg}")

        if path is not None:
            raise SystemExit("Only one raster path can be provided")

        path = arg

    if path is None:
        _print_usage(program)
        raise SystemExit(1)

    return path, indent


def main(argv: Sequence[str] | None = None) -> None:
    cli_args = _parse_cli(list(argv) if argv is not None else sys.argv)
    path, indent = cli_args
    metadata = collect_raster_metadata(path)
    print(json.dumps(metadata, indent=indent, ensure_ascii=False))


if __name__ == "__main__":
    main()
