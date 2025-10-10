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


TRUECOLOUR_BANDS: Mapping[str, str] = {"B04": "Red", "B03": "Green", "B02": "Blue"}


@dataclass(frozen=True)
class ProcessingConfig:
    """Konfigurasi manual untuk menjalankan pemrosesan."""

    zip_path: Path
    output_path: Optional[Path] = None
    keep_temp_directory: bool = False


@dataclass
class MetadataSummary:
    """Ringkasan metadata Sentinel yang relevan untuk pemrosesan."""

    general_fields: Dict[str, str]
    quantification_value: Optional[float]
    radiometric_offsets: Mapping[str, float]
    band_file_templates: Mapping[str, str]
    preferred_order: Tuple[str, ...]
    true_colour_path: Optional[str]

    @staticmethod
    def empty(defaults: Optional[Dict[str, str]] = None) -> "MetadataSummary":
        base_fields = {
            "SENSING_TIME": "Unknown",
            "PLATFORM": "Unknown",
            "PRODUCT_ID": "Unknown",
            "CLOUD_COVERAGE_ASSESSMENT": "Unknown",
        }
        if defaults:
            base_fields.update(defaults)
        return MetadataSummary(
            general_fields=base_fields,
            quantification_value=None,
            radiometric_offsets={},
            band_file_templates={},
            preferred_order=("B04", "B03", "B02"),
            true_colour_path=None,
        )


@dataclass
class ExtractionResult:
    """Container for artefacts extracted from the Sentinel archive."""

    band_paths: Mapping[str, Path]
    metadata_path: Optional[Path]
    metadata_summary: MetadataSummary


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


def _resolve_band_members(
    members: List[str],
    templates: Mapping[str, str],
) -> Mapping[str, str]:
    """Temukan anggota arsip JP2 berdasarkan template metadata."""

    resolved: Dict[str, str] = {}
    normalised_members = [member.replace("\\", "/") for member in members]
    for band_code, template in templates.items():
        template_norm = template.lower().replace("\\", "/")
        template_norm = template_norm.rstrip(".jp2")
        for member, normalised in zip(members, normalised_members):
            lower_member = normalised.lower()
            if not lower_member.endswith(".jp2"):
                continue
            if template_norm in lower_member and f"_{band_code.lower()}" in lower_member:
                resolved[band_code] = member
                break
    return resolved


def extract_required_assets(zip_path: Path, destination: Path) -> ExtractionResult:
    """Extract the metadata file and the 10 m RGB bands from the archive."""

    metadata_pattern = re.compile(r"MTD_MSIL[12][AC]\.xml$", re.IGNORECASE)

    with zipfile.ZipFile(zip_path) as archive:
        members = archive.namelist()

        metadata_member: Optional[str] = None
        metadata_bytes: Optional[bytes] = None
        for member in members:
            basename = os.path.basename(member)
            if metadata_pattern.search(basename):
                metadata_member = member
                metadata_bytes = archive.read(member)
                break

        metadata_summary = (
            parse_metadata_summary_from_bytes(metadata_bytes)
            if metadata_bytes is not None
            else MetadataSummary.empty()
        )

        band_members: MutableMapping[str, str] = _resolve_band_members(
            members, metadata_summary.band_file_templates
        )

        if len(band_members) < len(TRUECOLOUR_BANDS):
            band_patterns = {
                band: re.compile(
                    r"GRANULE/.*/IMG_DATA/(?:R10m/)?[^/]*_{band}(?:_10m)?\.jp2$".format(
                        band=band
                    ),
                    re.IGNORECASE,
                )
                for band in TRUECOLOUR_BANDS
            }
            for member in members:
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

    return ExtractionResult(
        band_paths=extracted_bands,
        metadata_path=extracted_metadata,
        metadata_summary=metadata_summary,
    )


MANUAL_EPSG_PROJ4: Mapping[int, str] = {
    4326: "+proj=longlat +datum=WGS84 +no_defs +type=crs",
    3857: "+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 "
    "+x_0=0.0 +y_0=0.0 +k=1.0 +units=m +nadgrids=@null +wktext +no_defs +type=crs",
}


def _normalise_band_code(band: str) -> Optional[str]:
    """Konversi nama band dari metadata menjadi kode standar (mis. B04)."""

    if not band:
        return None
    band = band.upper().strip()
    if band == "TCI":
        return "TCI"
    if band == "B8A":
        return "B8A"
    if not band.startswith("B"):
        return None
    suffix = band[1:]
    if suffix.isdigit():
        return f"B{int(suffix):02d}"
    return band


