# PLAN-SISTEM-NOTIFIKASI-TEKNISI: Notifikasi & Teknisi

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sistem / Notifikasi & Teknisi
**Branch:** feature/notif-teknisi
**Terakhir diperbarui:** 2026-11-08

> Sebagian besar item sudah merge ke main; B2 (UM-1) dan B11 (TEK-5) perlu keputusan lanjutan. Status item perlu dicek ulang.

---
## Brief Sesi B — Notifikasi & Teknisi

> Bagian dari [PLAN-SISTEM-AUDIT-NOVEMBER](PLAN-SISTEM-AUDIT-NOVEMBER.md). Baca file induk untuk detail tiap item (masalah, perbaikan, file, verifikasi).

- **Worktree:** `/home/yeski/3dyapp-notif-tek`
- **Branch:** `feature/notif-teknisi`
- **Base:** `main` yang sudah bersih (lihat §2 PLAN-SISTEM-AUDIT-NOVEMBER)
- **Jumlah item:** 11
- **Batas teknis:** ikuti `AGENTS.md` §2 (alur merge), §3/§9 (wip → squash), §7 (tombol), §12 (role)

---

## Urutan kerja

| Urut | No | ID | Singkat | Commit scope |
|---|---|---|---|---|
| 1 | B1 | TEK-4 | Lampiran survey terima doc/docx/xls | `tek/survey` |
| 2 | B2 | UM-1 | Hilangkan delay notifikasi | `sys/notif` |
| 3 | B3 | UM-2 | Penanda "terakhir dilihat" | `sys/notif` |
| 4 | B4 | UM-3 | Notifikasi teknisi jadi PIC | `tek/requests` |
| 5 | B5 | SAL-5 | Matikan notifikasi request engineer | `tek/requests` |
| 6 | B6 | INS-1 | Notif price request langsung ke detail | `sys/notif` |
| 7 | B7 | SAL-6 | Menu Hasil Pekerjaan read-only | `tek/field-work` |
| 8 | B8 | TEK-2 | Cek bentrok jadwal + usul perubahan | `tek/jadwal` |
| 9 | B9 | TEK-3 | Auto Schedule saat accept | `tek/jadwal` |
| 10 | B10 | TEK-6 | Dashboard Teknisi lacak PIC | `dash/teknisi` |
| 11 | B11 | TEK-5 | Field Work general + dokumentasi & BAST | `tek/field-work` (dipecah 2–3 commit) |

**Aturan commit:** dilarang mencampur dua divisi dalam satu commit (`AGENTS.md` §9). B11 berpotensi > 15 file, jadi **wajib dipecah**: (a) tipe pekerjaan + daftar, (b) upload dokumentasi, (c) tautan BAST.

---

## File yang kamu miliki

- `app/Http/Controllers/TechnicalRequestController.php`
- `app/Http/Controllers/SurveyController.php`
- `app/Http/Controllers/FieldWorkController.php`, `InstalasiController.php`
- `app/Models/TechnicalRequest.php`, `Survey.php`, `Instalasi.php`, `TechnicianSchedule.php`
- `app/Notifications/**` (file yang sudah ada)
- `app/Http/Controllers/NotificationController.php`, `app/Support/NotificationPresenter.php`, `app/Events/NotificationCreated.php`
- `resources/js/components/NotificationCenter.jsx`, `app.js`, `echo.js`
- `resources/views/layouts/app.blade.php`
- `resources/views/teknisi/**`
- `resources/views/layouts/partials/sidebar.blade.php`
- `config/queue.php`
- migrasi penanda notifikasi (baru)

## Jangan disentuh

- `app/Http/Controllers/ProposalController.php` → A
- `app/Models/Proposal.php` → A
- `app/Models/Lead.php`, `resources/views/leads/**` → A
- `app/Models/Customer.php`, `resources/views/customers/**` → A
- `resources/views/sales/**` → A
- `resources/views/admin/**`, `BastController` → A

