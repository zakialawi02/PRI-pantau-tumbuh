"""process_to_multicpectral2
=================================
Script untuk mengolah arsip Sentinel-2 Level-2A (format .zip dari Copernicus)
menjadi citra multispektral multiband (B01 s.d. B12) dalam satu berkas GeoTIFF/COG.

Fitur utama:
- Mengekstrak arsip .zip ke folder sementara (di direktori yang sama dengan arsip).
- Mengambil setiap band spektral (B01, B02, ..., B12, termasuk B8A) dan
  meresampel ke resolusi 10 m.
- Opsional reproyeksi ke CRS lain (default: CRS asli produk).
- Menulis keluaran sebagai Cloud Optimized GeoTIFF (COG) dengan penamaan band.
- Menghapus direktori ekstraksi sementara setelah proses selesai.

Cara pakai:
- Sesuaikan nilai pada kamus `MANUAL_CONFIG` di bawah (path arsip, keluaran, CRS, dll).
- Jalankan script ini: `python scripts/process_to_multicpectral2.py`.

Catatan:
- Script membutuhkan dependensi: rasterio, numpy.
- Untuk dataset yang memiliki lebih dari satu tile/granule, script akan memakai
  granule pertama yang ditemukan.
"""

from __future__ import annotations

import re
import shutil
import sys
import tempfile
import zipfile
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterable, List, Optional, Tuple

import numpy as np
import rasterio
from rasterio.crs import CRS
from rasterio.enums import Resampling
from rasterio.io import DatasetReader
from rasterio.warp import calculate_default_transform, reproject
import xml.etree.ElementTree as ET

# Urutan band yang diminta pengguna
ORDERED_BANDS: Tuple[str, ...] = (
    "B01",  # Coastal Aerosol (60 m)
    "B02",  # Blue (10 m)
    "B03",  # Green (10 m)
    "B04",  # Red (10 m)
    "B05",  # Red Edge 1 (20 m)
    "B06",  # Red Edge 2 (20 m)
    "B07",  # Red Edge 3 (20 m)
    "B08",  # NIR (10 m)
    "B8A",  # Narrow NIR (20 m)
    "B09",  # Water Vapour (60 m)
    "B11",  # SWIR 1 (20 m)
    "B12",  # SWIR 2 (20 m)
)

# Prioritas resolusi (10 m lebih disukai dibanding 20 m, dst)
RESOLUTION_PRIORITY: Dict[str, int] = {"10m": 0, "20m": 1, "60m": 2, "": 3}


# Pemetaan EPSG ke string PROJ untuk menghindari ketergantungan pada proj.db.
EPSG_PROJ_TEMPLATES: Dict[int, str] = {
    4326: "+proj=longlat +datum=WGS84 +no_defs +type=crs",
    3857: "+proj=merc +lon_0=0 +k=1 +x_0=0 +y_0=0 +datum=WGS84 +units=m +no_defs +type=crs",
}

_EPSG_PATTERN = re.compile(r"epsg(?::|::)?\s*(\d{4,5})", re.IGNORECASE)


# Cache pemetaan resampling agar pencarian nama bersifat case-insensitive.
RESAMPLING_LOOKUP: Dict[str, Resampling] = {
    name.lower(): member for name, member in Resampling.__members__.items()
}


# Konfigurasi manual (ubah sesuai kebutuhan sebelum menjalankan script)
MANUAL_CONFIG = {
    "zip_path": Path("./S2A_MSIL2A_20250923T023141_N0511_R046_T50MKD_20250923T073216.zip"),
    "output_path": Path("./output_multispektral.tif"),
    "target_crs": None,  # Contoh: "EPSG:4326"
    "resampling": "bilinear",
    "overwrite": False,
}


@dataclass
class ProcessingConfig:
    zip_path: Path
    output_path: Path
    target_crs: Optional[CRS]
    resampling: Resampling
    overwrite: bool


class ProcessingError(RuntimeError):
    """Kesalahan yang terjadi ketika memproses arsip."""


def _proj_string_from_epsg(code: int) -> Optional[str]:
    if code in EPSG_PROJ_TEMPLATES:
        return EPSG_PROJ_TEMPLATES[code]

    if 32601 <= code <= 32660:
        zone = code - 32600
        return f"+proj=utm +zone={zone} +datum=WGS84 +units=m +no_defs +type=crs"

    if 32701 <= code <= 32760:
        zone = code - 32700
        return f"+proj=utm +zone={zone} +south +datum=WGS84 +units=m +no_defs +type=crs"

    return None


