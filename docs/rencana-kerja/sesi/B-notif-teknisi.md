# Brief Sesi B — Notifikasi & Teknisi

> Bagian dari [Rencana Induk](../rencana-induk.md). Baca file induk untuk detail tiap item (masalah, perbaikan, file, verifikasi).

- **Worktree:** `/home/yeski/3dyapp-notif-tek`
- **Branch:** `feature/notif-teknisi`
- **Base:** `main` yang sudah bersih (lihat §2 rencana induk)
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