## File bersama

- `routes/web.php` — tambah route di blok teknisi/sales saja, jangan ubah urutan blok lain.
- `lang/en.json`, `lang/id.json` — tambah key, saat konflik keep both.

## Temuan kunci yang harus dijaga

1. Notifikasi memakai `ShouldQueue` + queue diproses cron per menit → akar delay (UM-1).
2. Feed mengambil semua unread tanpa penanda "terakhir dilihat" → akar tumpukan setelah restart (UM-2).
3. `SurveyController::guardRead()` hanya meloloskan `view-technician` atau sales pemilik data → akar 403 (SAL-6).
4. `TechnicalRequestController::assign()` hanya menotifikasi pemohon, belum teknisi (UM-3), dan belum membuat Schedule (TEK-3).
5. `TechnicalRequestController::store()` mengirim notifikasi ke `lead-technician`; ini yang dimatikan di SAL-5.
6. `NotificationPresenter` punya fallback generik — notifikasi baru dari sesi A tetap tampil tanpa perlu mapping.
7. Baca-saja selalu di belakang `view-*`, mutasi di `manage-*` (§12).

## Kontrak data dengan Sesi A (BAST)

Dokumen hasil Field Work harus ditulis ke `project_documents` dengan field:

- `project_id`
- `type` (mis. `dokumentasi`, `bast`)
- `path`
- `uploaded_by`

Sesi A membaca field yang sama di `BastController`. Sepakati sebelum B11.

## Definition of Done (per item)

1. Perubahan sesuai permintaan user, sudah dicek di UI.
2. Tidak ada `dd()`/`dump()`/`console.log`.
3. `php artisan test` lulus untuk logika yang disentuh.
4. Tombol mengikuti §7 (icon-only, warna solid, teks Inggris).
5. Tidak ada file sisa (`.bak`, screenshot debug, log).
6. Commit sesuai format §9 + body 2–5 baris.

## Template commit

```
fix(tek/survey): lampiran terima doc docx xls
fix(sys/notif): hapus delay dan tumpukan notifikasi
feat(tek/requests): notifikasi penugasan ke teknisi PIC
fix(tek/requests): hentikan notifikasi request engineer ke sales
fix(sys/notif): notif price request langsung ke detail
feat(tek/field-work): menu hasil pekerjaan read-only lintas role
feat(tek/jadwal): cek bentrok dan auto schedule dari request
feat(dash/teknisi): tracking PIC dan project aktif per teknisi
feat(tek/field-work): generalisasi pekerjaan lapangan
```

---

## 6. Sesi B — Detail Pekerjaan

**Worktree:** `/home/yeski/3dyapp-notif-tek` · **Branch:** `feature/notif-teknisi`

### B1 · TEK-4 — Lampiran survey non-PDF
- **Masalah:** lampiran Survey Detail hanya menerima PDF.
- **Perbaikan:** pakai `SecureFile::documents()` yang sudah ada (doc, docx, xls, xlsx, pdf), batas ukuran tetap; sesuaikan atribut `accept` di form.
- **File:** `app/Http/Controllers/SurveyController.php`, `app/Rules/SecureFile.php`, view form survey
- **Verifikasi:** upload docx & xlsx berhasil; file berbahaya (exe) tetap ditolak.

### B2 · UM-1 — Delay notifikasi
- **Masalah:** notifikasi lead baru telat ~60 detik karena menunggu cron.
- **Perbaikan:**
  1. Jalankan `queue:work` permanen + Reverb lewat supervisor (konfigurasi server, di luar repo).
  2. Untuk notifikasi kritis (lead baru, penugasan, approval), kirim langsung tanpa antre.
- **File:** `app/Notifications/**`, `config/queue.php`
- **Verifikasi:** lead baru dibuat → bell berubah < 5 detik pada kondisi server normal.

