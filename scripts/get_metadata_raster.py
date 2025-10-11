"""Utility to inspect metadata from GeoTIFF/COG raster files.

This script prints a comprehensive summary of the information stored in a
GeoTIFF/TIFF raster, including dataset-level metadata, per-band details, and
basic statistics for the pixel values.  A default configuration is hard-coded
in the ``CONFIG`` dictionary and can be adjusted directly in this file.  An
optional positional argument lets you override the raster path without editing
the file, while still avoiding external configuration or ``argparse``.

The user requested visible commentary and logs for every processing step, band
detection complete with ordering, and wavelength information when available.
The implementation below therefore:

* adds inline comments throughout the workflow so that each stage is clearly
  explained;
* emits logging statements to ``stderr`` for the major processes and per-band
  handling steps; and
* augments the metadata output with wavelength information derived from
  hard-coded sensor knowledge or from the raster's own metadata tags when
  possible.
"""

from __future__ import annotations

import json
import logging
import sys
from pathlib import Path
from typing import Any, Dict, MutableMapping, Optional, Sequence

import numpy as np
import rasterio
from affine import Affine


# ---------------------------------------------------------------------------
# Hard-coded configuration block requested by the user.
#
# ``band_wavelength_map`` contains known wavelength values (in nanometres) for
# Sentinel-2 Level-1C band nomenclature.  When the raster descriptions or
# indices match these keys the wavelength details will be copied into the
# output.  Additional sensors can be documented by expanding this dictionary.
# ---------------------------------------------------------------------------
CONFIG: Dict[str, Any] = {
    "raster_path": "./sentinel2L1_multispectral_10m_cog_auto_crs.tif",
    "indent": 2,
    "output_path": None,
    "ensure_ascii": False,
    "log_level": "INFO",
    "band_wavelength_map": {
        "B1": {"description": "Coastal aerosol", "center_nm": 443},
        "B2": {"description": "Blue", "center_nm": 490},
        "B3": {"description": "Green", "center_nm": 560},
        "B4": {"description": "Red", "center_nm": 665},
        "B5": {"description": "Red edge 1", "center_nm": 705},
        "B6": {"description": "Red edge 2", "center_nm": 740},
        "B7": {"description": "Red edge 3", "center_nm": 783},
        "B8": {"description": "NIR", "center_nm": 842},
        "B8A": {"description": "NIR narrow", "center_nm": 865},
        "B9": {"description": "Water vapour", "center_nm": 945},
        "B10": {"description": "SWIR - Cirrus", "center_nm": 1375},
        "B11": {"description": "SWIR 1", "center_nm": 1610},
        "B12": {"description": "SWIR 2", "center_nm": 2190},
    },
}


# ---------------------------------------------------------------------------
# Logging setup used to print informative messages for each processing step.
# The configuration is performed lazily inside ``main`` so the level can be
# controlled via ``CONFIG`` while remaining customisable by importers.
# ---------------------------------------------------------------------------
LOGGER = logging.getLogger("get_metadata_raster")


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


def _normalise_band_key(value: Optional[str]) -> Optional[str]:
    """Normalise band description strings to match keys in the config map."""

    if value is None:
        return None
    candidate = value.strip().upper()
    if not candidate:
        return None
    # Remove spaces and underscores to improve matching against canonical keys.
    return candidate.replace(" ", "").replace("_", "")


def _extract_wavelength_from_tags(tags: MutableMapping[str, str]) -> Optional[Dict[str, Any]]:
    """Return wavelength metadata embedded in raster tags if available."""

    # Several sensors store wavelength metadata with different naming
    # conventions.  We check common patterns to maximise compatibility.
    for key in ("wavelength", "wavelength_nm", "center_wavelength"):
        if key in tags:
            try:
                return {"center_nm": float(tags[key])}
            except ValueError:
                continue

    # Some datasets publish wavelength ranges instead of single values.
    range_keys = (
        ("wavelength_min", "wavelength_max"),
        ("min_wavelength", "max_wavelength"),
    )
    for min_key, max_key in range_keys:
        if min_key in tags and max_key in tags:
            try:
                return {
                    "min_nm": float(tags[min_key]),
                    "max_nm": float(tags[max_key]),
                }
            except ValueError:
                continue

    return None