def _crs_from_epsg_manual(code: int) -> Optional[CRS]:
    proj_string = _proj_string_from_epsg(code)
    if proj_string is None:
        return None
    try:
        return CRS.from_string(proj_string)
    except Exception:
        return None


def _extract_epsg(text: str) -> Optional[int]:
    match = _EPSG_PATTERN.search(text)
    if match:
        return int(match.group(1))
    digits = re.search(r"\b(\d{4,5})\b", text)
    if digits:
        try:
            return int(digits.group(1))
        except ValueError:
            return None
    return None


def _safe_crs_from_text(value: str, *, strict: bool = False) -> Optional[CRS]:
    text = value.strip()
    if not text:
        return None

    epsg_code = _extract_epsg(text)
    if epsg_code is not None:
        crs = _crs_from_epsg_manual(epsg_code)
        if crs is not None:
            return crs
        if strict:
            raise ProcessingError(f"EPSG {epsg_code} belum didukung oleh skrip ini.")

    if text.startswith("+proj"):
        try:
            return CRS.from_string(text)
        except Exception as exc:
            if strict:
                raise ProcessingError(f"CRS tidak valid: {value}") from exc
            return None

    if text[0] in "{<" or text.upper().startswith(("PROJCS", "GEOGCS", "ENGCRS", "VERTCS", "COMPD_CS")):
        try:
            return CRS.from_wkt(text)
        except Exception as exc:
            if strict:
                raise ProcessingError(f"CRS tidak valid: {value}") from exc
            return None

    if strict:
        raise ProcessingError(f"Tidak dapat mengenali format CRS: {value}")
    return None


def build_config() -> ProcessingConfig:
    """Bangun konfigurasi proses berdasarkan pengaturan manual."""

    def ensure_path(value: object, key: str) -> Path:
        if value is None:
            raise ProcessingError(f"Nilai {key!r} belum diisi pada MANUAL_CONFIG.")
        if isinstance(value, Path):
            return value.expanduser()
        if isinstance(value, str):
            return Path(value).expanduser()
        raise ProcessingError(f"Nilai {key!r} harus berupa string atau Path.")

    zip_path = ensure_path(MANUAL_CONFIG.get("zip_path"), "zip_path")
    output_path = ensure_path(MANUAL_CONFIG.get("output_path"), "output_path")

    target_input = MANUAL_CONFIG.get("target_crs")
    if target_input in (None, "", False):
        target_crs: Optional[CRS] = None
    elif isinstance(target_input, CRS):
        target_crs = target_input
    else:
        target_crs = _safe_crs_from_text(str(target_input), strict=True)

    resampling_raw = MANUAL_CONFIG.get("resampling", "bilinear")
    resampling_name = str(resampling_raw).lower()
    resampling = RESAMPLING_LOOKUP.get(resampling_name)
    if resampling is None:
        raise ProcessingError(
            "Metode resampling tidak dikenal: {}. Pilihan: {}".format(
                resampling_raw,
                ", ".join(sorted(RESAMPLING_LOOKUP.keys()))
            )
        )

    overwrite = bool(MANUAL_CONFIG.get("overwrite", False))

    if not zip_path.exists():
        raise ProcessingError(f"Berkas zip tidak ditemukan: {zip_path}")
    if output_path.exists() and not overwrite:
        raise ProcessingError(
            f"Berkas keluaran sudah ada: {output_path}. Atur overwrite=True untuk menimpa."
        )

    return ProcessingConfig(
        zip_path=zip_path,
        output_path=output_path,
        target_crs=target_crs,
        resampling=resampling,
        overwrite=overwrite,
    )


def extract_zip_to_temp(zip_path: Path) -> Path:
    """Ekstrak arsip zip ke folder sementara di direktori yang sama dengan arsip."""
    extract_parent = zip_path.parent
    temp_dir = Path(tempfile.mkdtemp(prefix=zip_path.stem + "_", dir=str(extract_parent)))

    with zipfile.ZipFile(zip_path, "r") as zf:
        zf.extractall(temp_dir)

    return temp_dir


