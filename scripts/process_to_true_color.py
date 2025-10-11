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
import xml.etree.ElementTree as ET
import zipfile
from contextlib import contextmanager
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterator, List, Mapping, MutableMapping, Optional, Tuple

import numpy as np
import rasterio
from rasterio.crs import CRS
from rasterio.errors import CRSError
from rasterio.enums import Resampling
from rasterio.transform import Affine


# Daftar band yang dibutuhkan untuk komposit true colour.
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
def managed_workspace_directory(zip_path: Path, keep: bool = False) -> Iterator[Path]:
    """Create a workspace directory next to the provided archive."""

    base_dir = zip_path.resolve().parent
    stem = zip_path.stem

    candidate = base_dir / f"{stem}_truecolour_tmp"
    counter = 1
    while candidate.exists():
        counter += 1
        candidate = base_dir / f"{stem}_truecolour_tmp{counter}"

    candidate.mkdir(parents=True, exist_ok=False)

    try:
        yield candidate
    finally:
        if keep:
            print(f"ℹ️  Menjaga folder sementara di: {candidate}")
        else:
            shutil.rmtree(candidate, ignore_errors=True)


def extract_required_assets(zip_path: Path, destination: Path) -> ExtractionResult:
    """Extract the metadata file and the 10 m RGB bands from the archive."""

    print("🔍 Membaca isi arsip Sentinel untuk mencari band dan metadata...")

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

        extracted_bands = {}
        for band, member in band_members.items():
            print(f"   • Mengekstrak {band} → {member}")
            extracted_bands[band] = Path(archive.extract(member, destination))

        extracted_metadata: Optional[Path] = None
        if metadata_member:
            print(f"🗂️  Metadata ditemukan: {metadata_member}")
            extracted_metadata = Path(archive.extract(metadata_member, destination))
        else:
            print("⚠️  Metadata utama tidak ditemukan dalam arsip. Melanjutkan tanpa metadata.")

    return ExtractionResult(band_paths=extracted_bands, metadata_path=extracted_metadata)


