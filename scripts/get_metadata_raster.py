import rasterio

# buka file citra
file_path = './sentinel2L1_multispectral_10m_cog_auto_crs.tif'

with rasterio.open(file_path) as src:
    print("Jumlah band:", src.count)
    print("Ukuran citra (lebar x tinggi):", src.width, "x", src.height)
    print("CRS:", src.crs)
    print("Transformasi geospasial:", src.transform)
    print("\nDeskripsi band (jika ada):")
    for i in range(1, src.count + 1):
        print(f"Band {i}: {src.descriptions[i-1]}")
    print("\nStatistik nilai tiap band:")
    for i in range(1, src.count + 1):
        band = src.read(i)
        print(f"Band {i} - Min: {band.min()}, Max: {band.max()}, Mean: {band.mean():.2f}")
