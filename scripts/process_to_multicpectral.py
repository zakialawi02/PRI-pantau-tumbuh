import os
import time
import gc
import zipfile
import shutil
import re
from glob import glob
import numpy as np
import rasterio
from rasterio.enums import Resampling
from rasterio.crs import CRS
from rasterio.vrt import WarpedVRT
from rasterio.windows import Window

# ========= KONFIGURASI =========
zip_path = "S2C_MSIL1C_20251001T022551_N0511_R046_T50MKD_20251001T051811.zip"
output_tif = "sentinel2L1_multispectral_10m_cog_auto_crs.tif"

COG_PROFILE = dict(
    driver="COG",
    compress="LZW",
    BIGTIFF="YES",
    blockxsize=512,
    blockysize=512,
    NUM_THREADS="ALL_CPUS",
    OVERVIEWS="AUTO"  # biarkan GDAL tentukan level overviews
)

# Urutan band standar S2
ORDERED_BANDS = [
    "B01","B02","B03","B04","B05","B06","B07","B08","B8A","B09","B11","B12"
]

# Mapping resolusi native (m)
NATIVE_RES = {
    "B01": 60, "B02": 10, "B03": 10, "B04": 10,
    "B05": 20, "B06": 20, "B07": 20, "B08": 10,
    "B8A": 20, "B09": 60, "B11": 20, "B12": 20
}

# ========= UTIL =========
def clean_dir(path):
    if os.path.exists(path):
        shutil.rmtree(path)
    os.makedirs(path, exist_ok=True)

def mgrs_hemisphere_from_lat_band(letter: str) -> str:
    """
    MGRS latitude band letters:
      C–M: Southern Hemisphere, N–X: Northern (I,O tidak dipakai)
    """
    letter = letter.upper()
    southern = "CDEFGHJKLM"
    northern = "NPQRSTUVWX"
    if letter in southern:
        return "S"
    if letter in northern:
        return "N"
    return None

def infer_crs_from_zipname(zip_name: str) -> CRS | None:
    # Contoh: ..._T50MKD_... => zone=50, latband='M'
    m = re.search(r"T(\d{2})([A-Z])([A-Z]{2})", zip_name)  # zona, lat band, 100km grid
    if not m:
        return None
    zone = int(m.group(1))
    lat_band = m.group(2)
    hemi = mgrs_hemisphere_from_lat_band(lat_band)
    if not hemi:
        return None
    central_meridian = (zone - 1) * 6 - 180 + 3
    false_north = 10000000 if hemi == "S" else 0
    proj4 = (
        f"+proj=utm +zone={zone} +datum=WGS84 +units=m +no_defs "
        + ("+south " if hemi == "S" else "")
    )
    try:
        return CRS.from_proj4(proj4)
    except Exception:
        # Fallback WKT jika perlu
        return CRS.from_wkt(
            f'PROJCS["WGS 84 / UTM zone {zone}{hemi}",'
            'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],'
            'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]],'
            'PROJECTION["Transverse_Mercator"],'
            'PARAMETER["latitude_of_origin",0],'
            f'PARAMETER["central_meridian",{central_meridian}],'
            'PARAMETER["scale_factor",0.9996],'
            'PARAMETER["false_easting",500000],'
            f'PARAMETER["false_northing",{false_north}],UNIT["metre",1]]'
        )

def list_band_files(unzip_dir: str) -> dict:
    """
    Cari file JP2 tiap band, prefer jalur resmi S2 L1C:
      .../GRANULE/*/IMG_DATA/R10m/*.jp2, R20m, R60m
    Kalau penamaan beda (variasi _B02_10m.jp2 vs _B02.jp2), kita toleran.
    """
    jp2s = sorted(glob(os.path.join(unzip_dir, "**", "*.jp2"), recursive=True))
    by_band = {}

    def choose_first(patterns):
        for p in jp2s:
            name = os.path.basename(p)
            for pat in patterns:
                if pat in name:
                    return p
        return None

    for b in ORDERED_BANDS:
        res = NATIVE_RES[b]
        # Pola umum baru: _B02_10m.jp2; pola lama: _B02.jp2 (di folder R10m)
        patterns = [f"_{b}_{res}m.jp2", f"_{b}.jp2"]
        path = choose_first(patterns)
        if path is None:
            # fallback: deteksi via folder resolusi
            for p in jp2s:
                if f"_{b}_" in os.path.basename(p) or f"_{b}.jp2" in os.path.basename(p):
                    path = p
                    break
        if path:
            by_band[b] = path
    return by_band

