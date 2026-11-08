# PLAN-SISTEM-AUDIT-NOVEMBER: Audit Sistem November 2026

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sistem / Lintas Divisi
**Branch:** -
**Terakhir diperbarui:** 2026-11-08

> Konteks, prasyarat, aturan anti-tabrakan, dan 3 item terbuka. Detail per sesi ada di PLAN-SALES-HANDOFF-ADMIN dan PLAN-SISTEM-NOTIFIKASI-TEKNISI.

---

## 0. Kepatuhan `AGENTS.md`

| Aturan | Penerapan di rencana ini |
|---|---|
| §1 satu sesi = satu folder = satu branch | Sesi A dan B masing-masing punya worktree + branch sendiri (§2) |
| §2 alur satu arah fitur → main → pull | Urutan merge di §7 |
| §3 & §9 wip → squash satu paket | Aturan commit di §8, **satu commit per divisi**, tidak campur |
| §4 pindah branch hanya dengan tree bersih | Worktree baru dibuat dari `main` bersih, bukan dari branch kotor |
| §5 build hanya dari main | §8 poin 5 |
| §6 jangan commit `.env`/backups | §8 poin 7 |
| §7 konvensi tombol `<x-icon-button>` | Item yang menyentuh UI ditandai "ikuti §7" (A2, A6, A9, A10, A12, B7, B10) |
| §8 database safety | Migrasi hanya `php artisan migrate`, tidak ada `migrate:fresh` |
| §9 format commit + maks 15 file | Tabel scope commit di §8.2; B11 wajib dipecah |
| §10 maks 9 worktree aktif | Prasyarat §2 nomor 4: rapikan worktree dulu |
| §11 branch dibuat dari main terbaru | Worktree baru dari `main` hasil `git pull` |
| §12 mutasi di `manage-*`, baca di `view-*` | A5 (penerima notifikasi), A8 & B7 (baca saja, tanpa hak ubah) |
| §13 format laporan progres | Tidak berubah, rencana ini tidak menyentuh laporan |

---

## 1. Ringkasan

Alur sistem saat ini:

```
Marketing → Lead → Sales → Quotation → PO customer → Project → Survey/Instalasi → BAST → Admin (Invoice/Payment)
```

Audit menemukan 21 poin yang membuat sistem belum nyaman dipakai harian. Dari penelusuran kode ditemukan 3 lubang tambahan: **Admin tidak pernah dapat notifikasi saat PO customer diunggah atau project dibuat**, padahal secara desain Admin sudah boleh mulai purchasing di titik itu.

| Sesi | Tema | Jumlah item |
|---|---|---|
| **A** | Sales → Admin handoff | 12 |
| **B** | Notifikasi & Teknisi | 11 |

---

## 2. Prasyarat Sebelum Mulai (WAJIB)

Dua sesi harus berangkat dari **satu titik `main` yang sama dan bersih**. Tanpa ini, pembagian file tidak ada artinya.

> **Status per 2 November 2026:** nomor 1, 4, 5, 6, 7 sudah selesai. Nomor 2 sudah diputuskan (`feature/div-marketing` tidak di-merge). Nomor 3 (`feature/div-teknisi`) masih menunggu keputusan user.

| No | Langkah | Kenapa |
|---|---|---|
| 1 | Commit atau stash semua WIP di `main` (13 file modified + 5 untracked) | `resources/views/leads/*` dan `layouts/app.blade.php` sedang berubah; keduanya target sesi A/B |
| 2 | Putuskan implementasi PIC: `feature/div-marketing` (8 commit, `Customer.php` +108 baris) atau `main` (`b2e68a0f` + merge `50416168`) | Dua implementasi fitur sama; kalau dibiarkan, CUS-1 bentrok dengan dirinya sendiri |
| 3 | Putuskan WIP `feature/div-teknisi`: simpan ke branch backup atau buang | Worktree `tek` behind 333 commit dengan 257 file modified — tidak bisa dipakai kerja baru |
| 4 | **Rapikan worktree** supaya tidak melebihi batas `AGENTS.md` §10 | Sekarang sudah 9 worktree non-main (main + photo, ai, cus, mkt, mon, sales, sec, tek, user). Tambah 2 = 11 |
| 5 | Buat 2 worktree baru dari `main` yang sudah bersih | Perintah di bawah |
| 6 | Tambahkan kedua worktree ke tabel `AGENTS.md` §1 | Wajib menurut §11.5 — **sudah ditambahkan di commit ini** |
| 7 | Ganti brief lama di `docs/plans/` | Sudah diganti: `PLAN-SALES-HANDOFF-ADMIN.md` dan `PLAN-SISTEM-NOTIFIKASI-TEKNISI.md` |

