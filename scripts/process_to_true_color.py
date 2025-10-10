import os
import zipfile
import rasterio
from rasterio.enums import Resampling
from rasterio.crs import CRS
import numpy as np
import xml.etree.ElementTree as ET
from glob import glob
import shutil
import re

# ===== KONFIGURASI =====
zip_path = "S2C_MSIL1C_20251001T022551_N0511_R046_T50MKD_20251001T051811.zip"
output_tif = "sentinel2L1_truecolor_10m_cog_auto_crs.tif"

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

# ===== 2. BACA METADATA SCENE =====
dirs = [os.path.join(unzip_dir, d) for d in os.listdir(unzip_dir)
        if os.path.isdir(os.path.join(unzip_dir, d))]
base_dir = dirs[0] if dirs else unzip_dir
xml_files = glob(os.path.join(base_dir, "**", "MTD_*.xml"), recursive=True)
metadata = {
    "SENSING_TIME": "Unknown",
    "PLATFORM": "Unknown",
    "PRODUCT_ID": "Unknown",
    "CLOUD_COVERAGE_ASSESSMENT": "Unknown"
}
if xml_files:
    xml_path = xml_files[0]
    tree = ET.parse(xml_path)
    root = tree.getroot()
    ns = {}
    for elem in root.iter():
        if elem.tag[0] == "{":
            uri = elem.tag[1:].split("}")[0]
            ns["n"] = uri
            break
    def get_text(path):
        node = root.find(path, ns)
        return node.text if node is not None else "Unknown"
    metadata["SENSING_TIME"] = get_text(".//n:SENSING_TIME")
    metadata["PLATFORM"] = get_text(".//n:SPACECRAFT_NAME")
    metadata["PRODUCT_ID"] = get_text(".//n:PRODUCT_URI")
    metadata["CLOUD_COVERAGE_ASSESSMENT"] = get_text(".//n:CLOUD_COVERAGE_ASSESSMENT")

print("\n🛰️ Metadata Scene:")
for k, v in metadata.items():
    print(f"   {k:<28}: {v}")

# ===== 3. PILIH BAND RGB (B04–B03–B02) =====
truecolor_bands = {"B04": "Red", "B03": "Green", "B02": "Blue"}
band_paths = sorted(glob(os.path.join(base_dir, "**", "*.jp2"), recursive=True))
band_files = {}
for bcode in truecolor_bands:
    for p in band_paths:
        if f"_{bcode}.jp2" in os.path.basename(p):
            band_files[bcode] = p
            break
if len(band_files) < 3:
    raise FileNotFoundError("Tidak semua band B02, B03, B04 ditemukan.")

print("\n🎨 Band yang dipakai:")
for b, p in band_files.items():
    print(f"   {b} ({truecolor_bands[b]}) → {os.path.basename(p)}")

# ===== 4. BACA BAND REFERENSI =====
ref_band_path = band_files["B04"]
with rasterio.open(ref_band_path) as ref:
    ref_shape = ref.shape
    ref_bounds = ref.bounds
    ref_transform = ref.transform
    ref_profile = ref.profile.copy()
    ref_crs = ref.crs

# ===== 5. TENTUKAN CRS (AUTO + FALLBACK) =====
crs_final = None
if ref_crs:
    crs_final = ref_crs
    print(f"📐 CRS asli terdeteksi dari raster: {crs_final}")
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
if crs_final is None:
    crs_final = CRS.from_wkt(
        'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],'
        'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]]'
    )
    print("⚠️ Gagal deteksi CRS, fallback ke EPSG:4326 (WGS84).")

# ===== 6. RESAMPLING BAND KE 10 m =====
bands_data = []
for bcode in ["B04", "B03", "B02"]:
    with rasterio.open(band_files[bcode]) as src:
        data = src.read(
            out_shape=(1, ref_shape[0], ref_shape[1]),
            resampling=Resampling.bilinear
        )[0]
        bands_data.append(data)

# ===== 7. SIMPAN COG TRUE COLOR =====
profile = ref_profile.copy()
profile.update(
    driver="COG",
    dtype=np.uint16,
    count=3,
    transform=ref_transform,
    crs=crs_final,
    compress="LZW",
    BIGTIFF="YES",
    blockxsize=512,
    blockysize=512
)
with rasterio.open(output_tif, "w", **profile) as dst:
    for i, bcode in enumerate(["B04", "B03", "B02"], start=1):
        dst.write(bands_data[i-1], i)
        dst.set_band_description(i, f"{truecolor_bands[bcode]} ({bcode})")
    dst.update_tags(
        **metadata,
        CRS_SOURCE=str(crs_final),
        RGB_COMPOSITION="B04-Red, B03-Green, B02-Blue",
        NOTE="True color composite disamakan ke 10m. CRS otomatis dari raster, fallback ke EPSG:4326 jika gagal."
    )

print(f"\n✅ File True Color (COG) disimpan: {output_tif}")
print(f"   CRS output: {crs_final}")
print(f"   Resolusi: 10 m")
print(f"   Format: Cloud-Optimized GeoTIFF")

# ===== 8. HAPUS FOLDER UNZIP =====
try:
    shutil.rmtree(unzip_dir)
    print(f"🧹 Folder hasil unzip dihapus: {unzip_dir}")
except Exception as e:
    print(f"⚠️ Gagal menghapus folder unzip: {e}")
