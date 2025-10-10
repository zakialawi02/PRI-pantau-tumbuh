import os, re, zipfile, shutil
import numpy as np
import rasterio
from rasterio.enums import Resampling
from rasterio.warp import calculate_default_transform, reproject
from rasterio.crs import CRS
import xml.etree.ElementTree as ET
from glob import glob

# ===== KONFIGURASI =====
zip_path = "S2A_MSIL2A_20250923T023141_N0511_R046_T50MKD_20250923T073216.zip"
output_tif = "sentinel2L2_multispectral_10m_cog_4326.tif"

# ===== 1) EKSTRAK DI FOLDER YANG SAMA =====
zip_dir = os.path.dirname(os.path.abspath(zip_path))
zip_name = os.path.splitext(os.path.basename(zip_path))[0]
unzip_dir = os.path.join(zip_dir, f"{zip_name}_unzip")
if os.path.exists(unzip_dir):
    shutil.rmtree(unzip_dir)
os.makedirs(unzip_dir, exist_ok=True)

print(f"📂 Mengekstrak ke: {unzip_dir}")
with zipfile.ZipFile(zip_path, 'r') as z:
    z.extractall(unzip_dir)

# ===== 2) METADATA (opsional, best-effort) =====
dirs = [os.path.join(unzip_dir, d) for d in os.listdir(unzip_dir) if os.path.isdir(os.path.join(unzip_dir, d))]
base_dir = dirs[0] if dirs else unzip_dir
xml_files = glob(os.path.join(base_dir, "**", "MTD_MSIL*.xml"), recursive=True)

metadata = {k:"Unknown" for k in
            ["SENSING_TIME","PLATFORM","PRODUCT_ID","PROCESSING_BASELINE",
             "DATASTRIP_ID","TILE_ID","CLOUD_COVERAGE_ASSESSMENT"]}

if xml_files:
    try:
        tree = ET.parse(xml_files[0]); root = tree.getroot()
        ns = {}
        for elem in root.iter():
            if elem.tag.startswith("{"):
                ns["n"] = elem.tag[1:].split("}")[0]; break
        def get_text(path):
            node = root.find(path, ns)
            return node.text if node is not None else "Unknown"
        metadata["SENSING_TIME"] = get_text(".//n:SENSING_TIME")
        metadata["PLATFORM"] = get_text(".//n:SPACECRAFT_NAME")
        metadata["PRODUCT_ID"] = get_text(".//n:PRODUCT_URI")
        metadata["PROCESSING_BASELINE"] = get_text(".//n:PROCESSING_BASELINE")
        metadata["DATASTRIP_ID"] = get_text(".//n:DATASTRIP_ID")
        metadata["TILE_ID"] = get_text(".//n:TILE_ID")
        metadata["CLOUD_COVERAGE_ASSESSMENT"] = get_text(".//n:CLOUD_COVERAGE_ASSESSMENT")
    except Exception as e:
        print(f"⚠️ Gagal baca metadata: {e}")

print("\n🛰️ Metadata Scene:")
for k, v in metadata.items(): print(f"   {k:<28}: {v}")

# ===== 3) KUMPULKAN FILE BAND (mendukung pola L2A: _Bxx_10m/20m/60m.jp2) =====
all_jp2 = sorted(glob(os.path.join(base_dir, "**", "*.jp2"), recursive=True))
re_band = re.compile(r'_(B0[1-9]|B1[12]|B8A)(?:_(10m|20m|60m))?\.jp2$', re.IGNORECASE)

# buang file non-band: TCI, mask, AOT, WVP, SCL
exclude_keys = ("TCI", "MSK", "QA", "QUAL", "AOT", "WVP", "SCL")
band_paths = [p for p in all_jp2
              if re_band.search(os.path.basename(p)) and not any(k in os.path.basename(p).upper() for k in exclude_keys)]

if not band_paths:
    print("⚠️ Tidak ditemukan band .jp2 Level-2A dengan pola L2A. Daftar contoh .jp2:")
    for s in all_jp2[:20]:
        print("   •", os.path.relpath(s, unzip_dir))
    raise FileNotFoundError("Tidak ditemukan file band .jp2 Level-2A!")

print(f"\n📄 Ditemukan {len(band_paths)} band kandidat:")
for p in band_paths: print(" -", os.path.basename(p))

# ===== 4) PILIH BAND REFERENSI 10m (B02/B04/B08) =====
prefer = ["_B02_10m", "_B04_10m", "_B08_10m", "_B02", "_B04", "_B08"]
ref_band_path = None
for key in prefer:
    for p in band_paths:
        if key.lower() in os.path.basename(p).lower():
            ref_band_path = p; break
    if ref_band_path: break