def parse_metadata_summary_from_bytes(data: bytes) -> MetadataSummary:
    """Bangun ringkasan metadata dari konten XML mentah."""

    if not data:
        return MetadataSummary.empty()

    try:
        root = ET.fromstring(data)
    except ET.ParseError:
        return MetadataSummary.empty()

    def find_first(tag: str) -> Optional[str]:
        for element in root.findall(f".//{{*}}{tag}"):
            if element.text:
                value = element.text.strip()
                if value:
                    return value
        return None

    general_fields = {
        "SENSING_TIME": find_first("SENSING_TIME") or "Unknown",
        "PLATFORM": find_first("SPACECRAFT_NAME") or "Unknown",
        "PRODUCT_ID": find_first("PRODUCT_URI") or "Unknown",
        "CLOUD_COVERAGE_ASSESSMENT": find_first("CLOUD_COVERAGE_ASSESSMENT")
        or "Unknown",
    }

    quant_value: Optional[float] = None
    quant_text = find_first("QUANTIFICATION_VALUE")
    if quant_text:
        try:
            quant_value = float(quant_text)
        except ValueError:
            quant_value = None

    band_id_to_code: Dict[int, str] = {}
    for spectral in root.findall(".//{*}Spectral_Information"):
        band_id_text = spectral.get("bandId")
        physical_band = spectral.get("physicalBand")
        if not band_id_text or physical_band is None:
            continue
        try:
            band_id = int(band_id_text)
        except ValueError:
            continue
        band_code = _normalise_band_code(physical_band)
        if band_code:
            band_id_to_code[band_id] = band_code

    radiometric_offsets: Dict[str, float] = {}
    for offset in root.findall(".//{*}RADIO_ADD_OFFSET"):
        band_id_text = offset.get("band_id")
        if not band_id_text or not offset.text:
            continue
        try:
            band_id = int(band_id_text)
        except ValueError:
            continue
        band_code = band_id_to_code.get(band_id)
        if not band_code:
            continue
        try:
            radiometric_offsets[band_code] = float(offset.text.strip())
        except ValueError:
            continue

    band_file_templates: Dict[str, str] = {}
    true_colour_path: Optional[str] = None
    for image in root.findall(".//{*}IMAGE_FILE"):
        if not image.text:
            continue
        text = image.text.strip().replace("\\", "/")
        match = re.search(r"_(B\d{1,2}|B8A|TCI)(?:_10m)?$", text, re.IGNORECASE)
        if not match:
            continue
        suffix = match.group(1).upper()
        band_code = _normalise_band_code(suffix)
        if not band_code:
            continue
        if band_code == "TCI":
            true_colour_path = text
            continue
        band_file_templates[band_code] = text

    preferred_order: Tuple[str, ...] = ("B04", "B03", "B02")
    display_order = []
    for channel_tag in ("RED_CHANNEL", "GREEN_CHANNEL", "BLUE_CHANNEL"):
        channel_text = find_first(channel_tag)
        if not channel_text:
            continue
        try:
            channel_index = int(channel_text)
        except ValueError:
            continue
        band_code = band_id_to_code.get(channel_index)
        if band_code:
            display_order.append(band_code)
    if len(display_order) == 3:
        preferred_order = tuple(display_order)

    return MetadataSummary(
        general_fields=general_fields,
        quantification_value=quant_value,
        radiometric_offsets=radiometric_offsets,
        band_file_templates=band_file_templates,
        preferred_order=preferred_order,
        true_colour_path=true_colour_path,
    )


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

    match = re.search(r"T(\d{2})([A-Z]{3})", zip_name)
    if match:
        utm_zone = int(match.group(1))
        hemisphere = "N"
        if match.group(2)[0] < "N":
            hemisphere = "S"
        utm_epsg = 32600 + utm_zone if hemisphere == "N" else 32700 + utm_zone
        crs = safe_crs_from_epsg(utm_epsg)
        if crs:
            print(
                "🗺️  CRS diduga dari kode tile ({}{} → EPSG:{}).".format(
                    match.group(1), match.group(2), utm_epsg
                )
            )
            return crs

    print("⚠️  CRS tidak ditemukan, fallback ke WGS84 (manual proj4).")
    return CRS.from_proj4("+proj=longlat +datum=WGS84 +no_defs +type=crs")


def read_and_resample_bands(
    band_paths: Mapping[str, Path],
    band_order: Tuple[str, ...],
) -> Tuple[np.ndarray, Mapping[str, object], Affine, str]:
    """Read Sentinel bands and resample them to match the 10 m grid."""

    reference_path = band_paths[band_order[0]]
    arrays: List[np.ndarray] = []
    with rasterio.open(reference_path) as reference:
        out_height, out_width = reference.shape
        profile = reference.profile
        transform = reference.transform
        dtype = reference.dtypes[0]

    for band_code in band_order:
        band_path = band_paths[band_code]
        with rasterio.open(band_path) as src:
            data = src.read(
                out_shape=(1, out_height, out_width),
                resampling=Resampling.bilinear,
            )[0]
            arrays.append(data)

    stacked = np.stack(arrays, axis=0)
    return stacked, profile, transform, dtype