def extract_metadata_fields(metadata_path: Optional[Path]) -> Dict[str, str]:
    """Parse useful fields from the metadata XML, returning fallbacks if needed."""

    defaults = {
        "SENSING_TIME": "Unknown",
        "PLATFORM": "Unknown",
        "PRODUCT_ID": "Unknown",
        "CLOUD_COVERAGE_ASSESSMENT": "Unknown",
        "PRODUCT_LEVEL": "Unknown",
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
    level = find_first("PROCESSING_LEVEL") or find_first("PRODUCT_TYPE")
    if level:
        match = re.search(
            r"(Level[-_ ]?\d[A-Z]?|MSIL\d[A-Z]?)",
            level,
            flags=re.IGNORECASE,
        )
        if match:
            level = match.group(0)
    metadata["PRODUCT_LEVEL"] = level or defaults["PRODUCT_LEVEL"]
    return metadata


MANUAL_EPSG_PROJ4: Mapping[int, str] = {
    4326: "+proj=longlat +datum=WGS84 +no_defs +type=crs",
    3857: "+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 "
    "+x_0=0.0 +y_0=0.0 +k=1.0 +units=m +nadgrids=@null +wktext +no_defs +type=crs",
}


def manual_crs_from_epsg(epsg: int) -> Optional[CRS]:
    """Create a CRS from a small curated set of EPSG definitions."""

    if epsg in MANUAL_EPSG_PROJ4:
        return CRS.from_proj4(MANUAL_EPSG_PROJ4[epsg])

    if 32601 <= epsg <= 32660:
        zone = epsg - 32600
        proj4 = (
            f"+proj=utm +zone={zone} +datum=WGS84 +units=m +no_defs +type=crs"
        )
        return CRS.from_proj4(proj4)

    if 32701 <= epsg <= 32760:
        zone = epsg - 32700
        proj4 = (
            f"+proj=utm +zone={zone} +south +datum=WGS84 +units=m +no_defs +type=crs"
        )
        return CRS.from_proj4(proj4)

    return None


def safe_crs_from_epsg(epsg: int) -> Optional[CRS]:
    """Safely attempt to create a CRS without relying on the PROJ database."""

    try:
        crs = manual_crs_from_epsg(epsg)
    except CRSError:
        crs = None

    if crs is not None:
        print(f"📐 CRS EPSG:{epsg} dimuat dari definisi manual.")
        return crs

    print(
        "⚠️  Definisi manual untuk EPSG:{} tidak tersedia."
        " Perlu fallback lain.".format(epsg)
    )
    return None


def _tile_code_from_metadata(metadata_path: Path) -> Optional[str]:
    """Ambil kode tile Sentinel-2 dari metadata bila tersedia."""

    try:
        tree = ET.parse(metadata_path)
    except (ET.ParseError, FileNotFoundError):
        return None

    root = tree.getroot()

    # Cari kode tile pada elemen yang umum dipakai oleh produk Level-1C/2A.
    candidates = [
        "TILE_ID",
        "PRODUCT_URI",
        "PRODUCT_ID",
        "PRODUCT_TILE_ID",
    ]
    for tag in candidates:
        for element in root.findall(f".//{{*}}{tag}"):
            if not element.text:
                continue
            match = re.search(r"T\d{2}[A-Z]{3}", element.text)
            if match:
                return match.group(0)

    # Sebagai alternatif, periksa atribut dari elemen mana pun.
    for element in root.iter():
        for value in element.attrib.values():
            match = re.search(r"T\d{2}[A-Z]{3}", value)
            if match:
                return match.group(0)

    return None


def derive_crs(
    reference_band: Path,
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
                        crs = safe_crs_from_epsg(epsg)
                        if crs:
                            print(f"📐 CRS diambil dari metadata (EPSG:{epsg}).")
                            return crs
                    if code.isdigit():
                        epsg = int(code)
                        crs = safe_crs_from_epsg(epsg)
                        if crs:
                            print(f"📐 CRS diambil dari metadata (EPSG:{epsg}).")
                            return crs
        except (ET.ParseError, ValueError, CRSError):
            pass

    if metadata_path and metadata_path.exists():
        tile_code = _tile_code_from_metadata(metadata_path)
        if tile_code:
            match = re.search(r"T(\d{2})([A-Z]{3})", tile_code)
            if match:
                utm_zone = int(match.group(1))
                hemisphere = "N"
                if match.group(2)[0] < "N":
                    hemisphere = "S"
                utm_epsg = 32600 + utm_zone if hemisphere == "N" else 32700 + utm_zone
                crs = safe_crs_from_epsg(utm_epsg)
                if crs:
                    print(
                        "🗺️  CRS diduga dari metadata tile ({}{} → EPSG:{}).".format(
                            match.group(1), match.group(2), utm_epsg
                        )
                    )
                    return crs

    print("⚠️  CRS tidak ditemukan, fallback ke WGS84 (manual proj4).")
    return CRS.from_proj4("+proj=longlat +datum=WGS84 +no_defs +type=crs")


def read_and_resample_bands(
    band_paths: Mapping[str, Path]
) -> Tuple[np.ndarray, Mapping[str, object], Affine, str]:
    """Read Sentinel bands and resample them to match the 10 m grid."""

    print("🧮 Membaca dan meresampel band B04, B03, B02 ke grid 10 m...")

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
            print(f"   • Band {band_code} berhasil dibaca dan disesuaikan ukurannya.")

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

    print(f"💾 Menulis True Colour COG ke {output_path}...")

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

    with managed_workspace_directory(
        config.zip_path, keep=config.keep_temp_directory
    ) as temp_dir:
        print(f"📂 Mengambil aset penting ke: {temp_dir}")
        extraction = extract_required_assets(config.zip_path, temp_dir)

        for code, path in extraction.band_paths.items():
            try:
                displayed = path.relative_to(temp_dir)
            except ValueError:
                displayed = path
            print(f"   • {code} ({TRUECOLOUR_BANDS[code]}): {displayed}")

        metadata_fields = extract_metadata_fields(extraction.metadata_path)
        print("\n🛰️ Metadata Scene:")
        for key, value in metadata_fields.items():
            print(f"   {key:<28}: {value}")

        stacked, profile, transform, dtype = read_and_resample_bands(extraction.band_paths)

        crs = derive_crs(
            reference_band=extraction.band_paths["B04"],
            metadata_path=extraction.metadata_path,
        )

        if extraction.metadata_path and extraction.metadata_path.exists():
            product_level = metadata_fields.get("PRODUCT_LEVEL", "Unknown")
            print(f"🏷️  Produk Sentinel terdeteksi pada level: {product_level}")
        else:
            print("🏷️  Produk Sentinel level tidak dapat ditentukan (metadata tidak tersedia).")

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
