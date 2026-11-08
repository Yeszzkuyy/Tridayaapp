# PLAN-SALES-HANDOFF-ADMIN: Handoff Sales ke Admin

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Admin Handoff
**Branch:** feature/handoff-admin
**Terakhir diperbarui:** 2026-11-08

> Sebagian besar item sudah merge ke main; A12 (INS-2) menunggu keputusan bentuk dari user. Status item perlu dicek ulang.

---
## Brief Sesi A — Sales → Admin Handoff

> Bagian dari [PLAN-SISTEM-AUDIT-NOVEMBER](PLAN-SISTEM-AUDIT-NOVEMBER.md). Baca file induk untuk detail tiap item (masalah, perbaikan, file, verifikasi).

- **Worktree:** `/home/yeski/3dyapp-handoff`
- **Branch:** `feature/handoff-admin`
- **Base:** `main` yang sudah bersih (lihat §2 PLAN-SISTEM-AUDIT-NOVEMBER)
- **Jumlah item:** 12
- **Batas teknis:** ikuti `AGENTS.md` §2 (alur merge), §3/§9 (wip → squash), §7 (tombol), §12 (role)

---

## Urutan kerja

| Urut | No | ID | Singkat | Commit scope |
|---|---|---|---|---|
| 1 | A3 | ADM-2 | Komponen status handoff (ditunggu A6) | `sales/quotation` |
| 2 | A4 | ADM-3 | Validasi kelengkapan PO di Sales | `sales/quotation` |
| 3 | A5 | ADM-1 | Dispatch notifikasi ke Admin | `sales/quotation` |
| 4 | A6 | ADM-1b | Tampilan antrean Purchasing | `panel/purchasing` |
| 5 | A1 | MKT-1 | Field Location di Add Lead | `mkt/leads` |
| 6 | A2 | SAL-4 | Back preview quotation | `sales/quotation` |
| 7 | A7 | CUS-1 | Sinkron PIC overview customer | `cus/customers` |
| 8 | A8 | TEK-1 | Access denied notifikasi proposal | `sales/quotation` |
| 9 | A9 | SAL-1 | Tips Add Follow Up | `sales/followups` |
| 10 | A10 | SAL-2 | Loader/success add follow up | `sales/followups` |
| 11 | A11 | SAL-3 | Tabel Request Inside Sales | `sales/requests` |
| 12 | A12 | INS-2 | Change Status di View Detail Request | `sales/price-request` |

**Aturan commit:** dilarang mencampur dua divisi dalam satu commit (`AGENTS.md` §9). Karena sesi ini menyentuh 4 divisi, hasil akhirnya berupa beberapa commit paket (`mkt`, `sales`, `panel`, `cus`), bukan satu commit besar.

---

## File yang kamu miliki

- `app/Http/Controllers/ProposalController.php`
- `app/Models/Proposal.php`
- `app/Http/Controllers/LeadController.php`, `app/Models/Lead.php`, `resources/views/leads/**`
- `app/Http/Controllers/CustomerController.php`, `app/Models/Customer.php`, `resources/views/customers/**`
- `resources/views/sales/**`
- `resources/views/admin/**`, `PurchasingHandoffController`, `AdminService`, `BastController`
- `resources/views/components/handoff-status.blade.php` (baru)
- `app/Notifications/PurchasingHandoffNotification.php` (baru)

## Jangan disentuh

- `app/Http/Controllers/TechnicalRequestController.php` → B
- `app/Http/Controllers/SurveyController.php` → B
- `app/Notifications/**` selain file baru milikmu → B
- `app/Http/Controllers/NotificationController.php`, `app/Support/NotificationPresenter.php` → B
- `resources/js/**` → B
- `resources/views/teknisi/**` → B
- `resources/views/layouts/partials/sidebar.blade.php` → B
- `config/queue.php` → B

## File bersama

- `routes/web.php` — tambah route di blok sales/customer/admin saja, jangan ubah urutan blok lain.
- `lang/en.json`, `lang/id.json` — tambah key, saat konflik keep both.

## Temuan kunci yang harus dijaga

