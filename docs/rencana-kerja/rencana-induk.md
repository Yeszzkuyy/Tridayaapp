# Rencana Kerja 2 Sesi — Hasil Audit Manual November 2026

- **Tanggal:** 2 November 2026
- **Sumber:** audit manual per divisi + penelusuran kode
- **Jumlah poin:** 21 temuan audit + 3 temuan tambahan handoff Admin
- **Pembagian:** 2 sesi kerja paralel (A dan B), tanpa file yang sama
- **Status:** rencana, belum dikerjakan

> **Untuk AI agent yang membaca file ini:** cukup baca file ini sampai selesai. Bagian §5 dan §6 adalah pekerjaanmu. Jangan menyentuh file milik sesi lain (§3.3). Kalau butuh file sesi lain, tunggu merge atau minta ke user — jangan edit.

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

| No | Langkah | Kenapa |
|---|---|---|
| 1 | Commit atau stash semua WIP di `main` (13 file modified + 5 untracked) | `resources/views/leads/*` dan `layouts/app.blade.php` sedang berubah; keduanya target sesi A/B |
| 2 | Putuskan implementasi PIC: `feature/div-marketing` (8 commit, `Customer.php` +108 baris) atau `main` (`b2e68a0f` + merge `50416168`) | Dua implementasi fitur sama; kalau dibiarkan, CUS-1 bentrok dengan dirinya sendiri |
| 3 | Putuskan WIP `feature/div-teknisi`: simpan ke branch backup atau buang | Worktree `tek` behind 333 commit dengan 257 file modified — tidak bisa dipakai kerja baru |
| 4 | **Rapikan worktree** supaya tidak melebihi batas `AGENTS.md` §10 | Sekarang sudah 9 worktree non-main (main + photo, ai, cus, mkt, mon, sales, sec, tek, user). Tambah 2 = 11 |
| 5 | Buat 2 worktree baru dari `main` yang sudah bersih | Perintah di bawah |
| 6 | Tambahkan kedua worktree ke tabel `AGENTS.md` §1 | Wajib menurut §11.5 — **sudah ditambahkan di commit ini** |
| 7 | Ganti brief lama di `docs/rencana-kerja/sesi/` | Sudah diganti: `A-handoff.md` dan `B-notif-teknisi.md` |

### 2.1 Rapikan worktree dulu (§10)

```bash
git worktree list
git log --oneline main..feature/ai-assistant | wc -l    # hasil: 0 → aman dihapus
git log --oneline main..feature/sales-meeting-photos    # hasil: 3 commit, putuskan dulu
```

Rekomendasi:
- `feature/ai-assistant` — ahead 0, tidak ada commit yang hilang → hapus worktree-nya, branch boleh disimpan.
- `feature/sales-meeting-photos` — ahead 3; cek isinya, merge kalau relevan, baru hapus.
- `feature/div-teknisi` — **jangan dihapus**, tapi bersihkan WIP-nya (prasyarat nomor 3).

```bash
git worktree remove /var/www/3dyapp-ai
git worktree prune
```

### 2.2 Membuat worktree