def apply_radiometric_adjustments(
    stacked: np.ndarray,
    band_order: Tuple[str, ...],
    dtype: str,
    metadata_summary: MetadataSummary,
) -> np.ndarray:
    """Gunakan offset radiometrik dan kuantisasi dari metadata bila tersedia."""

    has_offsets = bool(metadata_summary.radiometric_offsets)
    has_quant = metadata_summary.quantification_value is not None
    if not has_offsets and not has_quant:
        return stacked

    working = stacked.astype(np.float32, copy=True)
    for idx, band_code in enumerate(band_order):
        offset = metadata_summary.radiometric_offsets.get(band_code)
        if offset is not None:
            working[idx] += offset
    if has_quant:
        working = np.clip(working, 0.0, metadata_summary.quantification_value)
    dtype_info = np.dtype(dtype)
    if np.issubdtype(dtype_info, np.integer):
        info = np.iinfo(dtype_info)
        working = np.clip(working, info.min, info.max)
        return working.round().astype(dtype_info)
    if np.issubdtype(dtype_info, np.floating):
        return working.astype(dtype_info)
    return working.astype(stacked.dtype, copy=False)


def create_truecolour_cog(
    stacked_bands: np.ndarray,
    profile: Mapping[str, object],
    transform,
    crs: CRS,
    output_path: Path,
    metadata: Mapping[str, str],
    band_order: Tuple[str, ...],
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
        for idx, band_code in enumerate(band_order, start=1):
            dst.write(stacked_bands[idx - 1], idx)
            display_name = TRUECOLOUR_BANDS.get(band_code, band_code)
            dst.set_band_description(idx, f"{display_name} ({band_code})")
        composition = ", ".join(
            f"{band_code}-{TRUECOLOUR_BANDS.get(band_code, band_code)}"
            for band_code in band_order
        )
        dst.update_tags(
            **metadata,
            CRS_SOURCE=str(crs),
            RGB_COMPOSITION=composition,
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

        metadata_summary = extraction.metadata_summary
        metadata_fields = metadata_summary.general_fields
        print("\n🛰️ Metadata Scene:")
        for key, value in metadata_fields.items():
            print(f"   {key:<28}: {value}")

        if metadata_summary.quantification_value is not None:
            print(
                "   QUANTIFICATION_VALUE        :"
                f" {metadata_summary.quantification_value}"
            )
        if metadata_summary.radiometric_offsets:
            offsets_str = ", ".join(
                f"{band}={offset}" for band, offset in sorted(
                    metadata_summary.radiometric_offsets.items()
                )
            )
            print(f"   RADIO_ADD_OFFSET            : {offsets_str}")
        if metadata_summary.true_colour_path:
            print(
                "   TRUE_COLOUR_IMAGE           :"
                f" {metadata_summary.true_colour_path}"
            )

        band_order = tuple(
            band for band in metadata_summary.preferred_order if band in extraction.band_paths
        )
        if len(band_order) != len(TRUECOLOUR_BANDS):
            band_order = tuple(TRUECOLOUR_BANDS.keys())
        if band_order != tuple(TRUECOLOUR_BANDS.keys()):
            display = ", ".join(band_order)
            print(f"   RGB Display Order (metadata): {display}")

        stacked, profile, transform, dtype = read_and_resample_bands(
            extraction.band_paths, band_order
        )

        if metadata_summary.radiometric_offsets or metadata_summary.quantification_value is not None:
            stacked = apply_radiometric_adjustments(
                stacked, band_order, dtype, metadata_summary
            )
            print("🎚️  Nilai band disesuaikan menggunakan metadata radiometrik.")

        crs = derive_crs(
            reference_band=extraction.band_paths["B04"],
            zip_name=config.zip_path.stem,
            metadata_path=extraction.metadata_path,
        )

        cog_tags = dict(metadata_fields)
        if metadata_summary.quantification_value is not None:
            cog_tags["QUANTIFICATION_VALUE"] = str(
                metadata_summary.quantification_value
            )
        if metadata_summary.radiometric_offsets:
            cog_tags["RADIO_ADD_OFFSET"] = ", ".join(
                f"{band}={offset}" for band, offset in sorted(
                    metadata_summary.radiometric_offsets.items()
                )
            )

        create_truecolour_cog(
            stacked_bands=stacked.astype(dtype, copy=False),
            profile=profile,
            transform=transform,
            crs=crs,
            output_path=output_path,
            metadata=cog_tags,
            band_order=band_order,
        )

    print("\n✅ File True Colour (COG) disimpan:", output_path)
    print(f"   CRS output: {crs}")
    print("   Resolusi: 10 m")
    print("   Format: Cloud-Optimized GeoTIFF")


if __name__ == "__main__":
    main()