### B3 · UM-2 — Tumpukan notifikasi setelah restart
- **Masalah:** setelah restart/pindah tab, semua notifikasi lama muncul sekaligus.
- **Perbaikan:**
  1. Migrasi kolom penanda `notifications_seen_at` di `users` (atau tabel terpisah).
  2. `NotificationController::status()` menandai item setelah penanda sebagai "baru".
  3. Frontend hanya menyorot/menampilkan item baru; item lama tetap ada di daftar tapi tidak memicu tumpukan.
  4. Perbarui penanda saat panel dibuka dan saat tab kembali aktif.
- **File:** migrasi baru, `app/Http/Controllers/NotificationController.php`, `resources/js/components/NotificationCenter.jsx`
- **Verifikasi:** tutup browser → buka lagi → tidak ada ledakan notifikasi lama; item baru tetap muncul.

### B4 · UM-3 — Notifikasi teknisi jadi PIC
- **Masalah:** teknisi yang ditunjuk tidak tahu dia jadi PIC.
- **Perbaikan:** kirim notifikasi ke `assigned_technician_id` di `TechnicalRequestController::assign()`, isi: project/request, tanggal, tautan ke tugas.
- **File:** `app/Http/Controllers/TechnicalRequestController.php`, notifikasi baru di `app/Notifications/`
- **Verifikasi:** Amir assign teknisi → teknisi menerima notifikasi → klik masuk ke detail tugas.

### B5 · SAL-5 — Matikan notifikasi request engineer
- **Masalah:** notifikasi request engineer terlalu ramai.
- **Perbaikan:** hapus `Notification::send` pada pembuatan request (`store`). Yang tetap: `TechnicalRequestReviewedNotification` saat accepted/assigned.
- **File:** `app/Http/Controllers/TechnicalRequestController.php`
- **Verifikasi:** Sales kirim request → tidak ada notifikasi; Amir membalas → Sales dapat notifikasi.

### B6 · INS-1 — Klik notif request penawaran
- **Masalah:** klik notifikasi tidak langsung ke detail request.
- **Perbaikan:** arahkan URL ke `sales.price-requests.show`; pastikan presenter & hak akses `manage-inside-sales` cocok.
- **File:** `app/Notifications/PriceRequestCreatedNotification.php`, `app/Support/NotificationPresenter.php`
- **Verifikasi:** satu klik dari bell langsung membuka detail request.

### B7 · SAL-6 — Menu "Hasil Pekerjaan" read-only
- **Masalah:** link notifikasi hasil survey/instalasi minta login teknisi; role lain kena 403.
- **Akar masalah:** `SurveyController::guardRead()` hanya meloloskan `view-technician` atau sales pemilik data.
- **Perbaikan:**
  1. Longgarkan **baca** untuk `view-sales`, `view-admin`, management, CEO (§12: baca saja, tanpa hak ubah).
  2. Buat halaman "Hasil Pekerjaan" (read-only) berisi survey, instalasi, meeting teknis, troubleshooting, maintenance.
  3. Arahkan URL notifikasi hasil kerja ke halaman ini.
  4. Tambah menu di sidebar; tombol aksi ikuti §7.
- **File:** `app/Http/Controllers/SurveyController.php`, route + controller + view baru, `resources/views/layouts/partials/sidebar.blade.php`
- **Verifikasi:** Sales/Admin/Management bisa melihat hasil tanpa login teknisi; teknisi tetap bisa.

### B8 · TEK-2 — Cek bentrok jadwal
- **Masalah:** request teknisi bisa bentrok dengan jadwal yang sudah ada.
- **Perbaikan:**
  1. Saat request dibuat/di-accept, cek `TechnicianSchedule` untuk teknisi & rentang tanggal yang sama.
  2. Bila bentrok: tandai jelas di UI + blokir assign sampai diubah.
  3. Sediakan aksi "Usul perubahan jadwal" yang mengirim notifikasi ke Sales berisi usulan tanggal.
