# PLAN-ESTIMATOR-PRICELIST: Revamp Pricelist (Margin, Filter, Bulk Price)

**Status Pengerjaan:** Siap Dikerjakan
**Work:** ESTIMATOR-PRICELIST
**PIC:** Yeski
**Menu:** Estimator / Pricelist
**Branch:** feature/estimator-pricelist
**Terakhir diperbarui:** 2026-11-17

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
   memakai halaman Update Price biasa.
3. Tombol Review Prices membuka mode bulk dengan filter Needs Recheck;
   filter dapat dilepas untuk mengedit produk lain yang punya riwayat harga.

## Ruang Lingkup

- Accessor margin dan price trend pada model Product.
- Scope filter harga: price active, needs recheck, negative margin, expiring soon.
- UI grid sejajar Cost / Selling Price / Margin + quick filter chips.
- Mode bulk edit: input Cost, Supplier, Valid Until; Stock/Warranty disalin.
- Endpoint bulk update dengan transaksi dan pencatatan ProductPriceLog.
- Test accessor, filter, bulk update, dan tampilan.

Tidak termasuk: perubahan harga jual, perubahan struktur supplier produk,
migrasi database baru (kecuali terbukti perlu).

## Verifikasi

- [ ] Estimator melihat Selling Price + badge margin.
- [ ] Produk rugi tampil merah; margin 0-10% kuning; sehat hijau.
- [ ] Chip filter menampilkan jumlah yang benar dan bisa dikombinasikan
      dengan pencarian/kategori.
- [ ] Review Prices membuka bulk mode berisi produk dengan riwayat harga.
- [ ] Simpan bulk membuat satu log per produk yang berubah dalam satu transaksi.
- [ ] Produk tanpa riwayat harga tidak masuk bulk dan diarahkan ke Update Price.
- [ ] Test terkait lulus, build dari main, cache view dibersihkan.
