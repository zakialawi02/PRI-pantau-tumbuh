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

Contoh penggunaan:
    python scripts/process_to_multicpectral2.py \
        /path/ke/S2A_MSIL2A_20240218T021531_N0509_R046_T49MFM_20240218T050829.zip \
        output_multispectral.tif --target-crs EPSG:4326

Catatan:
- Script membutuhkan dependensi: rasterio, numpy.
- Untuk dataset yang memiliki lebih dari satu tile/granule, script akan memakai
  granule pertama yang ditemukan.
"""

from __future__ import annotations

import argparse
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


@dataclass
class ProcessingConfig:
    zip_path: Path
    output_path: Path
    target_crs: Optional[CRS]
    resampling: Resampling
    overwrite: bool


class ProcessingError(RuntimeError):
    """Kesalahan yang terjadi ketika memproses arsip."""


def parse_arguments(argv: Optional[Iterable[str]] = None) -> ProcessingConfig:
    parser = argparse.ArgumentParser(
        description=(
            "Konversi arsip Sentinel-2 Level-2A (.zip) menjadi citra multispektral "
            "multiband (B01-B12) dalam satu GeoTIFF/COG."
        )
    )
    parser.add_argument("zip_path", type=Path, help="Path ke arsip .zip Sentinel-2 Level-2A")
    parser.add_argument("output_path", type=Path, help="Path keluaran GeoTIFF/COG")
    parser.add_argument(
        "--target-crs",
        type=str,
        default=None,
        help="CRS keluaran (contoh: EPSG:4326). Jika tidak diisi, menggunakan CRS asli tile."
    )
    parser.add_argument(
        "--resampling",
        choices=[r.name.lower() for r in Resampling],
        default="bilinear",
        help="Metode resampling untuk penyeragaman resolusi (default: bilinear)."
    )
    parser.add_argument(
        "--overwrite",
        action="store_true",
        help="Timpa berkas keluaran jika sudah ada."
    )

    args = parser.parse_args(list(argv) if argv is not None else None)

    if not args.zip_path.exists():
        raise ProcessingError(f"Berkas zip tidak ditemukan: {args.zip_path}")
    if args.output_path.exists() and not args.overwrite:
        raise ProcessingError(
            f"Berkas keluaran sudah ada: {args.output_path}. Gunakan --overwrite untuk menimpa."
        )

    target_crs = CRS.from_user_input(args.target_crs) if args.target_crs else None
    resampling = Resampling[args.resampling.upper()]

    return ProcessingConfig(
        zip_path=args.zip_path,
        output_path=args.output_path,
        target_crs=target_crs,
        resampling=resampling,
        overwrite=args.overwrite,
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
    target_crs: Optional[CRS],
) -> Tuple[CRS, rasterio.Affine, int, int]:
    src_crs = reference_dataset.crs
    if src_crs is None:
        raise ProcessingError("CRS band referensi tidak ditemukan.")

    if target_crs is None or target_crs == src_crs:
        return src_crs, reference_dataset.transform, reference_dataset.width, reference_dataset.height

    transform, width, height = calculate_default_transform(
        src_crs,
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
) -> None:
    with rasterio.open(src_path) as src:
        if src.count != 1:
            raise ProcessingError(f"Berkas band bukan single-band: {src_path}")
        reproject(
            source=rasterio.band(src, 1),
            destination=dst_array,
            src_transform=src.transform,
            src_crs=src.crs,
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
        with rasterio.open(band_map[reference_band_code]) as ref_ds:
            target_crs, target_transform, target_width, target_height = build_target_grid(
                ref_ds, config.target_crs
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
        print(f"   CRS keluaran : {target_crs.to_string()}" if target_crs else "   CRS keluaran : (tidak diketahui)")
        print(f"   Resolusi     : {target_width} x {target_height} piksel")
    finally:
        if temp_dir and temp_dir.exists():
            try:
                shutil.rmtree(temp_dir)
                print(f"🧹 Folder sementara dihapus: {temp_dir}")
            except Exception as exc:  # pragma: no cover
                print(f"⚠️ Gagal menghapus folder sementara {temp_dir}: {exc}")


def main(argv: Optional[Iterable[str]] = None) -> None:
    try:
        config = parse_arguments(argv)
        process(config)
    except ProcessingError as exc:
        print(f"❌ Proses gagal: {exc}")
        sys.exit(1)
    except Exception as exc:
        print(f"❌ Terjadi kesalahan tak terduga: {exc}")
        sys.exit(2)


if __name__ == "__main__":
    main()
