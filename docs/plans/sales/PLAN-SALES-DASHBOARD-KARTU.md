# PLAN-SALES-DASHBOARD-KARTU: Urutan 5 Kartu KPI & Konsistensi Bahasa

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Dashboard
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/sales-flow` saat ini **146 commit di belakang `main`** dan 0 commit di depan.
> Sebelum mulai wajib: `git pull origin feature/sales-flow` lalu `git merge main`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- 5 kartu KPI berada **tepat setelah kartu motivasi** dan **di atas kartu My Personal KPI**.
- Seluruh teks di dashboard Sales memakai bahasa default sistem lewat `__()` (default Inggris),
  versi Indonesia tetap tersedia lewat `lang/id.json`.

## 2. Ruang lingkup

Termasuk:

- Urutan blok di `resources/views/sales/dashboard.blade.php`.
- Semua string Indonesia yang masih hardcode di dashboard Sales.
- Dua entri `lang/en.json` yang isinya masih Indonesia.

Tidak termasuk:

- Perubahan isi kartu motivasi / KPI (data tetap dari `ManageSalesController`).
- Desain ulang kartu (sudah mengikuti Lynk, AGENTS.md §7.1).
- Halaman Meeting (dibahas di `PLAN-SALES-REPORT-MEETING.md`).

## 3. Temuan kondisi sekarang

Urutan render `resources/views/sales/dashboard.blade.php`:

1. Open header + tanggal (baris 4-16).
2. Kartu motivasi (18-62).
3. **My Personal KPI** (64-160).
4. Customer Hub (162-163).
5. **5 kartu KPI** (definisi 165-242, render 244-275).
6. Leaderboard & target (277-356).
7. My Schedule (358-380).
8. Meeting tracker (382-403).
9. dst.

Jadi saat ini 5 kartu KPI berada **di bawah** My Personal KPI, bukan di atasnya.

String Indonesia yang masih hardcode:

| File | Baris | Teks |
|---|---|---|
| `sales/dashboard.blade.php` | 76 | `% Capaian` |
| sama | 82 | `Pipeline: ... Lead Aktif` |
| sama | 84 | `Konversi:` |
| sama | 115, 144 | `Sisa target:` |
| sama | 117 | `Target omset telah terlampaui!` |
| sama | 119 | `Bulan` |
| sama | 146 | `Target closing sukses tercapai!` |
| sama | 148 | `leads aktif siap closing` |
| sama | 155 | `Catatan Management:` |
| sama | 283 | `Anggota Tim` |
| sama | 317 | `Closing Deal` |
| `SalesKpiService.php` | 206, 221, 235 | `TARGET TERCAPAI`, `ON TRACK`, `FOKUS TARGET` |

`lang/en.json` baris 4731-4732: `Realisasi Closing (Lunas)` dan `PO Masuk` nilainya masih teks Indonesia.

## 4. Langkah pengerjaan

1. Pindahkan blok definisi + render 5 kartu (baris 165-275) ke posisi setelah kartu motivasi
   dan sebelum My Personal KPI. Tidak perlu ubah controller.
2. Ganti semua string pada tabel 3 dengan `__('...')`.
3. Tambahkan key baru ke `lang/en.json` (nilai Inggris) dan `lang/id.json` (nilai Indonesia).
   Contoh: `'% Achieved'`, `'Pipeline: :count Active Leads'`, `'Conversion:'`,
   `'Target remaining:'`, `'Monthly target has been exceeded!'`, `'Team Members'`, `'Closing Deal'`.
4. Perbaiki `lang/en.json` baris 4731-4732 menjadi bahasa Inggris
   (`Realized Closing (Paid)`, `Incoming PO`), lalu pastikan `lang/id.json` tetap Indonesia.
5. Ganti badge `SalesKpiService` (206/221/235) dengan `__()`.
6. Cek konsistensi: tidak ada lagi teks Indonesia hardcode di dashboard Sales.

## 5. Kriteria selesai

- Buka `/sales/dashboard`: urutannya header -> motivasi -> **5 KPI** -> My Personal KPI -> Customer Hub.
- Dengan locale `en` semua label berbahasa Inggris; dengan locale `id` tetap Indonesia.
- Tidak ada `__()` yang menghasilkan key mentah (key harus ada di dua file bahasa).
- `php artisan test --filter="SalesKpi|Dashboard"` lulus.
- Setelah merge ke `main`, jalankan `npm run build` dari `/var/www/3dyapp` (AGENTS.md §5).

## 6. Dependensi

- Tidak ada. Cukup satu file view + dua file bahasa + satu service.
- Kalau sedang ada pekerjaan i18n besar di `feature/i18n-english`, koordinasikan agar key tidak dobel.
