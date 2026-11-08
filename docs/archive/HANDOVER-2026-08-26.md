# HANDOVER — Serah Terima Antar Sesi AI

> Terakhir diperbarui: **26 Agustus 2026**, oleh sesi ox-alpha.
> Tujuan dokumen ini: sesi berikutnya bisa melanjutkan pekerjaan tanpa mengulang eksplorasi dari nol.

## 1. Snapshot Kondisi Repo (per 26 Agu 2026)

- Branch utama `/var/www/3dyapp` ada di `main`, commit `ad15ea4`, **working tree bersih**.
- Semua worktree divisi sinkron dengan `main` (tidak ada kerjaan belum merge):

| Folder | Branch | Status |
|---|---|---|
| `/var/www/3dyapp` | `main` | Bersih, melayani website |
| `/var/www/3dyapp-mkt` | `feature/div-marketing` | Sinkron dgn main |
| `/var/www/3dyapp-tek` | `feature/div-teknisi` | Sinkron dgn main |
| `/var/www/3dyapp-user` | `feature/user-management-avatar` | Sinkron dgn main |
| `/var/www/3dyapp-mon` | `feature/div-mon` | Sinkron dgn main |

- Laravel Framework 13.14.0. Sebelum mulai apa pun: baca `AGENTS.md` (aturan multi-sesi, warna tombol, larangan).

## 2. Peta Dokumentasi (jangan eksplorasi manual, baca ini dulu)

| Butuh tahu tentang | Buka |
|---|---|
| Gambaran produk & kebutuhan user | `docs/PRD.md` |
| Struktur DB & ERD | `docs/database-v1.md`, `docs/erd-v1.md` |
| Desain sistem | `docs/system-design-v1.md` |
| Rencana frontend/wireframe | `docs/Frontend_Plan_Wireframe.md` |
| Dark mode (kenapa tombol dilarang pakai bg-slate/gray/white) | `docs/DARK_MODE_SYSTEM.md` |
| Integrasi Google Calendar jadwal teknisi | `docs/GOOGLE_CALENDAR.md` |
| Audit modul marketing | `docs/AUDIT_MODUL_MARKETING.md` |
| Knowledge graph kodebase | `graphify-out/GRAPH_REPORT.md` |

## 3. Yang Sudah Selesai (jangan dikerjakan ulang)

- Modul inti semua divisi: Lead/Marketing (`leads` + `lead_activities` + `lead_documents`, rework 24 Agu), Sales (meeting, follow-up), Admin (invoice, PO, payment), Teknisi (dashboard, `technician_schedules` + sinkron Google Calendar satu arah), Monitoring (progress per customer), Partner, Projects + dokumen + task + support.
- User management: role/permission (spatie), avatar user (`x-user-avatar` dipakai konsisten), last_activity.
- Trash (soft delete + restore customer/project) di `/trash` — sudah ada di main via admin routes.
- Konsolidasi UI: konvensi warna tombol (bagian 7 AGENTS.md), dark mode input `color-scheme`, filter leads (pakai `incoming_date`, lead tetap tampil walau customer soft-deleted).
- Hapus lampiran lead (file fisik + record + log aktivitas).
- Manage Sales (branch `feature/manage-sales`): role `management` (Bu Yanita `yanita@dbteknisi.com`, Bu Ayu `ayu@dbteknisi.com` — password `password`, role diset via seeder/tinker) dengan permission `manage-sales-leads`. Marketing simpan lead tanpa assign → lead muncul di Data Lead Marketing (`/leads`) dan Manage Sales (`/manage-sales`) tanpa duplikat. Management mengisi Solusi / Progress FollowUp / Catatan Internal + Assign ke Sales (kolom `assigned_to` lama dipakai ulang; tambah `assigned_by`, `assigned_at`). Sales melihat lead-nya di `/sales/my-leads`. Field tsb dihapus dari form Tambah/Edit Lead; label "PT" → "Lead dari PT".

## 4. Rencana / Yang BELUM Dikerjakan (urutan prioritas)

### a. Worktree divisi baru (sesuai rencana di AGENTS.md)
Belum dibuat. Buat saat mulai dikerjakan:
```bash
git worktree add /var/www/3dyapp-cus feature/div-cus        # Customer
git worktree add /var/www/3dyapp-panel feature/div-panel    # Admin Panel
ln -sf /var/www/3dyapp/.env /var/www/3dyapp-cus/.env     # (+ panel, + build symlink public/build)
```
Catatan: **`feature/div-trash` kemungkinan sudah tidak perlu** — modul Trash sudah jadi di main (routes/admin.php:7-18). Konfirmasi dulu sebelum buat worktree.

### b. Google Calendar dua arah (roadmap tertulis)
Sudah didokumentasikan lengkap di `docs/GOOGLE_CALENDAR.md` bagian 7:
- Tambah `pullEvents()` di `GoogleCalendarService` (sudah ada `clientFor()` + token mechanism sebagai fondasi).
- Cocokkan via `google_event_id` di `technician_schedules`, pakai `syncToken`/`updatedMin`.
- Job queue untuk polling berkala.

### c. Role marketing belum terpasang ke user
Audit lama (`AUDIT_MODUL_MARKETING.md`) mencatat: role `marketing` ada tapi 0 user memakainya → menu marketing tidak muncul untuk siapa pun. Cek ulang kondisi terkini; kalau masih 0, tetapkan user mana yang dapat role tersebut (butuh input owner).

### d. Data lead masih kosong
Fitur sudah jalan via UI, tinggal diisi/di-seed kalau diminta data contoh.

### e. Catatan teknis tertanda `ponytail:` (utang sadar, bukan bug)
- `app/Http/Controllers/TechnicianDashboardController.php:89` — pencocokan nama via kolom teks `pic_engineer`/`support_technicians`; pindah ke relasi kalau matching drift.
- `app/Http/Controllers/MonitoringController.php:151` — sort di PHP setelah full load; pindah ke subquery SQL kalau customer sudah ribuan.

## 5. Aturan Main (ringkasan — detail di AGENTS.md)

1. Satu sesi = satu worktree = satu branch. **Jangan pindah branch di worktree milik sesi lain.**
2. Alur: fitur di worktree divisi → merge ke `main` → semua worktree `git pull`. Jangan cherry-pick antar divisi.
3. Sebelum merge: `php artisan test` harus lulus.
4. Website dilayani dari `main`; setelah merge ada perubahan CSS/JS → `npm run build` (kelas Tailwind baru tidak akan compile tanpa build).
5. Warna tombol ikut tabel konvensi di AGENTS.md bagian 7. Dilarang `bg-slate-*`/`bg-gray-*`/`bg-white` sebagai background tombol.
6. Jangan commit: `backups/`, `.env`/kredensial, file eksperimen.
7. Commit kecil tapi sering; pesan commit menjelaskan fiturnya.

## 6. Langkah Pertama Sesi Berikutnya

```bash
git pull origin main                 # di folder masing-masing
# lalu kerjakan item prioritas di bagian 4 sesuai divisi,
# atau tanyakan owner kalau urutan berubah.
```

Perbarui dokumen ini setiap akhir sesi: geser item dari "belum" ke "selesai", tambahkan temuan baru.
