# PLAN-SALES-REPORT-MEETING: Report Harian Otomatis dari Add Meeting

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Meeting
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Implementasi sudah berjalan di `feature/sales-flow`; menunggu merge ke `main`
> dan pengecekan user sebelum status diubah menjadi `Selesai`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- New Meeting menjadi satu-satunya tempat input data meeting.
- Setelah New Meeting disimpan, daily report langsung dianggap terkirim.
- Tidak ada lagi input MOM, foto, outcome, atau next action yang sama di form report.
- Meeting lama yang belum report tetap bisa diselesaikan lewat form konfirmasi.

## 2. Ruang lingkup

Termasuk:

- Alur New Meeting -> daily report otomatis.
- Form report lama disederhanakan menjadi ringkasan read-only + tombol Send.
- Kartu Daily Report di dashboard Sales.
- Badge Report Sent di daftar Meeting.
- KPI Meeting: Won This Month diganti Report Sent This Month.

Tidak termasuk:

- Tampilan monitoring Management (ada di `../management/PLAN-MANAGEMENT-MONITORING-REPORT-SALES.md`).
- Perubahan modul Schedule (`sales_schedules`).
- Mengubah data meeting lama yang sudah selesai.

## 3. Temuan kondisi sekarang

1. New Meeting sudah menyimpan customer, lead, tanggal, jam, mode, lokasi,
   `description` (Summary Meeting), `next_follow_up_date`, dan foto bukti.
2. Form report lama meminta ulang foto, MOM (`meeting_notes`), outcome, dan
   next action — semuanya duplikat dari New Meeting.
3. `FollowUp::hasMeetingReport()` masih mensyaratkan `meeting_notes` terisi.
4. `SalesMeetingController@store()` masih mengarahkan user ke form report.
5. KPI "Won This Month" bergantung pada `meeting_outcome` yang tidak akan diisi lagi.

## 4. Langkah pengerjaan

### 4.1 Alur Sales

1. Setelah `SalesMeetingController@store()` menyimpan meeting:
   - set `completed_at = now()`,
   - set `daily_report_submitted_at = now()`,
   - set `meeting_next_action_date` dari `next_follow_up_date`,
   - catat aktivitas `daily_report_submitted`,
   - arahkan ke halaman detail meeting.
2. `FollowUp::hasMeetingReport()` cukup memeriksa `completed_at`.
3. Halaman detail tidak menampilkan MOM kosong; Summary Meeting sudah menjadi isi report.

### 4.2 Form report lama (legacy)

4. Form report hanya dipakai meeting lama yang belum selesai.
5. Isinya ringkasan read-only: customer, mode, tanggal, jam, lokasi, next meeting,
   Summary Meeting, dan jumlah foto.
6. Hapus input foto, MOM, outcome, dan next action.
7. Tombol akhir: **Send Daily Report**; submit langsung menandai selesai.

### 4.3 Tampilan Sales

8. Dashboard Sales: kartu **Daily Report** berisi meeting hari ini, sudah dikirim,
   belum dikirim, dan tombol Send report.
9. Daftar Meeting: badge **Report Sent** + jam kirim; Report Pending hanya untuk data lama.
10. KPI Meeting: **Report Sent This Month** menggantikan **Won This Month**.

### 4.4 Data untuk Management

11. `SalesReportStatusService::daily($date)` mengembalikan status per sales
    (`done` / `partial` / `missing`) dan dipakai dashboard Sales.
12. Halaman Management memakai service yang sama (plan terpisah).

## 5. Kriteria selesai

- New Meeting -> Save -> detail meeting langsung berstatus Report Sent.
- Tidak ada input ulang foto, MOM, outcome, atau next action.
- Meeting lama yang belum report bisa diselesaikan lewat form konfirmasi.
- Dashboard Sales menampilkan kartu Daily Report dengan angka yang benar.
- `php artisan test --filter="MeetingReport|SalesMeetingTracker|SalesDailyReport"` lulus.

## 6. Dependensi

- `../management/PLAN-MANAGEMENT-MONITORING-REPORT-SALES.md` memakai service status yang sama.
- Tidak menunggu `PLAN-SALES-QUOTATION-OTOMATIS.md`.

## 7. Keputusan yang sudah diambil

1. Report harian cukup dari New Meeting; tidak ada input tambahan.
2. New Meeting langsung dianggap sebagai report terkirim.
3. Meeting Outcome dihapus dari form baru; data lama tetap ditampilkan.
4. Meeting lama tetap bisa di-report lewat form konfirmasi.
