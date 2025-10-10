import os
import zipfile
import rasterio
from rasterio.enums import Resampling
from rasterio.warp import calculate_default_transform, reproject
from rasterio.crs import CRS
import numpy as np
import xml.etree.ElementTree as ET
from glob import glob
import shutil
import re

# ===== KONFIGURASI =====
zip_path = "S2C_MSIL1C_20251001T022551_N0511_R046_T50MKD_20251001T051811.zip"
output_tif = "sentinel2L1_multispectral_10m_cog_auto_crs.tif"

# ===== 1. EKSTRAKSI ZIP =====
zip_dir = os.path.dirname(os.path.abspath(zip_path))
zip_name = os.path.splitext(os.path.basename(zip_path))[0]
unzip_dir = os.path.join(zip_dir, f"{zip_name}_unzip")

if os.path.exists(unzip_dir):
    shutil.rmtree(unzip_dir)
os.makedirs(unzip_dir, exist_ok=True)

print(f"📂 Mengekstrak ke: {unzip_dir}")
with zipfile.ZipFile(zip_path, 'r') as z:
    z.extractall(unzip_dir)

# ===== 2. URUTAN BAND (B01–B12) =====
ordered_bands = [
    "B01",  # 1  Coastal Aerosol
    "B02",  # 2  Blue
    "B03",  # 3  Green
    "B04",  # 4  Red
    "B05",  # 5  Red Edge 1
    "B06",  # 6  Red Edge 2
    "B07",  # 7  Red Edge 3
    "B08",  # 8  NIR
    "B8A",  # 9  Red Edge 4 (Narrow NIR)
    "B09",  # 10 Water Vapor
    "B11",  # 11 SWIR 1
    "B12"   # 12 SWIR 2
]

# ===== 3. TEMUKAN FILE BAND =====
band_paths = sorted(glob(os.path.join(unzip_dir, "**", "*.jp2"), recursive=True))
band_files = {}
for bcode in ordered_bands:
    for p in band_paths:
        if f"_{bcode}.jp2" in os.path.basename(p):
            band_files[bcode] = p
            break

missing = [b for b in ordered_bands if b not in band_files]
if missing:
    print(f"⚠️ Beberapa band tidak ditemukan: {missing}")

print("\n🎨 Urutan band yang dipakai:")
for i, b in enumerate(ordered_bands, 1):
    print(f"   {i:02d}. {b} → {os.path.basename(band_files.get(b, '❌ Tidak ditemukan'))}")

# ===== 4. BACA BAND REFERENSI =====
ref_band_path = band_files.get("B04") or band_files.get("B02") or band_files.get("B08")
if ref_band_path is None:
    raise RuntimeError("Tidak ditemukan band referensi 10m (B02/B04/B08).")

with rasterio.open(ref_band_path) as ref:
    ref_shape = ref.shape
    ref_bounds = ref.bounds
    ref_transform = ref.transform
    ref_profile = ref.profile.copy()
    ref_crs = ref.crs

# ===== 5. TENTUKAN CRS (AUTO + FALLBACK) =====
crs_final = None

# 5a. Jika file punya CRS asli
if ref_crs:
    crs_final = ref_crs
    print(f"📐 CRS asli terdeteksi dari raster: {crs_final}")

# 5b. Jika tidak ada CRS, deteksi UTM dari nama tile
if crs_final is None:
    match = re.search(r'T(\d{2})([A-Z]{3})', zip_name)
    if match:
        utm_zone = int(match.group(1))
        utm_hemisphere = "S" if utm_zone >= 30 else "N"
        print(f"🗺️ CRS tidak ada, fallback ke UTM zone {utm_zone}{utm_hemisphere}")
        crs_final = CRS.from_wkt(
            f'PROJCS["WGS 84 / UTM zone {utm_zone}{utm_hemisphere}",'
            'GEOGCS["WGS 84",DATUM["WGS_1984",'
            'SPHEROID["WGS 84",6378137,298.257223563]],'
            'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]],'
            f'PROJECTION["Transverse_Mercator"],PARAMETER["latitude_of_origin",0],'
            f'PARAMETER["central_meridian",{(utm_zone - 1)*6 - 180 + 3}],'
            'PARAMETER["scale_factor",0.9996],PARAMETER["false_easting",500000],'
            f'PARAMETER["false_northing",{10000000 if utm_hemisphere=="S" else 0}],UNIT["metre",1]]'
        )

# 5c. Jika tetap gagal → fallback terakhir ke EPSG:4326
if crs_final is None:
    crs_final = CRS.from_wkt(
        'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],'
        'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]]'
    )
    print("⚠️ Gagal deteksi CRS, fallback ke EPSG:4326 (WGS84).")

# ===== 6. RESAMPLING SEMUA BAND KE 10 m =====
bands_data = []
for bcode in ordered_bands:
    path = band_files.get(bcode)
    if path is None:
        print(f"⚠️ Melewati {bcode} (tidak ditemukan).")
        continue
    with rasterio.open(path) as src:
        data = src.read(
            out_shape=(1, ref_shape[0], ref_shape[1]),
            resampling=Resampling.bilinear
        )[0]
        bands_data.append(data)

# ===== 7. SIMPAN SEBAGAI COG  =====
profile = ref_profile.copy()
profile.update(
    driver="COG",
    dtype=np.uint16,
    count=len(bands_data),
    transform=ref_transform,
    crs=crs_final,
    compress="LZW",
    BIGTIFF="YES",
    blockxsize=512,
    blockysize=512
)

with rasterio.open(output_tif, "w", **profile) as dst:
    for i, bcode in enumerate(ordered_bands, start=1):
        if i <= len(bands_data):
            dst.write(bands_data[i - 1], i)
            dst.set_band_description(i, f"{bcode} (Band {i})")
    dst.update_tags(
        REPROJECTED_TO=str(crs_final),
        BAND_ORDER=",".join(ordered_bands),
        NOTE="Semua band Sentinel-2 disamakan resolusi ke 10m. CRS diambil otomatis dari raster JP2, fallback ke EPSG:4326 jika gagal."
    )

print(f"\n✅ File multispectral (COG) disimpan: {output_tif}")
print(f"   CRS output: {crs_final}")
print(f"   Jumlah band: {len(bands_data)} (B01–B12)")
print(f"   Resolusi: 10 m")

# ===== 8. HAPUS FOLDER UNZIP =====
try:
    shutil.rmtree(unzip_dir)
    print(f"🧹 Folder hasil unzip dihapus: {unzip_dir}")
except Exception as e:
    print(f"⚠️ Gagal menghapus folder unzip: {e}")
