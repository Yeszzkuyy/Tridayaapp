# PLAN-SALES-INSIDE-DASHBOARD: Dashboard Kerja Inside Sales

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Sales / Inside Sales
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Menunggu `PLAN-SALES-QUOTATION-OTOMATIS.md` karena dashboard menampilkan hasil quotation.
> Struktur menu default: satu route group `/workspace` dengan tab per role.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Inside Sales punya halaman kerja sendiri: daftar request dari Sales, progress pengerjaan,
  chat, dan hasil quotation.
- Request yang mengendap, deadline, dan status pengerjaan terlihat dalam satu layar.
- User multi-role (`inside-sales` + `sales`) tetap bisa membuka menu Inside Sales.

## 2. Ruang lingkup

Termasuk:

- Dashboard Inside Sales + menu sidebar.
- Ringkasan antrean, SLA/aging, aksi cepat.
- Notifikasi request baru dan jawaban harga.

Tidak termasuk:

- Dashboard Estimator (ada di `PLAN-SALES-ESTIMATOR-DASHBOARD.md`).
- Perubahan isi quotation (ada di `PLAN-SALES-QUOTATION-OTOMATIS.md`).
- Perubahan Price Request yang sedang dikerjakan di `feature/sales-price-sync`.

## 3. Temuan kondisi sekarang

1. Role `inside-sales` hanya punya `manage-inside-sales` (+ `view-customer`, `view-trash`,
   `view-products`).
2. Sidebar hanya menampilkan menu Inside Sales bila user **tidak** punya `view-sales`
   (`sidebar.blade.php:40`). User multi-role malah melihat tab "Request".
3. Belum ada dashboard khusus; user mendarat di `lead-tasks.index`.
4. Data yang sudah tersedia: `lead_tasks`, `lead_task_attachments`, chat, dan quotation.

## 4. Langkah pengerjaan

1. Buat route `inside-sales.dashboard` dengan middleware `manage-inside-sales` di route group
   `/workspace`.
2. Buat `InsideSalesDashboardController` (controller terpisah, jangan digabung dengan Estimator).
3. KPI: request baru, sedang dikerjakan, menunggu jawaban estimator, selesai minggu ini,
   rata-rata waktu penyelesaian.
4. Daftar kerja: request terbaru, deadline, customer, Sales peminta, status, aksi cepat
   (buka task, chat, unggah quotation).
5. Peringatan request mengendap lebih dari X hari.
6. Perbaiki sidebar: menu Inside Sales tetap muncul untuk user multi-role, tidak bergantung
   pada "tidak punya `view-sales`".
7. Notifikasi request baru ke Inside Sales terkait; gunakan pola notifikasi yang sudah ada.

## 5. Kriteria selesai

- Login sebagai user `inside-sales` langsung melihat dashboard dengan antrean nyata.
- User multi-role (`inside-sales` + `sales`) bisa membuka dashboard ini tanpa logout.
- Sales tidak melihat menu ini.
- Semua angka dari database, bukan contoh (AGENTS.md §7.2).
- Light & dark mode, mobile, empty state, dan loading state rapi.
- `php artisan test --filter="InsideSalesTask|RoleMatrix|DivisionAccess"` lulus.

## 6. Dependensi

- `PLAN-SALES-QUOTATION-OTOMATIS.md`: hasil quotation harus sudah terstruktur.
- `feature/sales-price-sync`: merge dulu bila skema Price Request berubah.