### 2.1 Rapikan worktree dulu (§10) — **sudah dikerjakan**

Hasil pemeriksaan:

| Worktree | Temuan | Tindakan |
|---|---|---|
| `/var/www/3dyapp-ai` | `feature/ai-assistant` ahead 0, sudah ancestor `main`; satu-satunya file kotor `bubble-check.png` | worktree dihapus (branch disimpan) |
| `/home/yeski/3dyapp-photo` | `feature/sales-meeting-photos` ahead 3, tapi fitur foto bukti meeting sudah ada di `main` (`f02d2d33`); worktree bersih | worktree dihapus (branch disimpan) |

Hasilnya: **7 worktree non-main** → tambah 2 worktree baru = 9 (batas §10 terpenuhi).

### 2.2 Worktree sesi — **sudah dibuat**

| Sesi | Folder | Branch |
|---|---|---|
| A | `/home/yeski/3dyapp-handoff` | `feature/handoff-admin` |
| B | `/home/yeski/3dyapp-notif-tek` | `feature/notif-teknisi` |

Sudah dilengkapi: symlink `.env`, symlink `public/build` ke `public/build` main, dan `composer install`.

Kalau perlu dibuat ulang dari nol:

```bash
cd /var/www/3dyapp && git checkout main && git pull
git worktree add /home/yeski/3dyapp-handoff -b feature/handoff-admin main
git worktree add /home/yeski/3dyapp-notif-tek -b feature/notif-teknisi main
ln -sf /var/www/3dyapp/.env /home/yeski/3dyapp-handoff/.env
ln -s /var/www/3dyapp/public/build /home/yeski/3dyapp-handoff/public/build
```

Catatan: `/var/www` sekarang hanya bisa ditulis root, jadi worktree baru ditaruh di `/home/yeski/` sesuai catatan §1.

---

## 3. Aturan Anti-Tabrakan

### 3.1 Prinsip

1. **File yang sudah ada** hanya boleh diubah oleh pemiliknya (§3.3).
2. **File baru** bebas dibuat siapa saja, asal namanya tidak sama. Dua sesi boleh menambah file baru di folder yang sama.
3. `routes/web.php`, `lang/en.json`, `lang/id.json` **dipakai bersama** — aturannya di §3.2.
4. Jangan `git rebase`/`reset --hard` branch sesi lain (§4).
5. Sebelum merge: `git pull origin main` di branch sendiri, selesaikan konflik, jalankan test.

### 3.2 File bersama

| File | Aturan |
|---|---|
| `routes/web.php` | Tambah route **hanya di blok modul sendiri**, jangan mengubah urutan blok lain. Saat konflik: **pertahankan keduanya**. |
| `lang/en.json`, `lang/id.json` | Tambah key baru di akhir. Saat konflik: **keep both**, jangan pakai `--ours` / `--theirs`. Konflik di sini normal dan selesai 1–2 menit. |
| `database/migrations/**` | Timestamp berbeda. Cek isi folder sebelum membuat file baru; kalau bentrok detik, geser 1 menit. |
| `docs/plans/**` | Hanya diubah untuk memperbarui isi/status rencana, bukan untuk mengubah kode. |

### 3.3 Peta kepemilikan file

| File / folder | Pemilik | Keterangan |
|---|---|---|
| `app/Http/Controllers/ProposalController.php` | **A** | ADM-1, ADM-3, TEK-1 |
| `app/Models/Proposal.php` | **A** | label status handoff |
| `app/Http/Controllers/LeadController.php`, `app/Models/Lead.php`, `resources/views/leads/**` | **A** | MKT-1 |
| `app/Http/Controllers/CustomerController.php`, `app/Models/Customer.php`, `resources/views/customers/**` | **A** | CUS-1 |
| `resources/views/sales/**` | **A** | SAL-1..4, INS-2 |
| `resources/views/admin/**`, `PurchasingHandoffController`, `AdminService`, `BastController` | **A** | ADM-1b, ADM-2, BAST |
| `resources/views/components/handoff-status.blade.php` (baru) | **A** | dipakai Sales & Admin |
| `app/Notifications/PurchasingHandoffNotification.php` (baru) | **A** | kelas baru, tidak bentrok |
| `app/Http/Controllers/TechnicalRequestController.php` | **B** | UM-3, SAL-5, TEK-2, TEK-3 |
| `app/Http/Controllers/SurveyController.php` | **B** | TEK-4, SAL-6 |
| `app/Http/Controllers/FieldWorkController.php`, `InstalasiController.php` | **B** | TEK-5 |
| `app/Models/TechnicalRequest.php`, `Survey.php`, `Instalasi.php`, `TechnicianSchedule.php` | **B** | TEK-2..5 |
| `app/Notifications/**` (file yang sudah ada) | **B** | UM-1, INS-1, SAL-6 |
| `app/Http/Controllers/NotificationController.php`, `app/Support/NotificationPresenter.php`, `app/Events/NotificationCreated.php` | **B** | UM-1, UM-2 |
| `resources/js/components/NotificationCenter.jsx`, `resources/js/app.js`, `resources/js/echo.js` | **B** | UM-2 |
| `resources/views/layouts/app.blade.php` | **B** | bootstrap notifikasi |
| `resources/views/teknisi/**` | **B** | kecuali `dashboard.blade.php` |
| `resources/views/teknisi/dashboard.blade.php`, `TechnicianDashboardController.php` | **B** | TEK-6 |
| `resources/views/layouts/partials/sidebar.blade.php` | **B** | hanya menambah menu "Hasil Pekerjaan" |
| `config/queue.php` | **B** | UM-1 |

