# PLAN-ESTIMATOR-PRICELIST: Revamp Pricelist (Margin, Filter, Bulk Price)

**Status Pengerjaan:** Siap Dikerjakan
**Work:** ESTIMATOR-PRICELIST
**PIC:** Yeski
**Menu:** Estimator / Pricelist
**Branch:** feature/estimator-pricelist
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Pricelist Estimator menampilkan Selling Price dan margin, menandai produk
rugi/margin rendah, menyediakan filter cepat (Active, Needs Recheck, Negative
Margin, Expiring Soon), dan memungkinkan update harga massal untuk produk yang
sudah punya riwayat harga.

## Keputusan User

1. Estimator boleh melihat Selling Price dan Margin di halaman Pricelist,
   tetapi tetap tidak bisa mengubah Selling Price.
2. Bulk price update hanya untuk produk yang sudah punya riwayat harga;
   Stock dan Warranty disalin dari riwayat terakhir. Produk tanpa harga
   memakai tombol Set Price pada baris Pricelist.
3. Tombol Review Prices membuka seluruh produk yang punya riwayat harga;
   tautan ke Needs Recheck muncul bila memang ada produk yang tumpang tindih.
4. Review Prices membuka halaman Bulk Price Update terpisah. Harga dapat
   ditandai termasuk atau tidak termasuk PPN per produk, lalu dibandingkan
   berdasarkan harga modal bersih memakai tarif PPN saat ini.
5. Sebelum disimpan, perubahan ditinjau dalam kartu konfirmasi. Produk tanpa
   perubahan tidak membuat histori baru; stok dan garansi mengikuti histori terakhir.
6. Tidak ada perubahan skema database. Pilihan PPN hanya dipakai untuk
   menghitung harga modal bersih; riwayat menyimpan harga bersih, bukan nilai
   bruto atau status PPN.
7. Tarif PPN dibaca dari konfigurasi aplikasi, dengan nilai awal 12%.

## Ruang Lingkup

- Accessor margin dan price trend pada model Product.
- Scope filter harga: price active, needs recheck, negative margin, expiring soon.
- UI grid sejajar Cost / Selling Price / Margin + quick filter chips.
- Halaman Bulk Price Update terpisah untuk produk yang sudah punya histori.
- Input Cost, status termasuk PPN, Supplier, dan Valid Until per produk;
  Stock/Warranty disalin dari histori terakhir.
- Pencarian langsung berdasarkan nama produk/SKU tanpa menghilangkan
  perubahan yang belum disimpan.
- Perbandingan harga memakai harga modal bersih setelah PPN dihitung ulang
  menggunakan tarif saat ini (default 12%, dapat diatur melalui konfigurasi).
- Simpan perubahan sebagai histori baru dalam satu transaksi.
- Test accessor, filter, bulk update, dan tampilan.

Tidak termasuk: perubahan harga jual, perubahan struktur supplier produk,
migrasi database baru (kecuali terbukti perlu).

## Verifikasi

- [x] Estimator melihat Selling Price + badge margin.
- [x] Produk rugi tampil merah; margin 0-10% kuning; sehat hijau.
- [x] Chip filter menampilkan jumlah yang benar dan bisa dikombinasikan
      dengan pencarian/kategori.
- [x] Review Prices membuka bulk mode berisi produk dengan riwayat harga.
- [x] Simpan bulk membuat satu log per produk yang berubah dalam satu transaksi.
- [x] Produk tanpa riwayat harga memakai tombol Set Price; yang beriwayat
      memakai Update Price.
- [x] Pencarian bulk menyaring nama/SKU dan tetap menghitung perubahan pada
      baris yang disembunyikan.
- [x] Input Cost menampilkan Rp dan titik ribuan saat diketik.
- [x] Kolom Cost, Supplier, dan Valid Until sejajar; helper berulang dihapus.
- [x] Halaman bulk menampilkan hanya produk dengan histori dan memuat nilai
      awal yang sama dengan harga pada histori.
- [x] Harga termasuk PPN dibandingkan berdasarkan harga modal bersih dan
      perubahan ditinjau sebelum disimpan.
- [x] Validasi gagal tetap mempertahankan seluruh input yang sudah diubah.
- [x] Tidak ada migration baru; histori lama dan harga modal tetap utuh.
- [x] Tes PHP/JavaScript terkait perubahan bulk lulus.
- [ ] Build dari main dan pembersihan cache view setelah perubahan di-merge.