def find_safe_root(extracted_dir: Path) -> Path:
    """Temukan direktori *.SAFE pada hasil ekstraksi."""
    for path in extracted_dir.iterdir():
        if path.suffix.lower() == ".safe" and path.is_dir():
            return path
    # Jika tidak ada ekstensi .SAFE, coba gunakan folder pertama
    candidates = [p for p in extracted_dir.iterdir() if p.is_dir()]
    if not candidates:
        raise ProcessingError("Tidak menemukan direktori .SAFE pada hasil ekstraksi.")
    return candidates[0]


def gather_band_files(safe_root: Path) -> Dict[str, Path]:
    """Kumpulkan berkas JP2 setiap band dengan memilih resolusi terbaik."""
    band_map: Dict[str, Path] = {}
    pattern = re.compile(r"_(B0[1-9]|B1[12]|B8A)(?:_(10m|20m|60m))?\.jp2$", re.IGNORECASE)

    for jp2_path in safe_root.rglob("*.jp2"):
        name = jp2_path.name
        if any(key in name.upper() for key in ("TCI", "PVI", "AOT", "SCL", "MSK", "QA")):
            continue
        match = pattern.search(name)
        if not match:
            continue
        band_code = match.group(1).upper()
        if band_code not in ORDERED_BANDS:
            continue
        resolution = match.group(2) or ""
        priority = RESOLUTION_PRIORITY.get(resolution, RESOLUTION_PRIORITY[""])
        prev = band_map.get(band_code)
        if prev is None:
            band_map[band_code] = jp2_path
        else:
            prev_match = pattern.search(prev.name)
            prev_res = prev_match.group(2) if prev_match else ""
            prev_priority = RESOLUTION_PRIORITY.get(prev_res or "", RESOLUTION_PRIORITY[""])
            if priority < prev_priority:
                band_map[band_code] = jp2_path

    missing = [band for band in ORDERED_BANDS if band not in band_map]
    if missing:
        raise ProcessingError(
            "Tidak menemukan berkas untuk band: " + ", ".join(missing)
        )

    return band_map


def read_metadata(safe_root: Path) -> Dict[str, str]:
    """Baca metadata utama dari MTD_MSIL2A.xml (best effort)."""
    metadata_path = safe_root / "MTD_MSIL2A.xml"
    if not metadata_path.exists():
        # fallback: cari file XML lain yang sesuai
        xml_candidates = list(safe_root.rglob("MTD_MSIL*.xml"))
        metadata_path = xml_candidates[0] if xml_candidates else None

    metadata: Dict[str, str] = {}
    if metadata_path is None:
        return metadata

    try:
        tree = ET.parse(metadata_path)
        root = tree.getroot()
        ns = {"n": root.tag.split("}")[0].strip("{")}

        def text(path: str, default: str = "") -> str:
            node = root.find(path, ns)
            return node.text.strip() if node is not None and node.text else default

        metadata = {
            "PRODUCT_URI": text(".//n:PRODUCT_URI"),
            "SENSING_TIME": text(".//n:SENSING_TIME"),
            "PROCESSING_LEVEL": text(".//n:PROCESSING_LEVEL"),
            "PROCESSING_BASELINE": text(".//n:PROCESSING_BASELINE"),
            "SPACECRAFT_NAME": text(".//n:SPACECRAFT_NAME"),
            "DATATAKE_ID": text(".//n:DATATAKE_ID"),
        }
    except Exception:
        # Metadata optional, abaikan bila gagal
        metadata = {}

    return metadata


def _crs_from_tags(tags: Dict[str, str]) -> Optional[CRS]:
    """Coba bangun CRS dari metadata tag rasterio."""

    for key in ("HORIZONTAL_CS_CODE", "horizontal_cs_code", "EPSG_CODE", "epsg_code", "crs"):
        value = tags.get(key)
        if value:
            crs = _safe_crs_from_text(value)
            if crs is not None:
                return crs
    return None