### 3.4 Handshake antar sesi (titik temu, bukan tabrakan)

| Titik | Sesi A | Sesi B |
|---|---|---|
| Notifikasi handoff | Membuat kelas `PurchasingHandoffNotification` + dispatch | Tidak ikut. `NotificationPresenter` punya fallback generik, jadi kelas baru tetap tampil. Kalau mau chip/ikon khusus, B menambah mapping **setelah** A merge. |
| TEK-1 (access denied proposal) | Perbaiki `ProposalController::authorizeView` | Perbaiki URL di `ProposalStatusNotification` bila perlu (file milik B) |
| BAST | Menerima dokumen di `BastController` + views admin | Menghasilkan dokumen dari Field Work (TEK-5) |
| Kontrak data BAST | Baca `project_documents` dengan `project_id`, `type`, `path`, `uploaded_by` | Tulis dengan field yang sama |

---

## 4. Ringkasan Pembagian

### Sesi A — "Sales → Admin Handoff"

| No | ID | Pekerjaan | Fase |
|---|---|---|---|
| A1 | MKT-1 | Field Location di Add Lead | 0 |
| A2 | SAL-4 | Back preview quotation ke menu Quotation | 0 |
| A3 | ADM-2 | Komponen status handoff | 1 |
| A4 | ADM-3 | Validasi kelengkapan PO di Sales | 1 |
| A5 | ADM-1 | Dispatch notifikasi ke Admin | 1 |
| A6 | ADM-1b | Tampilan antrean Purchasing | 1 |
| A7 | CUS-1 | Sinkron PIC di overview customer | 1 |
| A8 | TEK-1 | Access denied notifikasi proposal | 2 |
| A9 | SAL-1 | Tips Add Follow Up | 3 |
| A10 | SAL-2 | Loader/success check add follow up | 3 |
| A11 | SAL-3 | Rapikan tabel Request Inside Sales | 3 |
| A12 | INS-2 | Rombak Change Status di View Detail Request | 3 |

### Sesi B — "Notifikasi & Teknisi"

| No | ID | Pekerjaan | Fase |
|---|---|---|---|
| B1 | TEK-4 | Lampiran survey terima doc/docx/xls | 0 |
| B2 | UM-1 | Hilangkan delay notifikasi | 2 |
| B3 | UM-2 | Penanda "terakhir dilihat", hilangkan tumpukan | 2 |
| B4 | UM-3 | Notifikasi teknisi saat di-assign jadi PIC | 2 |
| B5 | SAL-5 | Matikan notifikasi request engineer ke Sales | 2 |
| B6 | INS-1 | Klik notif request penawaran langsung ke detail | 2 |
| B7 | SAL-6 | Menu "Hasil Pekerjaan" read-only | 2 |
| B8 | TEK-2 | Cek bentrok jadwal + usul perubahan | 4 |
| B9 | TEK-3 | Accept request → otomatis Schedule | 4 |
| B10 | TEK-6 | Dashboard Teknisi lacak PIC & project aktif | 4 |
| B11 | TEK-5 | Field Work general + dokumentasi & BAST | 5 |

---

> Detail pekerjaan Sesi A ada di [PLAN-SALES-HANDOFF-ADMIN](PLAN-SALES-HANDOFF-ADMIN.md); Sesi B ada di [PLAN-SISTEM-NOTIFIKASI-TEKNISI](PLAN-SISTEM-NOTIFIKASI-TEKNISI.md).

---

## 7. Urutan Kerja & Titik Sinkronisasi