1. `Proposal::isReadyForPurchasing()` = `status === 'accepted'` **dan** `po_document_path` terisi.
2. `convertToProject()` sudah memblokir tanpa file PO, nomor PO, dan `payment_scheme` — jangan dilonggarkan, cukup diperjelas.
3. `ProposalController::authorizeView()` saat ini: owner / `manage-sales-leads` / `manage-admin` / CEO. `lead-technician` kena 403.
4. `LeadController::store` membuat kontak PIC hanya dengan `name` (tanpa `whatsapp`/`phone`) dan mengabaikan PIC pada mode customer existing — sumber masalah CUS-1. `feature/div-marketing` **tidak di-merge**; perbaiki langsung di `main`.
5. Penerima notifikasi Admin memakai `view-admin-hub` / `manage-admin` (§12), bukan role baru.

## Definition of Done (per item)

1. Perubahan sesuai permintaan user, sudah dicek di UI.
2. Tidak ada `dd()`/`dump()`/kode mati.
3. `php artisan test` lulus untuk logika yang disentuh.
4. Tombol mengikuti §7 (icon-only, warna solid, teks Inggris).
5. Tidak ada file sisa (`.bak`, screenshot debug, log).
6. Commit sesuai format §9 + body 2–5 baris.

## Template commit

```
feat(sales/quotation): status handoff seragam dan validasi PO customer
feat(panel/purchasing): penanda antrean siap proses dan tautan handoff
fix(cus/customers): sinkronkan PIC overview dengan kontak customer
fix(sales/proposals): izinkan lead technician membaca quotation
feat(mkt/leads): tambah field location pada add lead
feat(sales/followups): tips add follow up dan loader seragam
style(sales/requests): rapikan tabel request inside sales
feat(sales/price-request): rombak alur change status
```

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
- **Keputusan (2 Nov):** `feature/div-marketing` **tidak di-merge**. Implementasinya tumpang tindih dengan `CustomerContactService` yang sudah ada di `main`. Perbaikan dilakukan langsung di `main`.
- **Akar masalah (hasil cek kode):** tiga titik bocor.
  1. `LeadController::store` (~baris 475) — saat customer baru dibuat, `CustomerContactService::create()` hanya dikirim `name` + `is_primary`; `whatsapp` dan `phone` tidak ikut → kartu PIC WhatsApp kosong.
  2. `LeadController::store` (~baris 487) — pada mode "customer existing", PIC dari lead diabaikan bila customer sudah punya kontak (`! hasContacts`), jadi tidak pernah update → Total PIC tidak bertambah.
  3. `Customer::picWhatsapp()` hanya membaca `primaryContact()?->whatsapp`, tanpa fallback ke kolom lama customer (`whatsapp`), jadi hasilnya null saat kontak tidak punya WA.
- **Perbaikan:**
  1. Kirim `whatsapp`, `phone`, `email` ke `CustomerContactService::create()` dari field lead.
  2. Pada mode customer existing, sinkronkan PIC lead menjadi kontak primary (tambah/update), dengan tetap menjaga guard "satu primary per customer".
  3. Tambahkan fallback di `picWhatsapp()` (dan `picPhone()` bila perlu): WA kontak → telp kontak → WA customer.
  4. `Total PICs` sudah dihitung dari `$customer->contacts->count()`; pastikan kontak benar-benar dibuat/di-update pada kedua mode.
- **File:** `app/Http/Controllers/LeadController.php`, `app/Models/Customer.php`, `app/Services/CustomerContactService.php`, `resources/views/customers/show.blade.php`
- **Verifikasi:** input PIC di lead baru → buka customer → nama & WhatsApp muncul, Total PIC bertambah. Tambah lead kedua untuk customer yang sama dengan PIC berbeda → PIC ikut ter-update (tidak dobel), Total PIC menyesuaikan.

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

### Sesi A

> Tabel ini disalin dari rencana lama dan belum diperbarui. Status item perlu dicek ulang sebelum dikerjakan.

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

## Sisa Pekerjaan

- **INS-2** belum ada detail bentuk yang diinginkan — konfirmasi ke user sebelum menulis kode.
