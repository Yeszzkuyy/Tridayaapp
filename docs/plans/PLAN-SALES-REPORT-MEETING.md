# PLAN-SALES-REPORT-MEETING: Report Harian Otomatis dari Add Meeting

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Sales / Meeting
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
> Catatan: butuh keputusan di bagian 7.
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/sales-flow` 146 commit di belakang `main`.
> Sebelum mulai: `git merge main`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Setelah Sales menyimpan Add Meeting, Sales langsung bisa mengirim report meeting
  **tanpa mengisi ulang** data pertemuan.
- Ada penanda jelas "report hari ini sudah dikirim" / "belum".
- Management bisa melihat siapa Sales yang sudah dan belum update report pada hari itu.

## 2. Ruang lingkup

Termasuk:

- Alur Add Meeting -> Report -> status terkirim.
- Kartu status report di dashboard Sales.
- Sumber data yang dipakai Management untuk monitoring.

Tidak termasuk:

- Tampilan monitoring Management (dibahas di `PLAN-MANAGEMENT-MONITORING-REPORT-SALES.md`).
- Report bebas (free text) di luar meeting.
- Perubahan modul Schedule (`sales_schedules`).

## 3. Temuan kondisi sekarang

1. Meeting disimpan di tabel `follow_ups` dengan `type = 'meeting'` (bukan tabel `meetings`).
2. Kolom report: `meeting_photos`, `meeting_notes`, `meeting_outcome`, `meeting_next_action_date`, `completed_at`.
3. `FollowUp::hasMeetingReport()` = `completed_at != null && meeting_notes != null`
   (`app/Models/FollowUp.php:120-127`).
4. `SalesMeetingController@reportStore()` (159-195) memanggil `SalesService::completeMeetingReport()`
   yang mengisi `completed_at` dan mencatat aktivitas `meeting_report_submitted`.
5. **Belum ada tabel/model laporan harian** dan belum ada penanda "daily report terkirim".
   Status saat ini hanya "Report Pending" untuk meeting yang belum `completed_at`.
6. `SendMeetingReminders` sudah jalan tiap hari 17:00 untuk meeting yang belum lapor.

Kesimpulan: fondasinya sudah ada. Yang kurang adalah **satu langkah eksplisit "kirim report"**
dan **ringkasan harian** yang dipakai Sales + Management.

## 4. Langkah pengerjaan

### 4.1 Alur Sales

1. Setelah `SalesMeetingController@store()` menyimpan meeting, arahkan user ke form report
   (`sales.meetings.report`) dengan data meeting sudah terisi otomatis
   (customer, tanggal, jam, mode, summary tidak perlu diketik ulang).
2. Di form report, tombol akhir diubah menjadi **"Send Daily Report"**.
   Isi yang wajib: MOM/`meeting_notes` + outcome. Foto opsional (maks 5, sudah ada).
3. Saat dikirim:
   - set `completed_at = now()` (sudah ada),
   - simpan `meeting_notes`, `meeting_outcome`, `meeting_next_action_date`,
   - catat aktivitas `daily_report_submitted` (bisa menumpang log yang sudah ada).
4. Tambah kolom penanda di `follow_ups` bila perlu:
   `daily_report_submitted_at` (timestamp) agar status "sudah kirim hari ini" tidak ambigu
   bila meeting dijadwalkan beda hari. Buat migrasi baru, jangan edit migrasi lama.

### 4.2 Tampilan Sales

5. Dashboard Sales: ganti kartu "Meeting tracker" menjadi kartu **"Daily Report"** berisi:
   - jumlah meeting hari ini,
   - sudah dikirim,
   - belum dikirim,
   - tombol "Send report" untuk meeting yang belum.
6. Halaman Meeting (`sales/meetings/index.blade.php`): badge status
   `Report sent` / `Report pending` (sudah ada, rapikan labelnya dan tambahkan waktu kirim).
7. Setelah report terkirim, tampilkan konfirmasi jelas ("Report sent to Management").

### 4.3 Data untuk Management

8. Sediakan satu query/service bersama, misalnya `SalesReportStatusService::daily($date)`:
   - daftar sales,
   - jumlah meeting hari itu,
   - jumlah meeting yang sudah ada report,
   - status per sales (`done` / `partial` / `missing`),
   - waktu report terakhir.
9. Query ini dipakai dashboard Sales (untuk diri sendiri) dan halaman Management.

### 4.4 Notifikasi (opsional, disarankan)

10. Reminder 17:00 yang sudah ada dipertahankan.
11. Tambah digest ke Management 17:30 berisi daftar sales yang belum mengirim report hari itu.

## 5. Kriteria selesai

- Sales Add Meeting -> langsung diarahkan ke form report -> isi MOM -> kirim.
- Sales tidak perlu mengetik ulang customer/tanggal/jam.
- Dashboard Sales menampilkan "Report sent" setelah dikirim.
- Kirim dua kali ditolak (sudah diblokir `FollowUpController@complete`).
- `php artisan test --filter="MeetingReport|SalesMeetingTracker"` lulus.
- Test baru: "store meeting mengarah ke form report" dan "status daily report per sales".

## 6. Dependensi

- `PLAN-MANAGEMENT-MONITORING-REPORT-SALES.md` memakai service dari poin 8.
- Tidak perlu menunggu `PLAN-SALES-QUOTATION-OTOMATIS.md`.

## 7. Butuh keputusan User sebelum dikerjakan

1. Apakah report harian **cukup dari meeting**?
   - **Opsi A (rekomendasi):** ya, report harian = report meeting. Tidak ada input tambahan.
   - **Opsi B:** tetap ada kolom tambahan "ringkasan hari ini" di luar meeting.
2. Kalau Sales punya 3 meeting sehari, apakah wajib report semua?
   - **Opsi A (rekomendasi):** ya, semua meeting wajib ada report.
   - **Opsi B:** cukup 1 meeting dilaporkan, sisanya opsional.