```
Hari 1        A: A3 (komponen status) ─────────────┐
              B: B1 (lampiran) + B2 (delay)        │
                                                   │
Hari 2-3      A: A4, A5, A6 (pakai A3)             │
              B: B3, B4, B5                        │
                                                   │
Hari 4-5      A: A7, A8, A1, A2                    │
              B: B6, B7                            │
                                                   │
Hari 6-8      A: A9, A10, A11, A12                 │
              B: B8, B9, B10                       │
                                                   │
Hari 9-14     A: review + test + merge final       │
              B: B11 (TEK-5) ──────────────────────┘
```

**Titik sinkronisasi wajib:**

| Kapan | Siapa | Aksi |
|---|---|---|
| A3 selesai | A | Beri tahu B; B tidak butuh file itu, hanya informasi |
| A5 selesai | A | Beri tahu B; B menambah mapping presenter bila perlu tampilan khusus |
| B7 selesai | B | Beri tahu A; A memastikan link BAST/notifikasi konsisten |
| Sebelum B11 | B | Sepakati field dokumen BAST dengan A (§3.4) |
| Setiap merge | keduanya | `git pull origin main` di branch sendiri sebelum lanjut |

**Urutan merge yang disarankan:** A dulu (menyentuh `ProposalController` dan alur handoff), lalu B `git pull origin main` sebelum merge. Ini menghindari B bekerja di atas `main` lama.

---

## 8. Aturan Commit, Test, Build

### 8.1 Alur

1. Selama belum sesuai: `wip(<divisi>/<menu>): ...` (§3).
2. Setelah user menyatakan sesuai: squash `wip` jadi **satu commit paket per divisi** (`scripts/squash-wip.sh`).
3. Sebelum merge ke `main`: `php artisan test` lulus.
4. Setelah merge: `git pull` di worktree lain.
5. `npm run build` **hanya** dari `/var/www/3dyapp` (main), bila ada kelas Tailwind baru.
6. Setelah artisan/test di main sebagai non-www-data: `sudo -u www-data php artisan view:clear`.
7. Jangan commit: `.env`, `backups/`, file log, screenshot debug.

### 8.2 Scope commit per sesi (§9: dilarang campur divisi)

| Sesi | Item | Scope commit |
|---|---|---|
| A | A1 | `mkt/leads` |
| A | A2, A9, A10, A11, A12 | `sales/*` (quotation, followups, requests, price-request) |
| A | A3, A4, A5, A8 | `sales/quotation` (semua di `ProposalController` / view quotation) |
| A | A6 | `panel/purchasing` |
| A | A7 | `cus/customers` |
| B | B1, B4, B8, B9, B11 | `tek/*` |
| B | B2, B3, B6 | `sys/notif` |
| B | B5 | `tek/requests` |
| B | B7 | `tek/field-work` |
| B | B10 | `dash/teknisi` |

Contoh pesan:

```
feat(sales/quotation): status handoff dan validasi PO customer
feat(panel/purchasing): penanda antrean siap proses dan tautan handoff
fix(cus/customers): sinkronkan PIC overview dengan kontak customer
feat(sys/notif): notifikasi handoff PO dan project ke admin
feat(tek/field-work): generalisasi pekerjaan lapangan
```

Body wajib 2–5 baris untuk `feat`/`fix`/`style`: apa berubah, maksud, cara verifikasi.

---

## 9. Checklist Progres

Status hanya boleh diubah **setelah item merge ke `main`**, bukan setelah selesai di branch.

### Sesi A

| No | ID | Status | Tanggal merge |
|---|---|---|---|
| A1 | MKT-1 | belum | — |
| A2 | SAL-4 | belum | — |
| A3 | ADM-2 | belum | — |
| A4 | ADM-3 | belum | — |
| A5 | ADM-1 | belum | — |
| A6 | ADM-1b | belum | — |
| A7 | CUS-1 | belum | — |
| A8 | TEK-1 | belum | — |
| A9 | SAL-1 | belum | — |
| A10 | SAL-2 | belum | — |
| A11 | SAL-3 | belum | — |
| A12 | INS-2 | belum | — |

### Sesi B

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

---

## 10. Catatan Terbuka

1. **INS-2** belum ada detail bentuk yang diinginkan — konfirmasi ke user sebelum menulis kode.
2. **UM-1** butuh keputusan operasional (worker queue permanen di server). Tanpa itu, delay tetap terasa walau kode sudah benar.
3. **TEK-5** menyentuh tiga divisi. Kalau waktu mepet, pecah jadi dua bagian seperti catatan di B11.
4. **`lang/*.json`** akan tetap bentrok kecil saat merge — disengaja, penyelesaiannya keep both.
5. **Worktree** baru menambah jumlah melewati batas §10 kalau tidak ada yang dirapikan lebih dulu — lihat §2.1.
