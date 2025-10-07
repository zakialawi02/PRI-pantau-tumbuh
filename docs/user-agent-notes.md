# Catatan Kolaborasi

## Riwayat Tanya Jawab

- **Pertanyaan:** "Preview on map tidak tampil. Error: Sentinel preview controller is unavailable."  
  **Tindakan:** Menambahkan pemicu `map:ready` dan menunggu inisialisasi peta sebelum menampilkan preview.
- **Pertanyaan:** "Saya ingin bisa download scene citranya, bukan thumbnail."  
  **Tindakan:** Menambahkan aksi unduh scene lengkap serta heuristik pemilihan URL download.
- **Pertanyaan:** "Token tidak ditemukan / tidak bisa diparse."  
  **Tindakan:** Mengganti input manual dengan kredensial server dan layanan refresh token otomatis.
- **Pertanyaan:** "Preview coverage berhasil tapi citra tidak, jangan pakai thumbnail."  
  **Tindakan:** Mengganti rendering quicklook dengan layer WMS Copernicus sebagai preview utama dan tetap menampilkan kotak batas.

## Catatan Tambahan

- Layer preview sekarang memprioritaskan layanan OGC (WMS) yang disediakan oleh metadata Copernicus dan akan kembali ke quicklook hanya jika layanan tersebut tidak tersedia.
- Tombol unduh tetap menggunakan token Copernicus yang dikelola server; pastikan variabel lingkungan `COPERNICUS_CLIENT_ID` dan `COPERNICUS_CLIENT_SECRET` terisi agar token dapat diperbarui otomatis.