def _iterate_metadata_xml(band_path: Path, safe_root: Path) -> Iterable[Path]:
    """Hasilkan kandidat berkas XML yang mungkin menyimpan CRS."""

    seen = set()

    # Mulai dari folder band lalu naik ke atas untuk mencari metadata tile.
    for parent in band_path.parents:
        for pattern in ("MTD_TL*.xml", "MTD_L2A*.xml", "MTD_TD*.xml"):
            for candidate in parent.glob(pattern):
                if candidate not in seen and candidate.is_file():
                    seen.add(candidate)
                    yield candidate
        direct = parent / "MTD_TL.xml"
        if direct.exists() and direct not in seen:
            seen.add(direct)
            yield direct

    # Metadata utama SAFE.
    safe_xml = safe_root / "MTD_MSIL2A.xml"
    if safe_xml.exists() and safe_xml not in seen:
        seen.add(safe_xml)
        yield safe_xml


def _crs_from_xml(xml_path: Path) -> Optional[CRS]:
    """Parse CRS dari berkas metadata XML Sentinel."""

    try:
        tree = ET.parse(xml_path)
    except Exception:
        return None

    root = tree.getroot()
    for element in root.iter():
        local_name = element.tag.split("}")[-1]
        if local_name in {"HORIZONTAL_CS_CODE", "HORIZONTAL_CS_NAME", "Vertical_CS", "Projected_CRS"}:
            text = element.text.strip() if element.text else ""
            if not text:
                continue
            for candidate in (text, text.replace(" ", "")):
                crs = _safe_crs_from_text(candidate)
                if crs is not None:
                    return crs
    return None


TILE_ID_PATTERN = re.compile(r"T(\d{2})([A-Z]{3})")


def _tile_id_from_path(path: Path) -> Optional[str]:
    """Ambil tile-id Sentinel-2 dari nama berkas atau folder."""

    for part in path.parts:
        match = TILE_ID_PATTERN.search(part.upper())
        if match:
            return match.group(0)
    return None


def _crs_from_tile_id(tile_id: str) -> Optional[CRS]:
    """Hitung EPSG dari tile-id (TxxYYY)."""

    if len(tile_id) < 4:
        return None
    try:
        zone = int(tile_id[1:3])
    except ValueError:
        return None

    lat_band = tile_id[3]
    if not lat_band.isalpha():
        return None

    hemisphere_north = lat_band >= "N"
    epsg = 32600 + zone if hemisphere_north else 32700 + zone
    return _crs_from_epsg_manual(epsg)


def resolve_source_crs(
    reference_dataset: DatasetReader,
    band_path: Path,
    safe_root: Path,
) -> Tuple[CRS, str]:
    """Cari CRS sumber dengan berbagai strategi fallback."""

    if reference_dataset.crs:
        return reference_dataset.crs, "metadata JP2"

    tag_crs = _crs_from_tags(reference_dataset.tags())
    if tag_crs:
        return tag_crs, "tag JP2"

    for xml_path in _iterate_metadata_xml(band_path, safe_root):
        crs = _crs_from_xml(xml_path)
        if crs:
            return crs, f"metadata {xml_path.name}"

    tile_id = _tile_id_from_path(band_path) or _tile_id_from_path(safe_root)
    if tile_id:
        crs = _crs_from_tile_id(tile_id)
        if crs:
            return crs, f"tile-id {tile_id}"

    fallback = _crs_from_epsg_manual(4326)
    if fallback is None:
        raise ProcessingError("Fallback CRS EPSG:4326 tidak tersedia.")
    return fallback, "fallback EPSG:4326"


def choose_reference_band(band_map: Dict[str, Path]) -> str:
    """Pilih band referensi untuk grid 10 m (prioritas B02/B03/B04/B08)."""
    preferred = ["B02", "B03", "B04", "B08"]
    for band in preferred:
        if band in band_map:
            return band
    # fallback ke band pertama yang tersedia
    return ORDERED_BANDS[0]


def build_target_grid(
    reference_dataset: DatasetReader,
    source_crs: CRS,
    target_crs: Optional[CRS],
) -> Tuple[CRS, rasterio.Affine, int, int]:
    if target_crs is None or target_crs == source_crs:
        return source_crs, reference_dataset.transform, reference_dataset.width, reference_dataset.height

    transform, width, height = calculate_default_transform(
        source_crs,
        target_crs,
        reference_dataset.width,
        reference_dataset.height,
        *reference_dataset.bounds,
    )
    return target_crs, transform, width, height


