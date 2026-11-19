# PLAN-SALES-ESTIMATOR-DASHBOARD: Dashboard Kerja Estimator

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Sales / Estimator
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Menunggu `../sales/PLAN-SALES-QUOTATION-OTOMATIS.md` karena harga dari estimator dipakai quotation.
> Struktur menu default: satu route group `/workspace` dengan tab per role.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Estimator punya halaman kerja sendiri: antrean permintaan harga, riwayat harga produk,
  dan laporan.
- Price request yang belum dijawab, aging, dan prioritas terlihat dalam satu layar.
- Estimator bisa menjawab harga dan melihat riwayat tanpa berpindah menu.

## 2. Ruang lingkup

Termasuk:

- Dashboard Estimator + menu sidebar.
- Ringkasan antrean, SLA/aging, aksi cepat.
- Riwayat harga produk dan tautan ke `product_price_logs`.
- Notifikasi jawaban harga ke Sales/Inside Sales peminta.

Tidak termasuk:

- Dashboard Inside Sales (ada di `../inside-sales/PLAN-SALES-INSIDE-DASHBOARD.md`).
- Perubahan isi quotation (ada di `../sales/PLAN-SALES-QUOTATION-OTOMATIS.md`).
- Perubahan Price Request yang sedang dikerjakan di `feature/sales-price-sync`.

## 3. Temuan kondisi sekarang

1. Role `estimator` punya `manage-price-requests` + `manage-products`.
2. Grup menu Estimator muncul bila punya `manage-price-requests`
   (`sidebar.blade.php:399-433`), isinya Price Request + Products.
3. Belum ada dashboard khusus; user mendarat di `sales.requests?tab=price`.
4. Data yang sudah tersedia: `price_requests`, `price_request_replies`, `product_price_logs`.
5. Export laporan estimator sudah ada (`PriceRequestController:392-481`).

## 4. Langkah pengerjaan

1. Buat route `estimator.dashboard` dengan middleware `manage-price-requests` di route group
   `/workspace`.
2. Buat `EstimatorDashboardController` (controller terpisah, jangan digabung dengan Inside Sales).
3. KPI: price request masuk, belum dijawab, dijawab hari ini, rata-rata waktu jawab.
4. Antrean: daftar price request + aging + prioritas.
5. Riwayat harga: produk yang sering diminta, harga terakhir, link ke `product_price_logs`.
6. Aksi cepat: jawab harga, lihat lampiran, export laporan (sudah ada).
7. Tambah link dashboard di grup Estimator.
8. Pastikan Inside Sales tidak bisa membuka data Estimator (dan sebaliknya), kecuali diizinkan.
9. Notifikasi jawaban harga ke Sales/Inside Sales peminta.

## 5. Kriteria selesai

- Login sebagai Estimator langsung melihat dashboard dengan antrean nyata.
- Price request yang belum dijawab dan aging tampil benar.
- Riwayat harga terhubung ke `product_price_logs`.
- Semua angka dari database, bukan contoh (AGENTS.md §7.2).
- Light & dark mode, mobile, empty state, dan loading state rapi.
- `php artisan test --filter="PriceRequest|RoleMatrix|DivisionAccess"` lulus.

## 6. Dependensi

- `../sales/PLAN-SALES-QUOTATION-OTOMATIS.md`: harga estimator dipakai quotation.
- `feature/sales-price-sync`: merge dulu bila skema Price Request berubah.
