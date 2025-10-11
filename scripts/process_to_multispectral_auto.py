"""Utility untuk mengolah arsip Sentinel-2 Level-1C ataupun Level-2A menjadi
multispektral multiband (COG) dengan resolusi target 10 meter.

Script ini akan:
1. Membaca sumber (berkas .zip atau folder .SAFE) tanpa bergantung pada nama file.
2. Mengekstrak arsip .zip ke direktori sementara bila diperlukan.
3. Mendeteksi level produk (L1C / L2A) dari metadata internal.
4. Mengkumpulkan seluruh band spektral (B01 s/d B12) serta melakukan
   resampling ke grid 10 meter.
5. Menulis keluaran GeoTIFF berformat Cloud Optimized GeoTIFF (COG).
6. Membersihkan direktori sementara setelah proses selesai.

Konfigurasi dipasang langsung di dalam berkas melalui variabel
``USER_CONFIG`` di bawah. Sesuaikan path sumber, lokasi output, metode
resampling, serta opsi penimpaan file sebelum menjalankan script:

    python scripts/process_to_multispectral_auto.py
"""

from __future__ import annotations

import sys
import tempfile
import zipfile
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional

import numpy as np
import rasterio
from rasterio.enums import Resampling
from rasterio.crs import CRS
from rasterio.vrt import WarpedVRT

# Urutan band standar Sentinel-2
ORDERED_BANDS: List[str] = [
    "B01",
    "B02",
    "B03",
    "B04",
    "B05",
    "B06",
    "B07",
    "B08",
    "B8A",
    "B09",
    "B11",
    "B12",
]

# Resolusi native untuk tiap band
NATIVE_RESOLUTION: Dict[str, int] = {
    "B01": 60,
    "B02": 10,
    "B03": 10,
    "B04": 10,
    "B05": 20,
    "B06": 20,
    "B07": 20,
    "B08": 10,
    "B8A": 20,
    "B09": 60,
    "B11": 20,
    "B12": 20,
}

# Profil default Cloud Optimized GeoTIFF
COG_PROFILE = dict(
    driver="COG",
    compress="LZW",
    BIGTIFF="IF_SAFER",
    blockxsize=512,
    blockysize=512,
    NUM_THREADS="ALL_CPUS",
    OVERVIEWS="AUTO",
)


@dataclass
class ProcessingConfig:
    """Konfigurasi lengkap yang dibutuhkan selama pemrosesan."""

    source: Path
    output: Path
    overwrite: bool
    resampling: Resampling


USER_CONFIG = {
    "source": "/path/ke/produk.zip",  # Ganti dengan path sumber Sentinel-2 (.zip atau .SAFE)
    "output": None,  # Isi path keluaran atau biarkan None untuk otomatis
    "overwrite": False,  # Ubah ke True bila ingin menimpa file keluaran
    "resampling": "bilinear",  # Pilih metode resampling (mis. 'bilinear', 'cubic')
}


def build_config(config_data: Optional[Dict[str, object]] = None) -> ProcessingConfig:
    """Bangun :class:`ProcessingConfig` dari konfigurasi manual."""

    cfg = dict(USER_CONFIG)
    if config_data:
        cfg.update(config_data)

    source = Path(str(cfg["source"])).expanduser().resolve()

    output_raw = cfg.get("output")
    if output_raw in (None, "", False):
        suffix = "_multispectral.tif"
        output = source.with_name(f"{source.stem}{suffix}")
    else:
        output = Path(str(output_raw)).expanduser().resolve()

    if output.exists() and not cfg.get("overwrite", False):
        raise FileExistsError(
            f"Berkas keluaran {output} sudah ada. Set 'overwrite' ke True untuk menimpa."
        )

    resampling_name = str(cfg.get("resampling", "bilinear")).upper()
    if resampling_name not in Resampling.__members__:
        raise ValueError(
            "Nilai 'resampling' tidak dikenal. Pilih salah satu dari: "
            + ", ".join(name.lower() for name in Resampling.__members__.keys())
        )
    resampling = Resampling[resampling_name]

    print("📝 Konfigurasi aktif:")
    print(f"  • sumber: {source}")
    print(f"  • keluaran: {output}")
    print(f"  • overwrite: {cfg.get('overwrite', False)}")
    print(f"  • resampling: {resampling_name.lower()}")

    return ProcessingConfig(
        source=source,
        output=output,
        overwrite=bool(cfg.get("overwrite", False)),
        resampling=resampling,
    )


def ensure_safe_dir(source: Path) -> tuple[Path, Optional[tempfile.TemporaryDirectory]]:
    """Pastikan kita memiliki direktori .SAFE siap pakai.

    Jika ``source`` berupa arsip .zip, kita ekstrak ke direktori sementara dan
    mengembalikan path direktori .SAFE yang ditemukan.
    """

    if source.is_dir():
        print(f"📂 Sumber adalah direktori: {source}")
        if source.suffix.upper() == ".SAFE":
            return source, None
        # Cari direktori .SAFE di dalamnya
        safe_candidates = list(source.glob("*.SAFE"))
        if not safe_candidates:
            raise FileNotFoundError(
                "Tidak menemukan direktori .SAFE di dalam sumber yang diberikan."
            )
        print(f"🔍 Menemukan direktori .SAFE di dalam: {safe_candidates[0]}")
        return safe_candidates[0], None

    if source.suffix.lower() != ".zip":
        raise ValueError("Sumber harus berupa arsip .zip atau direktori .SAFE.")

    print(f"📦 Mengekstrak arsip: {source}")
    temp_dir = tempfile.TemporaryDirectory(prefix="s2_safe_")
    with zipfile.ZipFile(source, "r") as zf:
        zf.extractall(temp_dir.name)
    print(f"✅ Ekstraksi selesai ke: {temp_dir.name}")

    safe_candidates = list(Path(temp_dir.name).glob("*.SAFE"))
    if not safe_candidates:
        raise FileNotFoundError("Arsip tidak mengandung direktori .SAFE yang valid.")

    safe_dir = safe_candidates[0]
    print(f"📁 Menggunakan direktori produk: {safe_dir}")
    return safe_dir, temp_dir


