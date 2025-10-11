"""Utility to inspect metadata from GeoTIFF/COG raster files.

This script prints a comprehensive summary of the information stored in a
GeoTIFF/TIFF raster, including dataset-level metadata, per-band details, and
basic statistics for the pixel values.  A default configuration is hard-coded
in the ``CONFIG`` dictionary and can be adjusted directly in this file.  An
optional positional argument lets you override the raster path without editing
the file, while still avoiding external configuration or ``argparse``.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any, Dict, MutableMapping, Sequence

import numpy as np
import rasterio
from affine import Affine


CONFIG: Dict[str, Any] = {
    "raster_path": "./sentinel2L1_multispectral_10m_cog_auto_crs.tif",
    "indent": 2,
    "output_path": None,
    "ensure_ascii": False,
}


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
        "Usage: {program} [<path>]\n\n"
        "When no path is provided, the script will use the hard-coded CONFIG\n"
        "dictionary defined at the top of the module. Edit CONFIG to change\n"
        "the default raster path, JSON indentation, or output destination."
    ).format(program=program)
    print(message, file=sys.stderr)


def _resolve_runtime_config(
    argv: Sequence[str],
    base_config: MutableMapping[str, Any],
) -> Dict[str, Any]:
    program = argv[0] if argv else "get_metadata_raster.py"
    config: Dict[str, Any] = dict(base_config)

    args = list(argv[1:])
    if not args:
        if not config.get("raster_path"):
            _print_usage(program)
            raise SystemExit(1)
        return config

    if len(args) > 1:
        raise SystemExit("Only one raster path can be provided")

    option = args[0]
    if option in {"-h", "--help"}:
        _print_usage(program)
        raise SystemExit(0)

    if option.startswith("-"):
        raise SystemExit(f"Unknown option: {option}")

    config["raster_path"] = option
    return config


def main(argv: Sequence[str] | None = None) -> None:
    runtime_config = _resolve_runtime_config(
        list(argv) if argv is not None else sys.argv,
        CONFIG,
    )

    raster_path = runtime_config.get("raster_path")
    if not isinstance(raster_path, str) or not raster_path.strip():
        raise SystemExit("CONFIG must define a non-empty 'raster_path'.")

    indent_value = runtime_config.get("indent", 2)
    try:
        indent = int(indent_value)
    except (TypeError, ValueError) as exc:
        raise SystemExit("CONFIG 'indent' must be an integer value.") from exc

    ensure_ascii = bool(runtime_config.get("ensure_ascii", False))

    metadata = collect_raster_metadata(raster_path)

    output_path = runtime_config.get("output_path")
    if output_path:
        destination = Path(str(output_path)).expanduser()
        destination.write_text(
            json.dumps(metadata, indent=indent, ensure_ascii=ensure_ascii),
            encoding="utf-8",
        )
        print(f"Metadata written to {destination}")
        return

    print(json.dumps(metadata, indent=indent, ensure_ascii=ensure_ascii))


if __name__ == "__main__":
    main()