# ========= 1) EKSTRAK ZIP =========
zip_dir = os.path.dirname(os.path.abspath(zip_path))
zip_name = os.path.splitext(os.path.basename(zip_path))[0]
unzip_dir = os.path.join(zip_dir, f"{zip_name}_unzip")
clean_dir(unzip_dir)

print(f"📂 Mengekstrak ke: {unzip_dir}")
with zipfile.ZipFile(zip_path, "r") as zf:
    zf.extractall(unzip_dir)

# ========= 2) TEMUKAN FILE BAND =========
band_files = list_band_files(unzip_dir)
missing = [b for b in ORDERED_BANDS if b not in band_files]
if missing:
    print(f"⚠️ Band tidak ditemukan: {missing}")

print("\n🎨 Urutan band & file:")
for i, b in enumerate(ORDERED_BANDS, 1):
    print(f"  {i:02d}. {b}: {os.path.basename(band_files.get(b, '❌ NOT FOUND'))}")

# ========= 3) GRID REFERENSI 10 m =========
ref_code_candidates = ["B04", "B02", "B08"]
ref_path = next((band_files[c] for c in ref_code_candidates if c in band_files), None)
if not ref_path:
    raise RuntimeError("Tidak ditemukan band referensi 10 m (B04/B02/B08).")

with rasterio.open(ref_path) as ref:
    ref_crs = ref.crs
    ref_transform = ref.transform
    ref_width = ref.width
    ref_height = ref.height
    ref_profile = ref.profile.copy()

# ========= 4) CRS FINAL (AUTO + FALLBACK MGRS) =========
crs_final = ref_crs
if not crs_final:
    crs_final = infer_crs_from_zipname(zip_name)
if not crs_final:
    print("⚠️ Gagal infer dari MGRS, fallback ke EPSG:4326.")
    crs_final = CRS.from_epsg(4326)

print(f"📐 CRS output: {crs_final}")

# ========= 5) TULIS COG MULTIBAND (WINDOWED) =========
dtype = np.uint16  # L1C scaling 0..10000
band_count = len(ORDERED_BANDS)
profile = ref_profile.copy()
profile.update(
    dtype=dtype,
    count=sum(1 for b in ORDERED_BANDS if b in band_files),
    transform=ref_transform,
    crs=crs_final,
    width=ref_width,
    height=ref_height,
    **COG_PROFILE
)

# Siapkan writer
with rasterio.open(output_tif, "w", **profile) as dst:
    out_band_index = 1

    for bcode in ORDERED_BANDS:
        jp2 = band_files.get(bcode)
        if not jp2:
            print(f"⚠️ Melewati {bcode} (file tidak ditemukan).")
            continue

        # Set resampling: bilinear utk kontinyu
        resamp = Resampling.bilinear

        with rasterio.open(jp2) as src:
            # Pastikan CRS/transform target = grid referensi 10 m
            # WarpedVRT akan *on-the-fly* reproject/resize ke grid target
            vrt = WarpedVRT(
                src,
                crs=crs_final,
                transform=ref_transform,
                width=ref_width,
                height=ref_height,
                resampling=resamp
            )

            # Tulis per window agar hemat memori
            for ji, window in dst.block_windows(out_band_index):
                data = vrt.read(1, window=window, out_dtype=dtype)
                dst.write(data, out_band_index, window=window)

        dst.set_band_description(out_band_index, f"{bcode}")
        print(f"✅ Tulis band {bcode} → layer {out_band_index}")
        out_band_index += 1

    # Tag metadata
    dst.update_tags(
        BAND_ORDER=",".join([b for b in ORDERED_BANDS if b in band_files]),
        NOTE="Semua band Sentinel-2 L1C diraster ke grid 10m referensi; COG dengan overviews."
    )

print("\n🎉 Selesai!")
print(f"🗂 Output COG: {output_tif}")
print(f"📏 Resolusi target: 10 m")
print(f"📦 Jumlah band tertulis: {profile['count']}")

# ========= 6) BERSIHKAN UNZIP =========
print("\n🧹 Menutup file & membersihkan...")

# pastikan semua file raster & VRT sudah dilepas
gc.collect()
time.sleep(1.0)  # beri waktu OS menutup file handle

for _ in range(3):  # coba hingga 3x
    try:
        shutil.rmtree(unzip_dir)
        print(f"✅ Folder unzip dihapus: {unzip_dir}")
        break
    except PermissionError as e:
        print(f"⚠️ Folder masih terkunci, coba lagi dalam 1 detik...")
        time.sleep(1.0)
else:
    print(f"⚠️ Gagal hapus folder unzip setelah 3 percobaan.")
