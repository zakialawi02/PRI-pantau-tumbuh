"""Utility for generating Sentinel-2 true colour composites.

The script accepts Sentinel-2 Level-1C or Level-2A products packaged as a
single ``.zip`` file, extracts only the assets that are required to build a
true colour composite (B04, B03, B02 and the main metadata document), and
produces a Cloud Optimised GeoTIFF at 10 m resolution. The raster projection is
preserved whenever it is available within the imagery; otherwise, an attempt is
made to recover it from the metadata before falling back to an educated guess
derived from the tile identifier.

Konfigurasi input/output diatur langsung pada konstanta ``CONFIG`` yang dapat
disesuaikan manual di dalam berkas ini.

"""

from __future__ import annotations

import os
import re
import shutil
import tempfile
import xml.etree.ElementTree as ET
import zipfile
from contextlib import contextmanager
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterator, List, Mapping, MutableMapping, Optional, Tuple

import numpy as np
import rasterio
from rasterio.crs import CRS
from rasterio.enums import Resampling
from rasterio.transform import Affine


TRUECOLOUR_BANDS: Mapping[str, str] = {"B04": "Red", "B03": "Green", "B02": "Blue"}


@dataclass(frozen=True)
class ProcessingConfig:
    """Konfigurasi manual untuk menjalankan pemrosesan."""

    zip_path: Path
    output_path: Optional[Path] = None
    keep_temp_directory: bool = False


@dataclass
class ExtractionResult:
    """Container for artefacts extracted from the Sentinel archive."""

    band_paths: Mapping[str, Path]
    metadata_path: Optional[Path]


# ---------------------------------------------------------------------------
# ⚙️  Ubah nilai konstanta CONFIG berikut sebelum menjalankan skrip.
# ---------------------------------------------------------------------------
CONFIG = ProcessingConfig(
    zip_path=Path("/path/ke/produk_Sentinel2.zip"),
    output_path=None,
    keep_temp_directory=False,
)


@contextmanager
def managed_temporary_directory(keep: bool = False) -> Iterator[Path]:
    """Create (and optionally keep) a temporary directory."""

    temp_dir = Path(tempfile.mkdtemp(prefix="s2_truecolour_"))
    try:
        yield temp_dir
    finally:
        if keep:
            print(f"ℹ️  Menjaga folder sementara di: {temp_dir}")
        else:
            shutil.rmtree(temp_dir, ignore_errors=True)


def extract_required_assets(zip_path: Path, destination: Path) -> ExtractionResult:
    """Extract the metadata file and the 10 m RGB bands from the archive."""

    band_members: MutableMapping[str, str] = {}
    metadata_member: Optional[str] = None

    band_patterns = {
        band: re.compile(
            r"GRANULE/.*/IMG_DATA/(?:R10m/)?[^/]*_{band}(?:_10m)?\.jp2$".format(
                band=band
            ),
            re.IGNORECASE,
        )
        for band in TRUECOLOUR_BANDS
    }
    metadata_pattern = re.compile(r"MTD_MSIL[12][AC]\.xml$", re.IGNORECASE)

    with zipfile.ZipFile(zip_path) as archive:
        members = archive.namelist()

        for member in members:
            basename = os.path.basename(member)
            if metadata_member is None and metadata_pattern.search(basename):
                metadata_member = member
            for band_code in TRUECOLOUR_BANDS:
                if band_code in band_members:
                    continue
                if band_patterns[band_code].search(member):
                    band_members[band_code] = member

        missing = [b for b in TRUECOLOUR_BANDS if b not in band_members]
        if missing:
            raise FileNotFoundError(
                "Band berikut tidak ditemukan dalam arsip: " + ", ".join(missing)
            )

        extracted_bands = {
            band: Path(archive.extract(member, destination))
            for band, member in band_members.items()
        }

        extracted_metadata: Optional[Path] = None
        if metadata_member:
            extracted_metadata = Path(archive.extract(metadata_member, destination))

    return ExtractionResult(band_paths=extracted_bands, metadata_path=extracted_metadata)


def extract_metadata_fields(metadata_path: Optional[Path]) -> Dict[str, str]:
    """Parse useful fields from the metadata XML, returning fallbacks if needed."""

    defaults = {
        "SENSING_TIME": "Unknown",
        "PLATFORM": "Unknown",
        "PRODUCT_ID": "Unknown",
        "CLOUD_COVERAGE_ASSESSMENT": "Unknown",
    }

    if metadata_path is None or not metadata_path.exists():
        return defaults

    try:
        tree = ET.parse(metadata_path)
    except ET.ParseError:
        return defaults

    root = tree.getroot()

    def find_first(tag: str) -> Optional[str]:
        for element in root.findall(f".//{{*}}{tag}"):
            if element.text:
                value = element.text.strip()
                if value:
                    return value
        return None

    metadata = defaults.copy()
    metadata["SENSING_TIME"] = find_first("SENSING_TIME") or defaults["SENSING_TIME"]
    metadata["PLATFORM"] = find_first("SPACECRAFT_NAME") or defaults["PLATFORM"]
    metadata["PRODUCT_ID"] = find_first("PRODUCT_URI") or defaults["PRODUCT_ID"]
    metadata["CLOUD_COVERAGE_ASSESSMENT"] = (
        find_first("CLOUD_COVERAGE_ASSESSMENT")
        or defaults["CLOUD_COVERAGE_ASSESSMENT"]
    )
    return metadata


