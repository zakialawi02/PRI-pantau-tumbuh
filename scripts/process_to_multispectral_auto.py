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
import xml.etree.ElementTree as ET
from urllib.error import URLError
from urllib.request import urlopen

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

    resampling_name = str(cfg.get("resampling", "bilinear")).lower()
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
    print(f"  • resampling: {resampling_name}")

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
    temp_dir = tempfile.TemporaryDirectory(prefix="s2_safe_", dir=str(source.parent))
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


def determine_output_crs(band_map: Dict[str, Path], safe_dir: Path) -> CRS:
    """Ambil CRS dari band, metadata, nama file, atau fallback standar."""

    sample_band = next(iter(band_map.values()))
    with rasterio.open(sample_band) as ds:
        if ds.crs:
            print(f"📐 CRS keluaran (langsung dari band): {ds.crs}")
            return ds.crs

    print("⚠️ CRS tidak ditemukan langsung pada band. Mencari dari metadata...")
    metadata_file = locate_metadata_file(safe_dir)
    if metadata_file is not None:
        epsg_from_meta = epsg_from_metadata(metadata_file)
        if epsg_from_meta is not None:
            crs = build_crs_from_epsg(epsg_from_meta)
            if crs is not None:
                print(f"📐 CRS keluaran (metadata EPSG:{epsg_from_meta}): {crs}")
                return crs
            print(
                "⚠️ Kode EPSG dari metadata ditemukan tetapi tidak dapat dibangun dari referensi lokal."
            )
        else:
            print("⚠️ Metadata tidak mengandung kode EPSG eksplisit.")
    else:
        print("⚠️ Metadata utama tidak ditemukan, melanjutkan ke heuristik nama file.")

    print("🔤 Mencoba menurunkan EPSG dari nama file JP2...")
    epsg_from_name = infer_epsg_from_band_name(sample_band.name)
    if epsg_from_name is not None:
        crs = build_crs_from_epsg(epsg_from_name)
        if crs is not None:
            print(f"📐 CRS keluaran (nama file EPSG:{epsg_from_name}): {crs}")
            return crs
        print("⚠️ EPSG dari nama file diketahui tetapi gagal dibangun.")
    else:
        print("⚠️ Tidak dapat menurunkan kode EPSG dari nama file.")

    print("ℹ️  Menggunakan fallback EPSG:4326 (WGS84 lat/lon).")
    fallback = build_crs_from_epsg(4326)
    if fallback is None:
        raise RuntimeError("Tidak dapat membangun CRS fallback EPSG:4326.")
    return fallback


def locate_metadata_file(safe_dir: Path) -> Optional[Path]:
    """Cari file metadata utama Sentinel-2."""

    preferred = [
        safe_dir / "MTD_MSIL1C.xml",
        safe_dir / "MTD_MSIL2A.xml",
    ]
    for candidate in preferred:
        if candidate.exists():
            print(f"🔎 Menggunakan metadata: {candidate.name}")
            return candidate

    for candidate in safe_dir.rglob("MTD_*.xml"):
        print(f"🔎 Menggunakan metadata: {candidate.name}")
        return candidate

    print("⚠️ Metadata utama tidak ditemukan di direktori produk.")
    return None


def epsg_from_metadata(metadata_file: Path) -> Optional[int]:
    """Ambil kode EPSG dari metadata XML Sentinel-2."""

    try:
        root = ET.parse(metadata_file).getroot()
    except ET.ParseError as exc:
        print(f"⚠️ Gagal membaca metadata {metadata_file.name}: {exc}")
        return None

    def extract_epsg(text: str) -> Optional[int]:
        text = text.strip()
        if not text:
            return None
        upper = text.upper()
        if "EPSG" in upper and ":" in text:
            candidate = text.split(":")[-1]
        else:
            candidate = text
        digits = "".join(ch for ch in candidate if ch.isdigit())
        if not digits:
            return None
        return int(digits)

    possible_codes: List[int] = []
    keys = ("HORIZONTAL_CS_CODE", "HORIZONTAL_CS_NAME", "EPSG")
    for elem in root.iter():
        if not elem.text:
            continue
        tag_upper = elem.tag.upper()
        text_upper = elem.text.upper()
        if any(key in tag_upper for key in keys) or "EPSG" in text_upper:
            epsg = extract_epsg(elem.text)
            if epsg is not None:
                possible_codes.append(epsg)

    if not possible_codes:
        print("⚠️ Tidak menemukan kode EPSG di metadata.")
        return None

    return possible_codes[0]