def _resolve_band_wavelength(
    band_index: int,
    description: Optional[str],
    tags: MutableMapping[str, str],
    config_map: MutableMapping[str, Dict[str, Any]],
) -> Optional[Dict[str, Any]]:
    """Determine wavelength info from tags or from the hard-coded config map."""

    # First priority: explicit metadata inside the raster file.
    tag_wavelength = _extract_wavelength_from_tags(tags)
    if tag_wavelength:
        return tag_wavelength

    # Second priority: match against the configured band name map.
    normalised_description = _normalise_band_key(description)
    candidates = []
    if normalised_description:
        candidates.append(normalised_description)
    # Always try band indices in ``B<number>`` form as a fallback.
    candidates.append(f"B{band_index}")

    for candidate in candidates:
        if candidate in config_map:
            return dict(config_map[candidate])

        normalised_candidate = _normalise_band_key(candidate)
        if normalised_candidate and normalised_candidate in config_map:
            return dict(config_map[normalised_candidate])

        for config_key, config_value in config_map.items():
            if _normalise_band_key(str(config_key)) == normalised_candidate:
                return dict(config_value)

    # If nothing matches we return ``None`` to indicate missing data.
    return None


def collect_raster_metadata(
    file_path: str,
    *,
    band_wavelength_map: MutableMapping[str, Dict[str, Any]],
) -> Dict[str, Any]:
    """Read metadata and statistics from a raster file with detailed logging."""

    LOGGER.info("Opening raster: %s", file_path)
    with rasterio.open(file_path) as src:
        LOGGER.info("Collecting dataset-level metadata")
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
        LOGGER.info("Iterating through %d band(s)", src.count)
        for band_index in range(1, src.count + 1):
            LOGGER.info("Processing band %d", band_index)
            band_array = src.read(band_index, masked=True)
            band_tags = src.tags(band_index)
            wavelength = _resolve_band_wavelength(
                band_index,
                src.descriptions[band_index - 1],
                band_tags,
                band_wavelength_map,
            )

            band_detail = {
                "index": band_index,
                "description": src.descriptions[band_index - 1],
                "dtype": src.dtypes[band_index - 1],
                "unit": units[band_index - 1]
                if band_index - 1 < len(units)
                else None,
                "statistics": _band_statistics(band_array),
                "metadata_tags": band_tags,
            }

            if wavelength is not None:
                band_detail["wavelength"] = wavelength
                LOGGER.info(
                    "Band %d wavelength resolved: %s",
                    band_index,
                    json.dumps(wavelength),
                )
            else:
                LOGGER.info("Band %d has no wavelength metadata", band_index)

            band_details.append(band_detail)

        dataset_meta["bands"] = band_details

    LOGGER.info("Finished reading metadata for %s", file_path)
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

    # Configure logging using the requested level so that progress is printed
    # during each processing step without requiring external configuration.
    log_level_name = str(runtime_config.get("log_level", "INFO"))
    logging.basicConfig(
        level=getattr(logging, log_level_name.upper(), logging.INFO),
        format="[%(levelname)s] %(message)s",
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

    band_wavelength_map = runtime_config.get("band_wavelength_map", {})
    if not isinstance(band_wavelength_map, MutableMapping):
        raise SystemExit("CONFIG 'band_wavelength_map' must be a mapping.")

    # Normalise the configured band wavelength entries for resilient lookups.
    normalised_band_map: Dict[str, Dict[str, Any]] = {}
    for band_key, band_value in band_wavelength_map.items():
        if not isinstance(band_value, MutableMapping):
            LOGGER.warning(
                "Ignoring band_wavelength_map entry for %s because it is not a mapping",
                band_key,
            )
            continue

        key_string = str(band_key)
        normalised_band_map[key_string] = dict(band_value)

        normalised_key = _normalise_band_key(key_string)
        if normalised_key:
            normalised_band_map[normalised_key] = dict(band_value)

    metadata = collect_raster_metadata(
        raster_path,
        band_wavelength_map=normalised_band_map,
    )

    output_path = runtime_config.get("output_path")
    if output_path:
        destination = Path(str(output_path)).expanduser()
        LOGGER.info("Writing metadata JSON to %s", destination)
        destination.write_text(
            json.dumps(metadata, indent=indent, ensure_ascii=ensure_ascii),
            encoding="utf-8",
        )
        print(f"Metadata written to {destination}")
        return

    LOGGER.info("Printing metadata JSON to stdout")
    print(json.dumps(metadata, indent=indent, ensure_ascii=ensure_ascii))


if __name__ == "__main__":
    main()