def derive_crs(
    reference_band: Path,
    zip_name: str,
    metadata_path: Optional[Path] = None,
) -> CRS:
    """Determine the CRS, prioritising the raster, followed by metadata."""

    with rasterio.open(reference_band) as src:
        raster_crs = src.crs
    if raster_crs:
        print(f"📐 CRS terdeteksi langsung dari raster: {raster_crs}")
        return raster_crs

    if metadata_path and metadata_path.exists():
        try:
            tree = ET.parse(metadata_path)
            root = tree.getroot()
            for tag in ("HORIZONTAL_CS_CODE", "Code"):
                for element in root.findall(f".//{{*}}{tag}"):
                    if not element.text:
                        continue
                    code = element.text.strip()
                    if not code:
                        continue
                    if code.upper().startswith("EPSG:"):
                        epsg = int(code.split(":", 1)[1])
                        crs = CRS.from_epsg(epsg)
                        print(f"📐 CRS diambil dari metadata (EPSG:{epsg}).")
                        return crs
                    if code.isdigit():
                        epsg = int(code)
                        crs = CRS.from_epsg(epsg)
                        print(f"📐 CRS diambil dari metadata (EPSG:{epsg}).")
                        return crs
        except (ET.ParseError, ValueError):
            pass

    match = re.search(r"T(\d{2})([A-Z]{3})", zip_name)
    if match:
        utm_zone = int(match.group(1))
        hemisphere = "N"
        if match.group(2)[0] < "N":
            hemisphere = "S"
        utm_epsg = 32600 + utm_zone if hemisphere == "N" else 32700 + utm_zone
        try:
            crs = CRS.from_epsg(utm_epsg)
            print(
                "🗺️  CRS diduga dari kode tile ({}{} → EPSG:{}).".format(
                    match.group(1), match.group(2), utm_epsg
                )
            )
            return crs
        except ValueError:
            pass

    print("⚠️  CRS tidak ditemukan, fallback ke EPSG:4326 (WGS84).")
    return CRS.from_epsg(4326)


def read_and_resample_bands(
    band_paths: Mapping[str, Path]
) -> Tuple[np.ndarray, Mapping[str, object], Affine, str]:
    """Read Sentinel bands and resample them to match the 10 m grid."""

    reference_path = band_paths["B04"]
    arrays: List[np.ndarray] = []
    with rasterio.open(reference_path) as reference:
        out_height, out_width = reference.shape
        profile = reference.profile
        transform = reference.transform
        dtype = reference.dtypes[0]

    for band_code in ("B04", "B03", "B02"):
        band_path = band_paths[band_code]
        with rasterio.open(band_path) as src:
            data = src.read(
                out_shape=(1, out_height, out_width),
                resampling=Resampling.bilinear,
            )[0]
            arrays.append(data)

    stacked = np.stack(arrays, axis=0)
    return stacked, profile, transform, dtype


def create_truecolour_cog(
    stacked_bands: np.ndarray,
    profile: Mapping[str, object],
    transform,
    crs: CRS,
    output_path: Path,
    metadata: Mapping[str, str],
) -> None:
    """Write the stacked RGB data into a Cloud Optimised GeoTIFF."""

    output_profile = dict(profile)
    output_profile.update(
        driver="COG",
        dtype=stacked_bands.dtype,
        count=stacked_bands.shape[0],
        transform=transform,
        crs=crs,
        compress="LZW",
        BIGTIFF="IF_NEEDED",
        blockxsize=512,
        blockysize=512,
    )

    with rasterio.open(output_path, "w", **output_profile) as dst:
        for idx, band_code in enumerate(("B04", "B03", "B02"), start=1):
            dst.write(stacked_bands[idx - 1], idx)
            dst.set_band_description(idx, f"{TRUECOLOUR_BANDS[band_code]} ({band_code})")
        dst.update_tags(
            **metadata,
            CRS_SOURCE=str(crs),
            RGB_COMPOSITION="B04-Red, B03-Green, B02-Blue",
            NOTE="True colour composite pada resolusi 10 m.",
        )


def main(config: ProcessingConfig = CONFIG) -> None:
    if not config.zip_path.exists():
        raise FileNotFoundError(
            "Produk Sentinel tidak ditemukan: {}. Ubah nilai CONFIG.zip_path sebelum menjalankan."
            .format(config.zip_path)
        )

    output_path = config.output_path
    if output_path is None:
        default_name = config.zip_path.stem + "_truecolor.tif"
        output_path = Path.cwd() / default_name

    output_path.parent.mkdir(parents=True, exist_ok=True)

    print(f"📦 Memproses arsip: {config.zip_path}")

    with managed_temporary_directory(keep=config.keep_temp_directory) as temp_dir:
        print(f"📂 Mengambil aset penting ke: {temp_dir}")
        extraction = extract_required_assets(config.zip_path, temp_dir)

        for code, path in extraction.band_paths.items():
            print(f"   • {code} ({TRUECOLOUR_BANDS[code]}): {path.relative_to(temp_dir)}")

        metadata_fields = extract_metadata_fields(extraction.metadata_path)
        print("\n🛰️ Metadata Scene:")
        for key, value in metadata_fields.items():
            print(f"   {key:<28}: {value}")

        stacked, profile, transform, dtype = read_and_resample_bands(extraction.band_paths)

        crs = derive_crs(
            reference_band=extraction.band_paths["B04"],
            zip_name=config.zip_path.stem,
            metadata_path=extraction.metadata_path,
        )

        create_truecolour_cog(
            stacked_bands=stacked.astype(dtype, copy=False),
            profile=profile,
            transform=transform,
            crs=crs,
            output_path=output_path,
            metadata=metadata_fields,
        )

    print("\n✅ File True Colour (COG) disimpan:", output_path)
    print(f"   CRS output: {crs}")
    print("   Resolusi: 10 m")
    print("   Format: Cloud-Optimized GeoTIFF")


if __name__ == "__main__":
    main()