def build_crs_from_epsg(epsg_code: int) -> Optional[CRS]:
    """Bangun CRS dari kode EPSG dengan fallback daring dan hardcode."""

    try:
        crs = CRS.from_epsg(epsg_code)
        print(f"   ↪️  CRS dibangun dari proj.db (EPSG:{epsg_code}).")
        return crs
    except Exception as exc:  # pylint: disable=broad-except
        print(f"⚠️ Gagal memuat EPSG:{epsg_code} dari proj.db: {exc}")

    print("   ↪️  Mencoba mengambil definisi dari epsg.io...")
    try:
        with urlopen(f"https://epsg.io/{epsg_code}.proj4", timeout=10) as response:
            proj_string = response.read().decode("utf-8").strip()
        if proj_string:
            crs = CRS.from_proj4(proj_string)
            print("   ↪️  CRS dibangun dari definisi epsg.io.")
            return crs
    except (URLError, TimeoutError, ValueError) as exc:
        print(f"⚠️ Tidak dapat mengambil definisi EPSG:{epsg_code} dari internet: {exc}")
    except Exception as exc:  # pylint: disable=broad-except
        print(f"⚠️ Kesalahan tidak terduga saat mengambil EPSG:{epsg_code}: {exc}")

    hardcoded = hardcoded_epsg_definition(epsg_code)
    if hardcoded is not None:
        print("   ↪️  Menggunakan definisi CRS hardcode.")
        return hardcoded

    print(f"⚠️ Tidak ada definisi CRS yang tersedia untuk EPSG:{epsg_code}.")
    return None


def hardcoded_epsg_definition(epsg_code: int) -> Optional[CRS]:
    """Berikan CRS hardcode untuk kode EPSG umum Sentinel-2."""

    if 32601 <= epsg_code <= 32660:
        zone = epsg_code - 32600
        proj4 = f"+proj=utm +zone={zone} +datum=WGS84 +units=m +no_defs +type=crs"
        return CRS.from_proj4(proj4)
    if 32701 <= epsg_code <= 32760:
        zone = epsg_code - 32700
        proj4 = (
            f"+proj=utm +zone={zone} +datum=WGS84 +units=m +no_defs +south +type=crs"
        )
        return CRS.from_proj4(proj4)
    if epsg_code == 4326:
        return CRS.from_proj4("+proj=longlat +datum=WGS84 +no_defs +type=crs")
    return None


def infer_epsg_from_band_name(filename: str) -> Optional[int]:
    """Turunkan kode EPSG berdasarkan pola nama file Sentinel-2."""

    upper = filename.upper()
    if "T" not in upper:
        return None

    parts = upper.split("_")
    tile_token = next((part for part in parts if part.startswith("T") and len(part) >= 3), None)
    if tile_token is None:
        return None

    digits = "".join(ch for ch in tile_token[1:] if ch.isdigit())
    if len(digits) < 2:
        return None

    try:
        zone = int(digits[:2])
    except ValueError:
        return None

    if zone < 1 or zone > 60:
        return None

    latitude_band = tile_token[3] if len(tile_token) > 3 else ""
    if latitude_band:
        if "C" <= latitude_band <= "M":
            return 32700 + zone
        if "N" <= latitude_band <= "X":
            return 32600 + zone

    # Default asumsi lintang utara bila tidak yakin.
    return 32600 + zone


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
        output_crs = determine_output_crs(band_map, safe_dir)
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