if ref_band_path is None:
    raise RuntimeError("Tidak ditemukan band referensi 10 m (B02/B04/B08).")

with rasterio.open(ref_band_path) as ref:
    ref_transform = ref.transform
    ref_shape = ref.shape
    ref_bounds = ref.bounds
    ref_profile = ref.profile.copy()

# ===== 5) DETEKSI HEMISFER DARI NORTHING (tanpa PROJ) =====
# UTM south pakai false northing ~10,000,000. Jika nilai Y (northing) rata-rata > 7e6, besar kemungkinan hemisfer Selatan.
northing_avg = (ref_bounds.top + ref_bounds.bottom) / 2.0
hemisphere = "S" if northing_avg > 7_000_000 else "N"

# Dapatkan zona dari nama ZIP → 'T49MHU' → 49
m_zone = re.search(r'T(\d{2})[A-Z]{3}', zip_name)
if not m_zone:
    raise ValueError("Tidak bisa mengekstrak zona UTM dari nama ZIP.")
utm_zone = int(m_zone.group(1))
print(f"\n🗺️ UTM terdeteksi: Zone {utm_zone}{hemisphere} | northing_avg={northing_avg:,.0f}")

# ===== 6) BANGUN CRS MANUAL (tanpa PROJ) =====
central_meridian = (utm_zone - 1)*6 - 180 + 3
false_northing = 10_000_000 if hemisphere == "S" else 0

crs_utm = CRS.from_wkt(
    f'PROJCS["WGS 84 / UTM zone {utm_zone}{hemisphere}",'
    'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],'
    'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]],'
    'PROJECTION["Transverse_Mercator"],'
    'PARAMETER["latitude_of_origin",0],'
    f'PARAMETER["central_meridian",{central_meridian}],'
    'PARAMETER["scale_factor",0.9996],'
    'PARAMETER["false_easting",500000],'
    f'PARAMETER["false_northing",{false_northing}],'
    'UNIT["metre",1]]'
)
crs_wgs84 = CRS.from_wkt(
    'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],'
    'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]]'
)

# ===== 7) RESAMPLE SEMUA BAND → 10 m (menggunakan grid referensi) =====
bands_data, band_names = [], []
for bpath in band_paths:
    base = os.path.basename(bpath)
    m = re_band.search(base)
    bcode = m.group(1).upper() if m else base  # B01..B12, B8A
    band_names.append(bcode)
    with rasterio.open(bpath) as src:
        arr = src.read(out_shape=(1, ref_shape[0], ref_shape[1]),
                       resampling=Resampling.bilinear)[0]
        bands_data.append(arr)

# ===== 8) REPROJECT UTM → WGS84 (benar secara geografis) =====
print("\n🧭 Reprojecting semua band ke EPSG:4326 ...")
transform, width, height = calculate_default_transform(
    crs_utm, crs_wgs84, ref_shape[1], ref_shape[0], *ref_bounds
)
dst_data = np.zeros((len(bands_data), height, width), dtype=np.uint16)

for i, band in enumerate(bands_data):
    reproject(
        source=band,
        destination=dst_data[i],
        src_transform=ref_transform,
        src_crs=crs_utm,
        dst_transform=transform,
        dst_crs=crs_wgs84,
        resampling=Resampling.bilinear
    )

# ===== 9) SIMPAN SEBAGAI COG =====
profile = ref_profile.copy()
profile.update(
    driver="COG",
    dtype=np.uint16,
    count=len(bands_data),
    height=height, width=width,
    transform=transform, crs=crs_wgs84,
    compress="LZW", BIGTIFF="YES",
    blockxsize=512, blockysize=512
)

with rasterio.open(output_tif, "w", **profile) as dst:
    for i, bcode in enumerate(band_names, start=1):
        dst.write(dst_data[i-1], i)
        dst.set_band_description(i, bcode)
    dst.update_tags(
        **metadata,
        REPROJECTED_TO="EPSG:4326 (manual WKT)",
        BAND_LIST=",".join(band_names),
        NOTE="Sentinel-2 Level-2A: semua band disamakan ke 10 m dan direproyeksi ke WGS84 COG"
    )

print(f"\n✅ File multispectral Level-2A disimpan: {output_tif}")
print("   CRS: EPSG:4326 (WGS84) | Format: COG | Band:", len(band_names))

# ===== 10) HAPUS FOLDER UNZIP =====
try:
    shutil.rmtree(unzip_dir)
    print(f"🧹 Folder hasil unzip dihapus: {unzip_dir}")
except Exception as e:
    print(f"⚠️ Gagal hapus folder unzip: {e}")