```bash
cd /var/www/3dyapp
git checkout main && git pull

# Sesi A
git worktree add /home/yeski/3dyapp-handoff -b feature/handoff-admin main
ln -sf /var/www/3dyapp/.env /home/yeski/3dyapp-handoff/.env
ln -s /var/www/3dyapp/public/build /home/yeski/3dyapp-handoff/public/build
cd /home/yeski/3dyapp-handoff && composer install

# Sesi B
cd /var/www/3dyapp
git worktree add /home/yeski/3dyapp-notif-tek -b feature/notif-teknisi main
ln -sf /var/www/3dyapp/.env /home/yeski/3dyapp-notif-tek/.env
ln -s /var/www/3dyapp/public/build /home/yeski/3dyapp-notif-tek/public/build
cd /home/yeski/3dyapp-notif-tek && composer install
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
| `docs/rencana-kerja/**` | Hanya diubah saat memperbarui status (§9), bukan oleh sesi fitur. |

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

## 5. Sesi A — Detail Pekerjaan

**Worktree:** `/home/yeski/3dyapp-handoff` · **Branch:** `feature/handoff-admin`

### A1 · MKT-1 — Field Location di Add Lead
- **Masalah:** form Add Lead belum punya lokasi, padahal dibutuhkan untuk pemetaan wilayah.
- **Perbaikan:**
  1. Migrasi baru: kolom `location` (`string(255)`, nullable) di tabel `leads`.
  2. Tambahkan `location` ke `Lead::$fillable` dan validasi di `LeadController` (store & update).
  3. Input di `leads/create.blade.php` dan `leads/edit.blade.php`; tampilkan di `leads/show.blade.php`.
  4. Ikutkan di pencarian/filter halaman index bila filter wilayah sudah ada.
- **File:** migrasi baru, `app/Models/Lead.php`, `app/Http/Controllers/LeadController.php`, `resources/views/leads/**`
- **Verifikasi:** buat lead baru dengan lokasi → tersimpan → tampil di detail; edit lokasi → berubah; lead lama tidak error.
- **Catatan:** `resources/views/leads/**` sedang ada WIP di `main`. Selesaikan prasyarat §2 nomor 1 lebih dulu.

### A2 · SAL-4 — Back preview quotation
- **Masalah:** setelah Accept Quotation lalu membuka preview, tombol Back mendarat di Lead Detail.
- **Perbaikan:** arahkan tombol Back ke `route('sales.proposals.index')`. Tombol harus `<x-icon-button icon="back">` (ikuti `AGENTS.md` §7), bukan tombol teks.
- **File:** `resources/views/sales/proposals/show.blade.php`, `preview.blade.php`
- **Verifikasi:** Accept quotation → preview → Back → daftar Quotation Sales, bukan detail lead.

### A3 · ADM-2 — Komponen status handoff
- **Masalah:** Sales dan Admin memakai istilah tahap berbeda; tidak ada patokan bersama.
- **Perbaikan:**
  1. Buat `resources/views/components/handoff-status.blade.php` dengan prop `proposal`.
  2. Tahap: `Waiting for Customer PO → PO Received → In Purchasing → Project Created → BAST → Closed`.
  3. Sumber data: `Proposal::purchasingStatusLabel()` + relasi `project` + status BAST. Tambah method bila perlu.
  4. Pakai di `resources/views/sales/proposals/show.blade.php`; nanti dipakai Admin (A6).
- **File:** komponen baru, `app/Models/Proposal.php`, `resources/views/sales/proposals/show.blade.php`
- **Verifikasi:** satu quotation menampilkan tahap yang sama di Sales dan di antrean Purchasing.
- **Kerjakan lebih dulu** karena A6 menunggu komponen ini.

### A4 · ADM-3 — Validasi kelengkapan PO
- **Masalah:** Sales bisa menyerahkan data setengah jadi; blokir baru muncul saat convert project.
- **Perbaikan:**
  1. Di `uploadPo`, wajibkan: file PO, `po_number`, `payment_scheme`, `delivery_deadline` (kecuali sudah terisi sebelumnya).
  2. Tampilkan checklist kelengkapan di halaman quotation: empat item, centang/merah.
  3. Tombol "Convert to Project" nonaktif + alasan jelas bila checklist belum lengkap.
- **File:** `app/Http/Controllers/ProposalController.php`, `resources/views/sales/proposals/show.blade.php`
- **Verifikasi:** tanpa nomor PO tidak bisa simpan PO; tanpa checklist lengkap tombol convert tidak jalan dan alasannya terbaca.

### A5 · ADM-1 — Dispatch notifikasi ke Admin
- **Masalah:** Admin tidak pernah tahu PO sudah diunggah atau project sudah dibuat.
- **Perbaikan:**
  1. Buat `app/Notifications/PurchasingHandoffNotification.php` (payload minimal: `title`, `body`, `url`, `type`).
  2. Kirim ke user dengan `view-admin-hub` atau `manage-admin` (sesuai `AGENTS.md` §12), kecuali user yang melakukan aksi.
  3. Panggil di `uploadPo` (setelah simpan) dan di `convertToProject` (setelah project dibuat).
- **File:** notifikasi baru, `app/Http/Controllers/ProposalController.php`
- **Verifikasi:** Sales upload PO → akun Admin menerima notifikasi dan klik masuk ke antrean Purchasing. Convert project → notifikasi kedua, tidak dobel.
- **Catatan:** jangan mengubah `NotificationPresenter` (milik B). Presenter punya fallback generik.

### A6 · ADM-1b — Tampilan antrean Purchasing
- **Masalah:** antrean ada tapi tidak menunjukkan mana yang harus dikerjakan lebih dulu.
- **Perbaikan:**
  1. Badge "PO Received — siap diproses" vs "menunggu PO" (warna solid, ikuti §7).
  2. Jumlah antrean siap proses di halaman index.
  3. Tautan dari detail quotation ke antrean Purchasing.
  4. Pakai komponen `handoff-status` dari A3.
- **File:** `resources/views/admin/purchasing/**`, `app/Http/Controllers/PurchasingHandoffController.php`
- **Verifikasi:** Admin membuka Purchasing dan langsung tahu prioritas hari itu; angka antrean sesuai jumlah baris.

### A7 · CUS-1 — Sinkron PIC customer
- **Masalah:** PIC sudah diinput, tapi kartu "PIC WhatsApp" kosong dan "Total PICs" tidak bertambah.
- **Akar masalah:** `Customer::picWhatsapp()` hanya membaca `primaryContact()->whatsapp`; PIC dari lead tidak masuk `customer_contacts`; hitungan total memakai sumber lama.
- **Perbaikan:**
  1. Saat lead dibuat/diubah, sinkronkan `customer_contact_person` / `customer_whatsapp` menjadi kontak **primary** di `customer_contacts` (upsert, jangan duplikat).
  2. Fallback di model: kalau belum ada kontak primary, pakai kolom legacy customer.
  3. `Total PICs` dihitung dari kontak aktif (bukan kolom tunggal).
  4. Hormati guard "satu primary per customer" (migrasi 2026-10-29).
- **File:** `app/Models/Customer.php`, `app/Http/Controllers/CustomerController.php`, `resources/views/customers/show.blade.php`
- **Verifikasi:** input PIC di lead → buka customer → nama & WhatsApp muncul, Total PIC bertambah; edit PIC → tidak dobel.

### A8 · TEK-1 — Access denied notifikasi proposal
- **Masalah:** Amir klik notifikasi Proposal Update → 403.
- **Akar masalah:** `ProposalController::authorizeView()` hanya meloloskan owner / `manage-sales-leads` / `manage-admin` / CEO; `lead-technician` punya `manage-technician` + `view-sales` tapi tidak lolos.
- **Perbaikan:** perluas **baca** untuk pemegang `view-sales` atau `manage-technician` (read-only), tanpa memberi hak ubah. Hak tulis tetap di `authorizeManage`. Sesuai `AGENTS.md` §12: menambah `view-*` tidak membuka aksi tulis.
- **File:** `app/Http/Controllers/ProposalController.php`
- **Verifikasi:** login sebagai lead-technician → klik notifikasi proposal → halaman terbuka, tombol aksi tidak muncul.

### A9 · SAL-1 — Tips Add Follow Up
- **Masalah:** sales baru bingung kapan harus menekan Add Follow Up.
- **Perbaikan:**
  1. Kartu tips ringkas di My Leads saat pertama dibuka (dismissible, status disimpan per user mis. `localStorage`).
  2. Hint pada tombol Add Follow Up ketika ada lead baru di-assign (badge "baru" + satu kalimat panduan).
  3. Tombol/pill mengikuti §7 (solid, bukan transparan).
- **File:** `resources/views/sales/my-leads.blade.php`, `resources/views/sales/follow-ups/index.blade.php`
- **Verifikasi:** user baru melihat tips sekali; setelah dismiss tidak muncul lagi; lead baru memunculkan hint.

### A10 · SAL-2 — Loader/success add follow up
- **Masalah:** tidak ada umpan balik saat menyimpan follow up.
- **Perbaikan:** pakai pola tombol Save yang sudah ada (§7: shine + `transition-all` + `active:scale-95`) dan `x-status-card` sukses setelah simpan.
- **File:** `resources/views/sales/follow-ups/create.blade.php`, partial respons
- **Verifikasi:** submit → tombol disabled + spinner → kartu sukses muncul → data masuk daftar.

### A11 · SAL-3 — Tabel Request Inside Sales
- **Masalah:** deskripsi penawaran terlalu panjang, kolom lain menjadi sempit.
- **Perbaikan:** batasi kolom deskripsi (`max-w`, `line-clamp-2`/`truncate` + `title` untuk teks penuh), beri lebar tetap untuk kolom status/tanggal/aksi, minimalisir scroll horizontal. Aksi baris tetap icon-only (§7).
- **File:** `resources/views/sales/requests/index.blade.php`
- **Verifikasi:** deskripsi panjang tidak mendorong kolom lain; teks penuh tetap bisa dibaca (tooltip/detail).

### A12 · INS-2 — Change Status di View Detail Request
- **Masalah:** alur ubah status membingungkan.
- **Perbaikan:** **konfirmasi dulu ke user** bentuk yang diinginkan. Usulan awal: tombol aksi eksplisit per status (Review, Minta Revisi, Terima, Batalkan) dengan konfirmasi, bukan dropdown mentah; tampilkan status sekarang + langkah berikutnya. Semua tombol aksi icon-only (§7).
- **File:** `resources/views/sales/price-requests/show.blade.php`
- **Verifikasi:** alur status bisa dijelaskan dalam satu kalimat; tidak ada aksi ganda/ambigu.

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
