# PLAN-MANAGEMENT-MONITORING-REPORT-SALES: Monitoring Kepatuhan Report Sales

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Management / Sales KPI & Target
**Branch:** `feature/management`
**Worktree:** `/home/yeski/3dyapp-management`
**Terakhir diperbarui:** 2026-11-09
> Catatan: menunggu `PLAN-SALES-REPORT-MEETING.md`.
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/management` 70 commit di belakang `main` dan 52 commit di depan.
> Sebelum mulai: `git pull origin feature/management`, `git merge main`, `php artisan migrate`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Management bisa melihat, per hari, **siapa Sales yang sudah mengirim report meeting** dan siapa yang belum.
- Bisa drill-down ke detail meeting/report Sales tersebut.
- Ada filter tanggal dan divisi/PT.

## 2. Ruang lingkup

Termasuk:

- Satu halaman monitoring kepatuhan report.
- Link menu di sidebar Management.
- Data pendukung (service bersama dengan dashboard Sales).

Tidak termasuk:

- Membuat input report baru (sudah di `PLAN-SALES-REPORT-MEETING.md`).
- Mengubah target KPI/leaderboard.

## 3. Temuan kondisi sekarang

Halaman Management yang sudah ada:

| Halaman | Route | Isi |
|---|---|---|
| Manage Sales | `manage-sales.index` | assign lead + statistik |
| Sales KPI & Target | `manage-sales.kpi` | target tim + leaderboard |
| Team Activity | `manage-sales.team-activity` | kepatuhan jadwal, follow-up hari ini, aktivitas lead, lead mengendap, funnel |
| Sales Rep | `manage-sales.sales-rep` | snapshot dashboard 1 sales |

Catatan penting:

- `manage-sales.team-activity` **sudah punya struktur per-sales harian**, tetapi belum
  membaca status report meeting, dan **belum ada link di sidebar** (route-nya ada di `routes/web.php:727`).
- `manage-sales.kpi` fokus ke angka target dan peringkat, bukan kepatuhan harian.
- Management punya `manage-sales-leads` + `view-sales`, jadi boleh membuka data tim.

## 4. Rekomendasi penempatan

**Letakkan di halaman Management > Team Activity**, bukan di Sales KPI & Target.

Alasan:

1. Team Activity memang sudah berisi kepatuhan harian per orang (jadwal, aktivitas).
2. KPI & Target adalah halaman angka bulanan; menaruh daftar "belum lapor hari ini" di sana
   membuat dua tujuan bercampur.
3. Cukup menambah satu section, tidak perlu halaman baru, dan sekaligus memberi link sidebar
   yang saat ini belum ada.

Kalau User ingin nama menu yang lebih jelas, ganti label sidebar menjadi
**"Team Activity"** atau **"Activity & Report"** (default Inggris sesuai AGENTS.md §7).

## 5. Langkah pengerjaan

1. Pastikan `PLAN-SALES-REPORT-MEETING.md` sudah menyediakan `SalesReportStatusService::daily($date)`.
2. `ManageSalesController@teamActivity` (451-524): tambahkan data report meeting:
   - jumlah meeting terjadwal hari itu per sales,
   - jumlah yang sudah ada report,
   - status `done` / `partial` / `missing`,
   - waktu report terakhir.
3. View `manage-sales/team-activity.blade.php`: tambah kartu/tabel
   **"Meeting Report Today"** dengan kolom: Sales, Meeting, Reported, Pending, Last Report, Status.
4. Tambah filter tanggal (default hari ini) dan PT/divisi.
5. Baris tabel bisa diklik ke `manage-sales.sales-rep` untuk melihat daftar meeting + status report.
6. `manage-sales/sales-rep.blade.php`: tambah daftar meeting terakhir beserta badge
   `Report sent` / `Report pending`.
7. Sidebar (`layouts/partials/sidebar.blade.php`, grup Management): tambahkan link Team Activity
   dengan permission `manage-sales-leads` atau `view-sales`.
8. Empty state: jelaskan cara mengisi (Sales membuat meeting lalu mengirim report), bukan sekadar "No data".
9. Opsional: digest harian 17:30 ke Management berisi sales yang belum lapor.

## 6. Kriteria selesai

- Sales A mengirim report -> halaman Team Activity menampilkan A dengan status `done`.
- Sales B belum mengirim -> tampil `missing` beserta jumlah pending.
- Filter tanggal menampilkan data hari yang dipilih.
- Menu Management menampilkan link Team Activity dan bisa dibuka.
- Light & dark mode rapi; tidak ada kartu bersarang (AGENTS.md §7.1/§7.2).
- `php artisan test --filter="SalesUxImprovement|SalesKpiTarget|ManageSales"` lulus.

## 7. Dependensi

- Wajib setelah `PLAN-SALES-REPORT-MEETING.md` (butuh penanda report yang sama).
- Tidak perlu menunggu quotation/PO.