- **File:** `app/Http/Controllers/TechnicalRequestController.php`, `app/Models/TechnicianSchedule.php`, view request
- **Verifikasi:** request dengan tanggal bentrok ditandai; usulan jadwal terkirim dan bisa dibalas Sales.

### B9 · TEK-3 — Auto Schedule saat accept
- **Masalah:** setelah diterima, jadwal harus dibuat manual.
- **Perbaikan:** di `assign()`, buat `TechnicianSchedule` dari data request (tanggal, teknisi, project, customer). Wajib idempoten — request yang sama tidak membuat dua jadwal (kunci unik `technical_request_id` atau cek exists).
- **File:** `app/Http/Controllers/TechnicalRequestController.php`, `TechnicianScheduleController.php`
- **Verifikasi:** accept sekali → satu jadwal muncul; accept ulang → tetap satu.

### B10 · TEK-6 — Dashboard Teknisi tracking PIC
- **Masalah:** tidak terlihat teknisi mana yang masih pegang project aktif.
- **Perbaikan:**
  1. Kartu/daftar per teknisi: jumlah project aktif, project yang dia jadi PIC, status (aktif/selesai/kosong).
  2. Klik kartu → daftar project terkait.
  3. Tetap nyaman di mobile; warna solid sesuai §7.
- **File:** `app/Http/Controllers/TechnicianDashboardController.php`, `resources/views/teknisi/dashboard.blade.php`
- **Verifikasi:** lead teknisi bisa melihat beban kerja semua teknisi dalam satu layar.

### B11 · TEK-5 — Field Work general + dokumentasi & BAST
- **Masalah:** Field Work baru menangani survey & instalasi.
- **Perbaikan:**
  1. Tambah tipe pekerjaan: meeting teknis, troubleshooting, maintenance.
  2. Satu form/daftar untuk semua tipe, dengan filter tipe.
  3. Upload dokumentasi (foto/file) per pekerjaan.
  4. Hasil pekerjaan tertaut ke project sehingga terbaca Sales (lewat halaman B7) dan Admin (BAST).
  5. BAST: serahkan dokumen ke `project_documents` dengan field yang disepakati (§3.4), lalu `BastController` (milik A) membacanya.
- **File:** `app/Http/Controllers/FieldWorkController.php`, `InstalasiController.php`, `SurveyController.php`, `resources/views/teknisi/field-work/**`
- **Verifikasi:** buat pekerjaan tiap tipe → muncul di daftar & detail; upload dokumen tampil; BAST bisa mengambil dokumennya.
- **Catatan:** item terberat, kerjakan terakhir. **Wajib dipecah jadi 2–3 commit** karena berpotensi > 15 file (§9): (a) tipe pekerjaan + daftar, (b) upload dokumentasi, (c) tautan BAST.

---

### Sesi B

> Tabel ini disalin dari rencana lama dan belum diperbarui. Status item perlu dicek ulang sebelum dikerjakan.

| No | ID | Status | Tanggal merge |
|---|---|---|---|
| B1 | TEK-4 | belum | — |
| B2 | UM-1 | belum | — |
| B3 | UM-2 | belum | — |
| B4 | UM-3 | belum | — |
| B5 | SAL-5 | belum | — |
| B6 | INS-1 | belum | — |
| B7 | SAL-6 | belum | — |
| B8 | TEK-2 | belum | — |
| B9 | TEK-3 | belum | — |
| B10 | TEK-6 | belum | — |
| B11 | TEK-5 | belum | — |

## Sisa Pekerjaan

- **UM-1** butuh keputusan operasional (worker queue permanen di server). Tanpa itu, delay tetap terasa walau kode sudah benar.
- **TEK-5** menyentuh tiga divisi. Kalau waktu mepet, pecah jadi dua bagian seperti catatan di B11.