def detect_product_level(safe_dir: Path) -> str:
    """Deteksi level produk (L1C atau L2A) berdasarkan metadata internal."""

    if (safe_dir / "MTD_MSIL1C.xml").exists():
        level = "L1C"
    elif (safe_dir / "MTD_MSIL2A.xml").exists():
        level = "L2A"
    else:
        level = "UNKNOWN"

    print(f"ℹ️  Level produk terdeteksi: {level}")
    return level


def collect_band_files(safe_dir: Path) -> Dict[str, Path]:
    """Kumpulkan path file JP2 untuk setiap band yang tersedia."""

    print("🔍 Mencari file band JP2...")
    jp2_files = sorted(safe_dir.glob("**/*.jp2"))
    band_map: Dict[str, Path] = {}

    def choose_band(patterns: List[str]) -> Optional[Path]:
        for jp2 in jp2_files:
            name = jp2.name.upper()
            if all(p in name for p in patterns):
                return jp2
        return None

    for band in ORDERED_BANDS:
        res = NATIVE_RESOLUTION[band]
        candidates = [
            choose_band([f"_{band}_", f"_{res}M"]),
            choose_band([f"_{band}."]),
            choose_band([f"_{band}_"]),
        ]
        band_file = next((c for c in candidates if c is not None), None)
        if band_file:
            band_map[band] = band_file
            print(f"  ✅ {band} → {band_file.relative_to(safe_dir)}")
        else:
            print(f"  ⚠️ {band} tidak ditemukan.")

    return band_map


def select_reference_band(band_map: Dict[str, Path]) -> Path:
    """Pilih band referensi 10 m untuk menentukan grid output."""

    for candidate in ("B04", "B02", "B08"):
        if candidate in band_map:
            print(f"🎯 Menggunakan {candidate} sebagai referensi grid 10 m.")
            return band_map[candidate]
    raise RuntimeError("Tidak menemukan band referensi 10 m (B04/B02/B08).")


def determine_output_crs(band_map: Dict[str, Path]) -> CRS:
    """Ambil CRS dari salah satu band sebagai acuan."""

    sample_band = next(iter(band_map.values()))
    with rasterio.open(sample_band) as ds:
        if ds.crs is None:
            raise RuntimeError("Tidak dapat menentukan CRS dari metadata raster.")
        print(f"📐 CRS keluaran: {ds.crs}")
        return ds.crs


def prepare_output_profile(ref_band_path: Path, band_count: int, output_crs: CRS) -> dict:
    """Bangun profil raster keluaran berdasarkan band referensi."""

    with rasterio.open(ref_band_path) as ref:
        profile = ref.profile.copy()
        profile.update(
            count=band_count,
            dtype=np.uint16,
            transform=ref.transform,
            crs=output_crs,
            width=ref.width,
            height=ref.height,
            **COG_PROFILE,
        )
    print("🧾 Profil keluaran siap disiapkan.")
    return profile


def write_multispectral(
    output_path: Path,
    profile: dict,
    band_map: Dict[str, Path],
    resampling: Resampling,
) -> None:
    """Tulis seluruh band ke dalam GeoTIFF multiband."""

    with rasterio.open(output_path, "w", **profile) as dst:
        out_index = 1
        for band in ORDERED_BANDS:
            src_path = band_map.get(band)
            if src_path is None:
                print(f"⚠️ Melewati band {band} karena tidak tersedia.")
                continue

            print(f"✏️  Menulis band {band} (sumber: {src_path.name})")
            with rasterio.open(src_path) as src:
                with WarpedVRT(
                    src,
                    crs=profile["crs"],
                    transform=profile["transform"],
                    width=profile["width"],
                    height=profile["height"],
                    resampling=resampling,
                ) as vrt:
                    for _, window in dst.block_windows(out_index):
                        data = vrt.read(1, window=window, out_dtype=profile["dtype"])
                        dst.write(data, out_index, window=window)

            dst.set_band_description(out_index, band)
            print(f"  ✅ Band {band} selesai ditulis ke layer {out_index}.")
            out_index += 1

        dst.update_tags(
            BAND_ORDER=",".join([band for band in ORDERED_BANDS if band in band_map]),
            NOTE="Produk Sentinel-2 multiband 10 m",
        )
    print(f"🎉 Penulisan selesai: {output_path}")


def main() -> int:
    """Fungsi utama script."""

    try:
        config = build_config()
        safe_dir, temp_dir = ensure_safe_dir(config.source)
        detect_product_level(safe_dir)
        band_map = collect_band_files(safe_dir)
        if not band_map:
            raise RuntimeError("Tidak menemukan band apapun di dalam produk.")
        ref_band = select_reference_band(band_map)
        output_crs = determine_output_crs(band_map)
        profile = prepare_output_profile(ref_band, len(band_map), output_crs)

        print(f"🛠  Menulis COG multispektral ke: {config.output}")
        write_multispectral(config.output, profile, band_map, config.resampling)

        print("🧹 Membersihkan sumber sementara...")
        if temp_dir is not None:
            temp_dir.cleanup()
            print("✅ Direktori sementara dihapus.")
        else:
            print("ℹ️  Tidak ada direktori sementara yang perlu dibersihkan.")

        print("✨ Proses selesai tanpa error.")
        return 0

    except Exception as exc:  # pylint: disable=broad-except
        print(f"❌ Terjadi kesalahan: {exc}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