def reproject_band(
    src_path: Path,
    dst_array: np.ndarray,
    dst_transform: rasterio.Affine,
    dst_crs: CRS,
    resampling: Resampling,
    source_crs: CRS,
) -> None:
    with rasterio.open(src_path) as src:
        if src.count != 1:
            raise ProcessingError(f"Berkas band bukan single-band: {src_path}")
        src_crs = src.crs or source_crs
        if src_crs is None:
            raise ProcessingError(f"CRS tidak diketahui untuk band {src_path.name}")
        reproject(
            source=rasterio.band(src, 1),
            destination=dst_array,
            src_transform=src.transform,
            src_crs=src_crs,
            dst_transform=dst_transform,
            dst_crs=dst_crs,
            resampling=resampling,
            num_threads=2,
        )


def convert_float_to_uint16(array: np.ndarray) -> np.ndarray:
    array = np.nan_to_num(array, nan=0.0, copy=False)
    array = np.clip(array, 0, np.iinfo(np.uint16).max)
    return np.rint(array).astype(np.uint16)


def write_output(
    output_path: Path,
    bands_data: List[np.ndarray],
    band_names: List[str],
    transform: rasterio.Affine,
    crs: CRS,
    metadata: Dict[str, str],
) -> None:
    dtype = np.uint16
    profile = {
        "driver": "COG",
        "dtype": dtype,
        "count": len(bands_data),
        "height": bands_data[0].shape[0],
        "width": bands_data[0].shape[1],
        "transform": transform,
        "crs": crs,
        "nodata": 0,
        "compress": "LZW",
        "blockxsize": 512,
        "blockysize": 512,
        "BIGTIFF": "IF_SAFER",
    }

    with rasterio.open(output_path, "w", **profile) as dst:
        for index, (band_name, data) in enumerate(zip(band_names, bands_data), start=1):
            dst.write(data.astype(dtype), index)
            dst.set_band_description(index, band_name)
        if metadata:
            dst.update_tags(**metadata)
        dst.update_tags(BAND_ORDER=",".join(band_names))


def process(config: ProcessingConfig) -> None:
    temp_dir: Optional[Path] = None
    try:
        temp_dir = extract_zip_to_temp(config.zip_path)
        safe_root = find_safe_root(temp_dir)
        band_map = gather_band_files(safe_root)
        metadata = read_metadata(safe_root)

        reference_band_code = choose_reference_band(band_map)
        reference_path = band_map[reference_band_code]
        with rasterio.open(reference_path) as ref_ds:
            source_crs, crs_origin = resolve_source_crs(ref_ds, reference_path, safe_root)
            target_crs, target_transform, target_width, target_height = build_target_grid(
                ref_ds, source_crs, config.target_crs
            )

        bands_output: List[np.ndarray] = []
        for band in ORDERED_BANDS:
            src_path = band_map[band]
            destination = np.zeros((target_height, target_width), dtype=np.float32)
            reproject_band(
                src_path=src_path,
                dst_array=destination,
                dst_transform=target_transform,
                dst_crs=target_crs,
                resampling=config.resampling,
                source_crs=source_crs,
            )
            bands_output.append(convert_float_to_uint16(destination))

        write_output(
            output_path=config.output_path,
            bands_data=bands_output,
            band_names=list(ORDERED_BANDS),
            transform=target_transform,
            crs=target_crs,
            metadata=metadata,
        )

        print(f"✅ Multispektral berhasil dibuat: {config.output_path}")
        print(f"   CRS sumber   : {source_crs.to_string()} ({crs_origin})")
        print(f"   CRS keluaran : {target_crs.to_string()}" if target_crs else "   CRS keluaran : (tidak diketahui)")
        print(f"   Resolusi     : {target_width} x {target_height} piksel")
    finally:
        if temp_dir and temp_dir.exists():
            try:
                shutil.rmtree(temp_dir)
                print(f"🧹 Folder sementara dihapus: {temp_dir}")
            except Exception as exc:  # pragma: no cover
                print(f"⚠️ Gagal menghapus folder sementara {temp_dir}: {exc}")


def main() -> None:
    try:
        config = build_config()
        process(config)
    except ProcessingError as exc:
        print(f"❌ Proses gagal: {exc}")
        sys.exit(1)
    except Exception as exc:
        print(f"❌ Terjadi kesalahan tak terduga: {exc}")
        sys.exit(2)


if __name__ == "__main__":
    main()
