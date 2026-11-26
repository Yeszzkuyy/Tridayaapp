# Panduan Testing Sales, Inside Sales, Estimator

**Tanggal:** 23 November 2026
**Repo:** `/var/www/3dyapp` (branch `main`)
**Tujuan:** Panduan uji manual per menu untuk tiga divisi yang saling terhubung:
Sales, Inside Sales, dan Estimator. Dipakai untuk mencatat temuan (bug, alur
macet, tampilan tidak sesuai) saat testing.

**Cara pakai:**

1. Baca Bagian 2 (akun) dan Bagian 3 (peta fitur) dulu supaya tahu menu apa
   yang seharusnya muncul untuk tiap role.
2. Jalankan skenario di Bagian 4 berurutan. Banyak skenario bergantung pada
   data dari skenario sebelumnya.
3. Tandai `[x]` bila sesuai, `[!]` bila tidak sesuai, lalu catat di Bagian 6.
4. Blok yang **memang sengaja ditolak sistem** ada di Bagian 3.6. Jangan
   dilaporkan sebagai bug.

---

## 1. Ringkasan Alur Besar

```text
Marketing / Management membuat & meng-assign Lead
        ↓
Sales menerima Lead (badge New) → follow up / meeting
        ↓
Sales butuh bantuan (pilih salah satu):
   ├─ Inside Sales  → minta dibuatkan quotation
   ├─ Price Request → minta harga instalasi ke Engineer
   ├─ Engineer      → minta survey / instalasi / troubleshooting
   └─ Product Price → minta harga modal produk ke Estimator
        ↓
Sales menyusun Quotation (wajib sudah ada follow up)
        ↓
Ready → Send → Customer Accept → Upload PO
        ↓
Admin verifikasi PO → Sales convert Quotation menjadi Project
```

Yang perlu dipahami tester:

- **Satu lead hanya boleh punya satu quotation aktif.** Untuk revisi, ubah
  quotation yang ada, jangan buat baru.
- **Quotation tidak bisa dibuat sebelum ada minimal satu follow up.** Ini
  aturan anti-ghosting, bukan bug.
- **Convert ke Project hanya dari quotation yang sudah accepted + PO
  terverifikasi Admin.** Tombol Convert di halaman Lead hanya untuk
  Admin/Teknisi (jalur lama non-quotation).
- **Setiap role punya batas PT.** Sales MGK tidak bisa menyentuh data TPS.

---

## 2. Akun Testing

Password semua akun: **`3dy@11830`**

| Role | Nama | Email | PT yang boleh ditulis | Catatan |
|---|---|---|---|---|
| Sales | Adi Santosa | `adi.santosa@tridayaapp.com` | MGK | Sales murni |
| Sales | Irfan | `irfan@tridayaapp.com` | NTI | Sales murni |
| Sales | Deby | `deby@tridayaapp.com` | TPS | Sales murni |
| Sales | Felisia | `felisia@tridayaapp.com` | WANI | Sales murni |
| Sales | Jeremy | `jeremy@tridayaapp.com` | NTI | Sales murni |
| Sales | Tsamara | `tsamara@tridayaapp.com` | NTI | Sales murni |
| Inside Sales | Audi | `audi@tridayaapp.com` | NTI | Punya role `inside-sales` + `sales` + `sales-nti` |
| Inside Sales | Dini | `dini@tridayaapp.com` | NTI | Sama seperti Audi |
| Estimator | Vanesha | `vanesha@tridayaapp.com` | - | Hanya role `estimator` |
| Management | Christina | `christina@tridayaapp.com` | MGK | Management + Sales MGK |
| Management | Yanita | `yanita@tridayaapp.com` | NTI | Management + Sales NTI |
| Management | Ayu | `ayu@tridayaapp.com` | TPS | Management + Sales TPS |
| Management | Ardian | `ardian@tridayaapp.com` | NTI | Management + Lead Teknisi + Sales NTI |
| Admin | Hanifah | `hanifah@tridayaapp.com` | - | Untuk verifikasi PO |
| Admin | April | `april@tridayaapp.com` | - | Untuk verifikasi PO |
| Marketing | Syifa | `syifa@tridayaapp.com` | - | Pembuat lead |
| CEO | Victor | `victor@tridayaapp.com` | - | Read-only semua divisi |

**Penting soal Audi & Dini:** mereka memegang dua role (Inside Sales + Sales
NTI). Jadi mereka melihat menu Sales lengkap **dan** menu Inside Sales. Ini
bukan bug. Kalau ingin menguji "Inside Sales murni", perlu akun dengan role
`inside-sales` saja (belum ada di seeder).

---

## 3. Peta Fitur per Divisi

### 3.1 Sales

Menu di sidebar grup **Sales** (urutan sesuai tampilan):

| Menu | Isi | Aksi utama | Batasan |
|---|---|---|---|
| **Dashboard** | KPI tim + KPI personal + target + funnel + aktivitas | Lihat ringkasan, klik kartu untuk filter | Angka global hanya untuk yang berhak |
| **My Leads** | Tabel lead milik sendiri | Add Lead, Add Follow Up, Export CSV, ubah stage, atur next follow up | Sales hanya melihat lead yang di-assign ke dia; Management/CEO bisa pilih Sales |
| **Follow Up** | Tab All Open / Due / Upcoming / Completed | Add Follow Up, complete, reopen, snooze | Yang boleh complete hanya pembuat follow up |
| **Meeting** | Tab My Meetings / Team Meetings + KPI | New Meeting, Input Report | Report wajib foto + MOM |
| **Schedule** | Kalender bulan/minggu/hari | Add schedule, drag/lihat detail | PIC harus Sales atau Inside Sales |
| **Quotation** | Daftar quotation + KPI status | Buat, kirim, accept/reject, upload PO, convert project | Satu lead satu quotation aktif |
| **Project** | Daftar project | Lihat detail project | Read-only untuk sales |
| **Request** | 4 tab: Inside Sales, Installation Price, Engineer, Product Price | New Request sesuai tab | Tiap tab punya syarat role berbeda |
| **Inside Sales** | Dashboard antrean Inside Sales | Buka task | Hanya muncul bila punya `manage-inside-sales` |

**Halaman Sales yang tidak punya menu di sidebar:**

| Halaman | URL | Kondisi |
|---|---|---|
| Pipeline Board | `/leads/pipeline` | Papan kanban lead milik sendiri. Saat ini tidak ada tombol/menu menuju halaman ini. Buka lewat URL langsung. |
| DAR (Daily Activity Report) | `/sales/dar` | Rekap aktivitas harian. Saat ini juga tidak ada menu menuju halaman ini. Buka lewat URL langsung. |

Dua halaman di atas **perlu dicatat sebagai temuan UX**: fitur ada, tapi user
tidak punya pintu masuk. Jangan dianggap rusak, tapi laporkan sebagai
"menu tidak bisa ditemukan".

#### Detail halaman penting

**My Leads**

- Kolom: #, Company/Customer, PIC, Contact, Project Name, Pipeline Stage,
  Latest Note, Last Activity, Next Follow Up, Action.
- Filter: Sales (hanya supervisor), Search customer, Pipeline Stage, Next
  Follow Up (Due Today / Upcoming / Not Scheduled), Last Activity, Sort by,
  More filters (Company, PIC, Project, Overdue > 24 jam).
- Banner panduan 3 langkah muncul untuk lead baru; bisa di-dismiss.
- Export CSV mengikuti filter yang sedang aktif.

**Lead Detail**

- Menampilkan status lead, ringkasan, jadwal berikutnya, data customer,
  kebutuhan, daftar quotation, daftar task Inside Sales, technical request,
  follow up, dan activity log.
- Aksi: Edit lead, Delete (marketing), Promote to Customer (kalau customer
  masih berstatus lead), Convert to Project (hanya Admin/Teknisi).
- Untuk lead `won`/`lost`, muncul kartu Final Result. Lead `lost` bisa diisi
  alasan (price, competitor, budget, requirement_changed, no_response, other).

**Pipeline Board** (`/leads/pipeline`)

- Kolom: **New** (lead yang baru di-assign, belum diakui), Cool, Warm, Hot,
  Won, Lost.
- Drag dari New ke Cool/Warm/Hot akan mengakui lead (acknowledged).
- Won/Lost hanya menampilkan yang ditutup minggu berjalan, kecuali
  `?closed=all`.
- Hanya lead milik sendiri yang tampil, termasuk untuk Management.

**Quotation**

- Status: `draft` → `pending_technical` / `ready` → `sent` → `viewed` →
  `revision` → `accepted` / `rejected`.
- Alur normal Sales: Draft → isi item/harga → Ready → Send → customer
  accept/reject → upload PO → convert project.
- `accepted` otomatis membuat lead `won`.
- Kirim/accept quotation ditolak bila item kosong atau grand total 0.

**Request Hub** (4 tab)

| Tab | Untuk apa | Yang boleh membuat |
|---|---|---|
| Inside Sales | Minta dibuatkan quotation | `manage-sales`, `manage-sales-leads`, `manage-marketing` |
| Installation Price | Minta harga instalasi, lampirkan quotation | `manage-sales`, `manage-inside-sales`, `manage-technician` |
| Engineer | Survey, instalasi, troubleshooting, demo, konsultasi | `manage-sales`, `manage-inside-sales` |
| Product Price | Minta harga modal, stok, garansi produk | `manage-sales`, `manage-sales-leads` |

### 3.2 Inside Sales

Menu di sidebar:

| Menu | Isi | Aksi utama |
|---|---|---|
| **Inside Sales → Dashboard** | KPI New Request / In Progress / Quotation Created / Done + Request Queue | Buka task, buat quotation |
| **All Requests** (dari dashboard) | Daftar seluruh task yang di-assign | Filter, buka detail |
| **Task Detail** | Brief sales, lampiran, item quotation, diskusi, riwayat file | Tambah item, upload file, accept file jadi quotation, komentar, selesaikan task |
| **Quotation Create** | Form quotation dari item task | Pull Latest Price, isi data, simpan, cetak PDF |

Alur kerja Inside Sales:

```text
Sales membuat Request Inside Sales (pilih lead + assign ke Audi/Dini)
        ↓
Task muncul di dashboard Inside Sales (status todo)
        ↓
Inside Sales buka task → baca brief → tambah item ATAU upload file quotation
        ↓
Bila item sudah lengkap: Create Quotation → isi data → simpan → PDF
        ↓
Bila upload file: Sales (pemilik lead/pembuat task) accept file
        → otomatis dibuat quotation draft
        ↓
Task ditandai selesai
```

Aturan penting:

- **Accept file quotation butuh item sudah ada.** Kalau item kosong, sistem
  menolak.
- **Satu lead hanya boleh punya satu quotation aktif.** Kalau sudah ada,
  accept file ditolak dan harus revisi quotation lama.
- **Yang boleh accept file:** pembuat task, pemilik lead, atau Management.
  Sales lain tidak boleh.
- **Batas PT tetap berlaku** saat accept file.
- Task tanpa assignee menampilkan banner kuning "This request has no Inside
  Sales assignee yet" + tombol Assign.

### 3.3 Estimator

Menu di sidebar grup **Estimator**:

| Menu | Isi | Aksi utama |
|---|---|---|
| **Queue** | KPI Waiting / In Progress / Price Received / Revision / Completed + antrean | Proses request, isi harga |
| **Price History** | Riwayat perubahan harga produk | Lihat histori |
| **Pricelist** | Katalog produk + status harga | Tambah/edit produk, bulk review harga |
| **Suppliers** | Daftar supplier/distributor | Lihat data supplier |

Alur kerja Estimator:

```text
Sales membuat Request Product Price (pilih customer + item)
        ↓
Request masuk Queue Estimator (status waiting)
        ↓
Estimator buka request → isi supplier, harga modal, stok, garansi, valid until
   (bisa minta harga ke supplier lewat tombol WhatsApp)
        ↓
Send prices to Sales → status answered
        ↓
Sales menerima harga → Accept (final) / Revision / Cancel
        ↓
Bila accepted → harga masuk sebagai referensi quotation
```

Aturan penting:

- Estimator **tidak melihat menu Sales**. Role `estimator` hanya punya
  `manage-price-requests` + `manage-products`.
- Setelah request dijawab, form harga tertutup. Untuk mengubah, Sales harus
  minta **Revision** dulu.
- Estimator bisa mengedit harga selama status masih `answered` (tombol Edit
  Prices), tapi tidak setelah `completed`.
- Cancel request dilakukan oleh Sales, bukan Estimator.

### 3.4 Alur Penugasan Lead (End to End)

Ini bagian yang paling sering bermasalah. Ikuti urutannya.

```text
1. Marketing/Management membuat lead
   - Pilih customer baru atau existing
   - Pilih PT (MGK/NTI/TPS/WANI)
   - Isi kebutuhan, project, segment, source
   - assigned_to boleh kosong (masuk daftar belum di-assign)

2. Management meng-assign
   - Menu Management → Manage Sales
   - Filter "NEW (Unassigned)"
   - Pilih Sales tujuan → Assign
   - Hanya user dengan role `sales` yang bisa menjadi target

3. Sales menerima lead
   - Muncul notifikasi Lead Assigned
   - Lead masuk kolom New di Pipeline Board
   - Badge merah "Unassigned leads" di sidebar hanya untuk Management

4. Sales wajib mengakui lead
   - Drag dari New ke Cool/Warm/Hot, ATAU ubah stage di My Leads
   - Kalau 24 jam belum diakui dan belum ada follow up:
     lead dihitung "Overdue" + reminder ke Sales

5. Sales follow up
   - Add Follow Up dari My Leads / Lead Detail / Follow Up
   - Isi catatan, tanggal, next follow up
   - Follow up pertama membuka gerbang pembuatan quotation
```

Aturan penugasan yang harus diuji:

| Aturan | Perilaku yang benar |
|---|---|
| Target assign harus role Sales | User non-sales ditolak (422) |
| Lead closed (won/lost) | Tidak bisa di-assign ulang |
| Reassign | Sales lama dapat notifikasi, sales baru dapat notifikasi |
| Lead belum di-assign | Tidak muncul di My Leads sales mana pun |
| Sales per PT | Hanya melihat/menulis lead PT-nya |
| Management | Bisa melihat semua, tapi Pipeline tetap hanya lead milik sendiri |

---

### 3.5 Gerbang & Penolakan yang Disengaja

Catat sebagai **bukan bug**:

| Situasi | Pesan / perilaku |
|---|---|
| Buat quotation tanpa follow up | "Record at least one follow-up update before creating a quotation" |
| Kirim quotation tanpa item | Ditolak, quotation harus punya item dan total > 0 |
| Buat quotation kedua untuk lead yang sama | Ditolak, harus revisi quotation lama |
| Accept file Inside Sales tanpa item | Ditolak (422) |
| Accept file yang sudah pernah di-accept | Ditolak (422) |
| Convert project tanpa PO | Ditolak |
| Convert project dengan PO belum diverifikasi Admin | Ditolak |
| Convert project dengan PO dikembalikan Admin | Ditolak |
| Sales MGK buka customer TPS | 403 |
| Sales lain buka quotation bukan miliknya | 403 |
| Complete follow up milik orang lain | 403 |
| Assign lead ke user non-sales | 422 |
| Assign ulang lead won/lost | Ditolak |

### 3.6 Yang Belum Dibangun (Jangan Dianggap Bug)

- Tombol/menu menuju Pipeline Board dan DAR belum ada.
- Belum ada akun "Inside Sales murni" di seeder (semua Inside Sales memegang
  role Sales juga).
- Halaman Supplier Estimator belum tentu punya aksi tambah/edit; cek dulu
  sebelum melaporkan.
- Beberapa label tombol masih campur Indonesia/Inggris. Itu masuk temuan
  konsistensi bahasa, bukan bug fungsi.

---

## 4. Skenario Testing

Format: **ID — Menu — Langkah — Hasil yang diharapkan**.
Tandai `[ ]` sesuai / `[!]` tidak sesuai.

### 4.1 Management / Marketing — Membuat & Meng-assign Lead

#### T-01 — Marketing membuat lead baru (customer baru)

- **Login:** Syifa (Marketing)
- **Menu:** Marketing → Lead / Opportunity → Add Lead
- **Langkah:**
  1. Pilih mode customer **baru**.
  2. Isi nama customer, perusahaan, telepon, email.
  3. Pilih PT (mis. NTI), segment, source.
  4. Isi kebutuhan dan nama project.
  5. Biarkan Assign To kosong.
  6. Simpan.
- **Hasil yang diharapkan:**
  - Lead tersimpan, muncul di daftar lead.
  - Status awal **New**.
  - Lead belum muncul di My Leads sales mana pun.
  - Muncul di Manage Sales dengan filter "NEW (Unassigned)".
- [x] Sesuai

#### T-02 — Marketing membuat lead untuk customer existing

- **Login:** Syifa
- **Menu:** Marketing → Lead / Opportunity → Add Lead
- **Langkah:**
  1. Pilih mode customer **existing**.
  2. Cari dan pilih customer.
  3. Isi kebutuhan, PT, project.
  4. Simpan.
- **Hasil yang diharapkan:**
  - Lead tertaut ke customer lama, bukan membuat customer duplikat.
  - Data PIC/alamat customer terisi otomatis.
- [x] Sesuai

#### T-03 — Management meng-assign lead ke Sales

- **Login:** Christina / Yanita (Management)
- **Menu:** Management → Manage Sales
- **Langkah:**
  1. Filter Assignment Status = **NEW (Unassigned)**.
  2. Cari lead dari T-01.
  3. Pilih Sales tujuan (mis. Irfan untuk NTI).
  4. Klik Assign.
- **Hasil yang diharapkan:**
  - Lead pindah ke daftar assigned.
  - Muncul toast sukses "Lead assigned to Irfan".
  - `assigned_at` terisi.
  - Sales tujuan menerima notifikasi.
- [x] Sesuai

#### T-04 — Assign ke user non-Sales (negatif)

- **Login:** Christina (Management)
- **Menu:** Management → Manage Sales
- **Langkah:**
  1. Coba assign lead ke user yang bukan role Sales (mis. Vanesha/Estimator
     atau Hanifah/Admin) lewat form edit.
  2. Simpan.
- **Hasil yang diharapkan:**
  - Ditolak dengan pesan "Assignment target must be a user with the Sales
    role" (atau pesan Indonesia yang setara).
  - Assignee tidak berubah.
- [x] Sesuai

#### T-05 — Reassign lead

- **Login:** Christina (Management)
- **Menu:** Management → Manage Sales
- **Langkah:**
  1. Pilih lead yang sudah di-assign ke Irfan.
  2. Ubah assignee ke Jeremy.
  3. Simpan.
- **Hasil yang diharapkan:**
  - Irfan menerima notifikasi lead dipindahkan.
  - Jeremy menerima notifikasi lead baru.
  - Lead hilang dari My Leads Irfan, muncul di My Leads Jeremy.
  - Activity log mencatat `reassigned`.
- [x] Sesuai

#### T-06 — Assign ulang lead closed (negatif)

- **Login:** Christina (Management)
- **Menu:** Management → Manage Sales
- **Langkah:**
  1. Cari lead berstatus **won** atau **lost**.
  2. Coba ubah assignee.
- **Hasil yang diharapkan:**
  - Ditolak, assignee tidak berubah.
  - Pesan jelas bahwa lead sudah ditutup.
- [x] Sesuai

#### T-07 — Management melihat lead lintas PT

- **Login:** Ayu (Management + Sales TPS)
- **Menu:** Management → Manage Sales
- **Langkah:**
  1. Buka daftar lead tanpa filter PT.
  2. Cari lead MGK dan NTI.
- **Hasil yang diharapkan:**
  - Management bisa melihat semua lead lintas PT.
  - Filter dan pencarian bekerja.
- [x] Sesuai

### 4.2 Sales — Menerima Lead, Follow Up, Meeting

#### T-08 — Sales menerima lead baru

- **Login:** Irfan (Sales NTI)
- **Menu:** Sales → My Leads
- **Langkah:**
  1. Buka My Leads.
  2. Cari lead yang baru di-assign di T-03.
- **Hasil yang diharapkan:**
  - Lead muncul di tabel.
  - Stage **New**.
  - Banner panduan 3 langkah muncul (bisa di-dismiss).
  - Lead milik sales lain **tidak** muncul.
- [x] Sesuai

#### T-09 — Sales mengakui lead lewat Pipeline Board

- **Login:** Irfan
- **Menu:** buka langsung `/leads/pipeline`
- **Langkah:**
  1. Lihat kolom **New**.
  2. Drag lead ke kolom **Warm**.
  3. Refresh halaman.
- **Hasil yang diharapkan:**
  - Lead pindah ke Warm.
  - Lead hilang dari kolom New.
  - Status di My Leads berubah menjadi Warm.
  - `acknowledged_at` terisi (overdue tidak lagi menghitung lead ini).
- [x] Sesuai

#### T-10 — Sales mengubah stage dari My Leads

- **Login:** Irfan
- **Menu:** Sales → My Leads
- **Langkah:**
  1. Pada baris lead, ubah dropdown Pipeline Stage dari New ke Hot.
  2. Tunggu respons.
- **Hasil yang diharapkan:**
  - Stage berubah tanpa reload penuh (atau reload cepat).
  - Nilai tetap Hot setelah refresh.
- [x] Sesuai

#### T-11 — Sales menandai lead Lost dengan alasan

- **Login:** Irfan
- **Menu:** Sales → My Leads → ubah stage ke Lost
- **Langkah:**
  1. Pilih Lost.
  2. Saat diminta, pilih alasan (mis. price).
  3. Isi catatan opsional.
  4. Simpan.
- **Hasil yang diharapkan:**
  - Lead menjadi Lost.
  - Alasan tersimpan dan tampil di Lead Detail.
  - Lead tidak bisa di-assign ulang.
- [x] Sesuai

#### T-12 — Sales menambah follow up pertama

- **Login:** Irfan
- **Menu:** Sales → My Leads → Add Follow Up
- **Langkah:**
  1. Pilih customer/lead.
  2. Pilih tipe follow up (WhatsApp / Call / Visit / dll).
  3. Isi catatan.
  4. Isi tanggal dan next follow up.
  5. Simpan.
- **Hasil yang diharapkan:**
  - Follow up tersimpan.
  - Muncul di tab All Open / Upcoming sesuai tanggal.
  - Next Follow Up tampil di My Leads.
  - Activity log lead mencatat follow up.
- [x] Sesuai

#### T-13 — Follow up dari Lead Detail (customer terkunci)

- **Login:** Irfan
- **Menu:** Sales → My Leads → buka lead → Add Follow Up
- **Langkah:**
  1. Buka lead detail.
  2. Klik Add Follow Up.
  3. Perhatikan field customer dan lead.
- **Hasil yang diharapkan:**
  - Customer dan lead terkunci (tidak bisa diganti).
  - Ada keterangan bahwa follow up tetap terikat ke lead tersebut.
- [x] Sesuai

#### T-14 — Menyelesaikan follow up

- **Login:** Irfan
- **Menu:** Sales → Follow Up
- **Langkah:**
  1. Buka follow up yang dibuat T-12.
  2. Klik complete / tandai selesai.
  3. Isi outcome dan next follow up bila diminta.
- **Hasil yang diharapkan:**
  - Follow up pindah ke tab Completed.
  - Outcome tampil.
  - Next follow up (bila diisi) muncul di My Leads.
- [x] Sesuai

#### T-15 — Complete follow up milik sales lain (negatif)

- **Login:** Jeremy (Sales NTI)
- **Menu:** Sales → Follow Up
- **Langkah:**
  1. Cari follow up milik Irfan.
  2. Coba complete lewat URL/aksi.
- **Hasil yang diharapkan:**
  - 403 atau tombol tidak tersedia.
  - Data tidak berubah.
- [x] Sesuai

#### T-16 — Sales membuat meeting dengan foto

- **Login:** Irfan
- **Menu:** Sales → Meeting → New Meeting
- **Langkah:**
  1. Pilih customer/lead.
  2. Pilih mode **Online** atau **Offline**.
  3. Isi tanggal, waktu, lokasi, deskripsi.
  4. Upload 1-3 foto.
  5. Simpan.
- **Hasil yang diharapkan:**
  - Meeting tersimpan.
  - Mode dan jumlah foto tampil di daftar meeting.
  - Meeting muncul di tab My Meetings.
- [x] Sesuai

#### T-17 — Meeting wajib laporan sebelum complete

- **Login:** Irfan
- **Menu:** Sales → Meeting
- **Langkah:**
  1. Buat meeting tanpa laporan.
  2. Coba tandai complete dari Follow Up / Meeting.
- **Hasil yang diharapkan:**
  - Sistem mengarahkan ke form laporan meeting.
  - Complete tidak berhasil sebelum laporan (foto + MOM) diisi.
- [ ] Sesuai

#### T-18 — Sales membuat jadwal untuk Sales/Inside Sales lain

- **Login:** Irfan
- **Menu:** Sales → Schedule → Add
- **Langkah:**
  1. Pilih lead.
  2. Pilih PIC (Irfan sendiri, Jeremy, atau Audi).
  3. Pilih tipe, tanggal, waktu, lokasi.
  4. Simpan.
- **Hasil yang diharapkan:**
  - Jadwal tampil di kalender.
  - Bila PIC orang lain, PIC menerima notifikasi.
  - PIC non-Sales/Inside Sales ditolak.
- [x] Sesuai

#### T-19 — Sales membatasi akses PT (negatif)

- **Login:** Adi (Sales MGK)
- **Menu:** Sales → Add Follow Up
- **Langkah:**
  1. Cari customer TPS (mis. milik Deby).
  2. Coba simpan follow up.
- **Hasil yang diharapkan:**
  - Customer TPS tidak muncul di pencarian, atau
  - Simpan ditolak 403.
  - Tidak ada data TPS yang bisa ditulis.
- [x] Sesuai

### 4.3 Sales — Quotation

#### T-20 — Buat quotation tanpa follow up (negatif)

- **Login:** Irfan
- **Menu:** Sales → Quotation → Add
- **Langkah:**
  1. Pilih lead yang **belum punya follow up**.
  2. Coba lanjut ke form quotation.
- **Hasil yang diharapkan:**
  - Ditolak dengan pesan "Record at least one follow-up update before
    creating a quotation".
  - Tidak ada quotation draft yang dibuat.
- [x] Sesuai

#### T-21 — Buat quotation dari lead yang sudah follow up

- **Login:** Irfan
- **Menu:** Sales → Quotation → Add
- **Langkah:**
  1. Pilih lead dari T-12.
  2. Isi tanggal, customer, alamat, kontak.
  3. Tambah minimal satu item (deskripsi, qty, harga).
  4. Simpan sebagai Draft.
- **Hasil yang diharapkan:**
  - Quotation tersimpan dengan nomor otomatis.
  - Status **Draft**.
  - Total dan item benar.
- [x] Sesuai

#### T-22 — Kirim quotation tanpa item (negatif)

- **Login:** Irfan
- **Menu:** Sales → Quotation → buka quotation kosong
- **Langkah:**
  1. Buat quotation tanpa item.
  2. Coba ubah status ke Ready atau Send.
- **Hasil yang diharapkan:**
  - Ditolak.
  - Pesan: quotation harus punya minimal satu item dan total > 0.
- [ ] Sesuai

#### T-23 — Alur Ready → Send → Viewed → Accepted

- **Login:** Irfan
- **Menu:** Sales → Quotation → detail
- **Langkah:**
  1. Ubah Draft → Ready.
  2. Klik Send (pilih kanal bila diminta).
  3. Tandai Viewed (bila ada aksi manual).
  4. Klik Accept.
  5. Konfirmasi.
- **Hasil yang diharapkan:**
  - Setiap status berpindah sesuai urutan.
  - Setelah Accept: status **Accepted**, lead otomatis **Won**.
  - Tombol upload PO muncul.
- [ ] Sesuai

#### T-24 — Tolak quotation

- **Login:** Irfan
- **Menu:** Sales → Quotation → detail
- **Langkah:**
  1. Buat quotation lain (atau gunakan yang masih sent).
  2. Klik Reject → konfirmasi.
- **Hasil yang diharapkan:**
  - Status **Rejected**.
  - Quotation terkunci, tidak bisa diubah menjadi accepted tanpa revisi.
- [ ] Sesuai

#### T-25 — Satu lead satu quotation aktif (negatif)

- **Login:** Irfan
- **Menu:** Sales → Quotation → Add
- **Langkah:**
  1. Pilih lead yang sudah punya quotation aktif.
  2. Coba buat quotation baru.
- **Hasil yang diharapkan:**
  - Ditolak.
  - Pesan mengarahkan untuk revisi quotation yang sudah ada.
- [ ] Sesuai

#### T-26 — Upload PO customer

- **Login:** Irfan
- **Menu:** Sales → Quotation → detail (status Accepted)
- **Langkah:**
  1. Upload file PO.
  2. Isi nomor PO.
  3. Pilih skema pembayaran.
  4. Simpan.
- **Hasil yang diharapkan:**
  - PO tersimpan.
  - Status verifikasi Admin = menunggu.
  - Tombol Convert to Project masih terkunci.
- [ ] Sesuai

#### T-27 — Convert project sebelum PO diverifikasi (negatif)

- **Login:** Irfan
- **Menu:** Sales → Quotation → detail
- **Langkah:**
  1. Setelah upload PO, langsung klik Convert to Project.
- **Hasil yang diharapkan:**
  - Ditolak.
  - Pesan: PO harus diverifikasi Admin dulu.
- [ ] Sesuai

#### T-28 — Convert project setelah PO diverifikasi

- **Login:** Hanifah (Admin) lalu Irfan (Sales)
- **Menu:** Admin → Purchasing / Handoff → verifikasi PO
- **Langkah:**
  1. Admin buka handoff quotation.
  2. Verifikasi PO.
  3. Login Irfan, buka quotation, klik Convert to Project.
- **Hasil yang diharapkan:**
  - Admin melihat data quotation + PO.
  - Setelah verifikasi, tombol Convert aktif.
  - Convert berhasil membuat project dan menautkannya ke quotation.
- [ ] Sesuai

### 4.4 Sales — Request Hub

#### T-29 — Sales membuat request Inside Sales

- **Login:** Irfan
- **Menu:** Sales → Request → tab **Inside Sales** → New Request
- **Langkah:**
  1. Pilih lead.
  2. Isi judul task dan deskripsi kebutuhan.
  3. Upload lampiran pendukung (opsional).
  4. Pilih Inside Sales (Audi/Dini).
  5. Isi due date dan prioritas.
  6. Kirim.
- **Hasil yang diharapkan:**
  - Task dibuat dan muncul di dashboard Inside Sales.
  - Sales melihat task di tab Inside Sales.
  - Assignee menerima notifikasi.
- [ ] Sesuai

#### T-30 — Sales membuat Price Request instalasi

- **Login:** Irfan
- **Menu:** Sales → Request → tab **Installation Price** → New Request
- **Langkah:**
  1. Pilih customer dan project (opsional).
  2. Isi catatan.
  3. Upload file quotation (wajib).
  4. Kirim.
- **Hasil yang diharapkan:**
  - Request masuk ke antrean Engineer/Pricing.
  - Status awal `waiting_lead` bila belum ada pricing engineer, atau
    `in_progress` bila sudah.
- [ ] Sesuai

#### T-31 — Sales membuat Technical Request

- **Login:** Irfan
- **Menu:** Sales → Request → tab **Engineer** → New Request
- **Langkah:**
  1. Pilih lead.
  2. Pilih tipe (survey / installation / troubleshooting / demo / konsultasi).
  3. Isi judul, tujuan, lokasi (wajib untuk survey/instalasi).
  4. Isi PIC kontak.
  5. Upload lampiran (opsional).
  6. Kirim.
- **Hasil yang diharapkan:**
  - Request tersimpan.
  - Untuk survey/instalasi tanpa lokasi: ditolak dengan pesan jelas.
  - Teknisi terkait menerima notifikasi.
- [ ] Sesuai

#### T-32 — Sales membuat Product Price Request ke Estimator

- **Login:** Irfan
- **Menu:** Sales → Request → tab **Product Price** → New Request
- **Langkah:**
  1. Pilih customer.
  2. Tambah item produk (produk, qty, catatan).
  3. Kirim.
- **Hasil yang diharapkan:**
  - Request masuk Queue Estimator.
  - Status **Waiting**.
  - Estimator menerima notifikasi.
- [ ] Sesuai

### 4.5 Inside Sales

#### T-33 — Inside Sales melihat antrean

- **Login:** Audi
- **Menu:** Sales → Inside Sales
- **Langkah:**
  1. Buka dashboard.
  2. Periksa KPI: New Request, In Progress, Quotation Created, Done.
  3. Buka Request Queue.
- **Hasil yang diharapkan:**
  - Task dari T-29 muncul.
  - KPI bertambah sesuai jumlah task.
  - Hanya task yang di-assign ke Audi (atau belum di-assign) yang tampil.
- [ ] Sesuai

#### T-34 — Inside Sales menambah item quotation

- **Login:** Audi
- **Menu:** Inside Sales → buka task T-29
- **Langkah:**
  1. Baca brief dan lampiran.
  2. Tambah minimal satu item: produk, deskripsi, qty, harga.
  3. Simpan item.
- **Hasil yang diharapkan:**
  - Item tersimpan dan tampil di task.
  - Progress stepper berubah: langkah "Add quotation items" selesai.
  - Tombol Create Quotation muncul.
- [ ] Sesuai

#### T-35 — Inside Sales membuat quotation dari item

- **Login:** Audi
- **Menu:** Task → Create Quotation
- **Langkah:**
  1. Klik Create Quotation.
  2. Periksa nomor quotation otomatis.
  3. Isi tanggal, data customer, kontak, syarat pembayaran.
  4. Pilih letterhead PT.
  5. Simpan.
- **Hasil yang diharapkan:**
  - Quotation draft dibuat dan tertaut ke task + lead.
  - Nomor quotation mengikuti PT dan inisial sales.
  - PDF bisa diunduh.
- [ ] Sesuai

#### T-36 — Pull Latest Price

- **Login:** Audi
- **Menu:** Create Quotation
- **Langkah:**
  1. Sebelum menyimpan, klik **Pull Latest Price**.
  2. Tunggu proses.
- **Hasil yang diharapkan:**
  - Harga item terbaru dari Pricelist terisi.
  - Tidak ada error bila produk belum punya harga baru (pakai harga lama).
- [ ] Sesuai

#### T-37 — Upload file quotation lalu accept

- **Login:** Audi (upload), Irfan (accept)
- **Menu:** Task → Upload quotation file
- **Langkah:**
  1. Audi upload file quotation (PDF).
  2. Pastikan item sudah ada.
  3. Login Irfan, buka task yang sama.
  4. Klik Accept pada file.
- **Hasil yang diharapkan:**
  - File tersimpan sebagai versi baru.
  - Setelah accept: quotation draft otomatis dibuat.
  - File tertaut ke quotation.
  - Tidak bisa accept dua kali.
- [ ] Sesuai

#### T-38 — Accept file tanpa item (negatif)

- **Login:** Irfan
- **Menu:** Task → Accept file
- **Langkah:**
  1. Buat task baru tanpa item.
  2. Upload file.
  3. Coba accept.
- **Hasil yang diharapkan:**
  - Ditolak: "Add at least one quotation item before accepting the file."
- [ ] Sesuai

#### T-39 — Sales lain accept file (negatif)

- **Login:** Jeremy (Sales NTI, bukan pemilik lead/pembuat task)
- **Menu:** Task → Accept file
- **Langkah:**
  1. Buka task milik Irfan.
  2. Coba accept file.
- **Hasil yang diharapkan:**
  - 403.
  - File tidak berubah status.
- [ ] Sesuai

#### T-40 — Diskusi task

- **Login:** Audi dan Irfan
- **Menu:** Task Detail → komentar
- **Langkah:**
  1. Audi kirim komentar.
  2. Irfan balas.
- **Hasil yang diharapkan:**
  - Komentar tampil berurutan.
  - Pihak terkait menerima notifikasi.
  - Enter mengirim, Shift+Enter membuat baris baru (bila berlaku).
- [ ] Sesuai

#### T-41 — Menyelesaikan task

- **Login:** Audi
- **Menu:** Task Detail
- **Langkah:**
  1. Setelah quotation dibuat, klik tombol selesai.
- **Hasil yang diharapkan:**
  - Status task menjadi **Done**.
  - Task pindah dari antrean aktif.
  - Sales melihat status selesai.
- [ ] Sesuai

### 4.6 Estimator

#### T-42 — Estimator melihat antrean

- **Login:** Vanesha
- **Menu:** Estimator → Queue
- **Langkah:**
  1. Buka Queue.
  2. Periksa KPI dan daftar request.
- **Hasil yang diharapkan:**
  - Request dari T-32 muncul.
  - Status **Waiting**.
  - Menu Sales **tidak terlihat** di sidebar.
- [ ] Sesuai

#### T-43 — Estimator mengisi harga

- **Login:** Vanesha
- **Menu:** Estimator → Queue → Process Request
- **Langkah:**
  1. Buka request.
  2. Untuk tiap item, pilih supplier.
  3. Isi harga modal, status stok, garansi, valid until, catatan.
  4. Klik Send prices to Sales → konfirmasi.
- **Hasil yang diharapkan:**
  - Status berubah menjadi **Answered / Price Received**.
  - Form harga tertutup.
  - Sales menerima notifikasi.
  - Harga tersimpan di histori produk.
- [ ] Sesuai

#### T-44 — Estimator mengedit harga setelah answered

- **Login:** Vanesha
- **Menu:** Request Detail
- **Langkah:**
  1. Buka request berstatus Answered.
  2. Klik Edit Prices.
  3. Ubah satu harga.
  4. Kirim ulang.
- **Hasil yang diharapkan:**
  - Perubahan tersimpan.
  - Sales menerima harga terbaru.
- [ ] Sesuai

#### T-45 — Sales menerima harga

- **Login:** Irfan
- **Menu:** Sales → Request → tab Product Price → buka request
- **Langkah:**
  1. Periksa harga dari Estimator.
  2. Klik Accept.
  3. Konfirmasi.
- **Hasil yang diharapkan:**
  - Status **Completed**.
  - Harga final terkunci.
  - Bisa dipakai sebagai referensi quotation.
- [ ] Sesuai

#### T-46 — Sales minta revisi harga

- **Login:** Irfan
- **Menu:** Request Detail
- **Langkah:**
  1. Klik Revision.
  2. Isi alasan revisi.
  3. Kirim.
- **Hasil yang diharapkan:**
  - Status **Revision**.
  - Estimator menerima notifikasi + alasan.
  - Estimator bisa membuka form harga lagi.
- [ ] Sesuai

#### T-47 — Sales membatalkan request

- **Login:** Irfan
- **Menu:** Request Detail
- **Langkah:**
  1. Klik Cancel.
  2. Konfirmasi.
- **Hasil yang diharapkan:**
  - Status **Cancelled**.
  - Request hilang dari antrean aktif Estimator.
- [ ] Sesuai

#### T-48 — Estimator membuka Pricelist & Price History

- **Login:** Vanesha
- **Menu:** Estimator → Pricelist / Price History
- **Langkah:**
  1. Buka Pricelist.
  2. Cari produk yang dipakai di T-43.
  3. Buka Price History.
- **Hasil yang diharapkan:**
  - Harga terbaru tampil.
  - Histori mencatat perubahan dari request.
  - Filter status harga (Active / Needs Recheck / Negative Margin /
    Expiring Soon) bekerja.
- [ ] Sesuai

#### T-49 — Estimator tidak bisa akses menu Sales

- **Login:** Vanesha
- **Menu:** coba buka `/sales/my-leads` langsung
- **Langkah:**
  1. Buka URL Sales secara langsung.
- **Hasil yang diharapkan:**
  - 403.
  - Sidebar tetap hanya menampilkan Estimator.
- [ ] Sesuai

### 4.7 Lintas Divisi & Keamanan

#### T-50 — Sales lain tidak bisa buka quotation orang lain

- **Login:** Jeremy
- **Langkah:**
  1. Buka URL quotation milik Irfan.
- **Hasil yang diharapkan:**
  - 403.
  - Tidak ada data harga/item yang bocor.
- [ ] Sesuai

#### T-51 — Admin read-only tidak bisa mengubah quotation

- **Login:** Hanifah
- **Langkah:**
  1. Buka quotation.
  2. Coba ubah status / item.
- **Hasil yang diharapkan:**
  - Hanya bisa melihat.
  - Aksi tulis tidak tersedia atau ditolak.
- [ ] Sesuai

#### T-52 — CEO read-only

- **Login:** Victor
- **Langkah:**
  1. Buka Sales, Quotation, Estimator.
  2. Coba semua aksi tulis.
- **Hasil yang diharapkan:**
  - Semua halaman bisa dibaca (sesuai izin).
  - Tidak ada aksi tulis yang berhasil.
  - Harga modal tetap terlihat sesuai kebijakan CEO read-only.
- [ ] Sesuai

#### T-53 — Notifikasi sampai ke penerima

- **Login:** beberapa role
- **Langkah:**
  1. Assign lead (Management).
  2. Buat request Inside Sales (Sales).
  3. Jawab harga (Estimator).
  4. Cek lonceng notifikasi tiap penerima.
- **Hasil yang diharapkan:**
  - Notifikasi muncul di penerima yang benar.
  - Klik notifikasi membuka halaman terkait.
  - Tidak ada notifikasi nyasar ke role lain.
- [ ] Sesuai

#### T-54 — Dark mode & mobile

- **Login:** semua role
- **Langkah:**
  1. Aktifkan dark mode.
  2. Buka tiap menu Sales/Inside Sales/Estimator.
  3. Perkecil layar ke ukuran HP.
- **Hasil yang diharapkan:**
  - Tidak ada teks hilang/nyaru.
  - Tombol tetap bisa diklik.
  - Tabel bisa di-scroll horizontal.
  - Dropdown tidak terpotong.
- [ ] Sesuai

---

## 5. Checklist Ringkas

### Management / Marketing
- [ ] T-01 Lead baru (customer baru)
- [ ] T-02 Lead baru (customer existing)
- [ ] T-03 Assign ke Sales
- [ ] T-04 Assign ke non-Sales ditolak
- [ ] T-05 Reassign
- [ ] T-06 Assign lead closed ditolak
- [ ] T-07 Lihat lintas PT

### Sales
- [ ] T-08 Lead muncul di My Leads
- [ ] T-09 Pipeline Board (drag New → Warm)
- [ ] T-10 Ubah stage dari tabel
- [ ] T-11 Lost + alasan
- [ ] T-12 Follow up pertama
- [ ] T-13 Follow up dari lead (terkunci)
- [ ] T-14 Complete follow up
- [ ] T-15 Complete follow up orang lain ditolak
- [ ] T-16 Meeting + foto
- [ ] T-17 Meeting wajib laporan
- [ ] T-18 Schedule + PIC lain
- [ ] T-19 Batas PT
- [ ] T-20 Quotation tanpa follow up ditolak
- [ ] T-21 Quotation dari lead
- [ ] T-22 Kirim tanpa item ditolak
- [ ] T-23 Ready → Send → Accept
- [ ] T-24 Reject
- [ ] T-25 Quotation kedua ditolak
- [ ] T-26 Upload PO
- [ ] T-27 Convert sebelum verifikasi ditolak
- [ ] T-28 Convert setelah verifikasi
- [ ] T-29 Request Inside Sales
- [ ] T-30 Price Request
- [ ] T-31 Technical Request
- [ ] T-32 Product Price Request

### Inside Sales
- [ ] T-33 Antrean & KPI
- [ ] T-34 Tambah item
- [ ] T-35 Buat quotation
- [ ] T-36 Pull Latest Price
- [ ] T-37 Upload + accept file
- [ ] T-38 Accept tanpa item ditolak
- [ ] T-39 Sales lain accept ditolak
- [ ] T-40 Diskusi task
- [ ] T-41 Selesaikan task

### Estimator
- [ ] T-42 Antrean
- [ ] T-43 Isi harga
- [ ] T-44 Edit harga
- [ ] T-45 Sales accept
- [ ] T-46 Sales minta revisi
- [ ] T-47 Sales cancel
- [ ] T-48 Pricelist & Price History
- [ ] T-49 Tidak bisa akses Sales

### Lintas Divisi
- [ ] T-50 Quotation orang lain 403
- [ ] T-51 Admin read-only
- [ ] T-52 CEO read-only
- [ ] T-53 Notifikasi
- [ ] T-54 Dark mode & mobile

---

## 6. Format Laporan Temuan

Setiap temuan tulis dengan format ini supaya mudah diperbaiki:

```text
ID Temuan : BUG-001
Skenario  : T-23 (Ready → Send → Accept)
Role      : Sales / Irfan
Menu      : Sales → Quotation → Detail
Langkah   : Klik Send, lalu Accept
Harapan   : Status berpindah ke Accepted dan lead menjadi Won
Kenyataan : Status tetap Sent, muncul error 500
Dampak    : Sales tidak bisa lanjut upload PO
Bukti     : screenshot / nama file
Prioritas : Tinggi / Sedang / Rendah
```

Klasifikasi prioritas:

| Prioritas | Kriteria |
|---|---|
| **Tinggi** | Alur utama berhenti, data hilang, akses bocor, error 500 |
| **Sedang** | Fitur bisa dipakai tapi hasil salah / tidak konsisten |
| **Rendah** | Tampilan, label, atau kenyamanan |

---

## 7. Catatan Awal dari Pembacaan Kode

Hal-hal yang **sudah terlihat** saat menyusun panduan ini. Perlu dicek di layar
sebelum dilaporkan sebagai bug:

1. **Pipeline Board tidak punya menu.** Halaman `/leads/pipeline` ada dan
   berfungsi, tetapi tidak ada tombol di sidebar, dashboard, atau My Leads.
   Sales harus mengetik URL. Ini kandidat temuan UX tinggi karena papan kanban
   adalah inti kerja Sales.
2. **DAR tidak punya menu.** Halaman `/sales/dar` juga hanya bisa dibuka lewat
   URL. Padahal halaman ini merangkum aktivitas harian.
3. **Inside Sales di seeder selalu memegang role Sales.** Audi dan Dini melihat
   menu Sales lengkap. Kalau tujuannya Inside Sales hanya mengerjakan task,
   role-nya perlu dipisah atau diuji dengan akun `inside-sales` murni.
4. **Label campur bahasa.** Beberapa tombol Inside Sales masih Indonesia
   ("Buka Task", "Buat Quotation", "Kerjakan Task") di halaman yang judulnya
   Inggris. Masuk temuan konsistensi bahasa.
5. **Tombol Convert di Lead Detail hanya untuk Admin/Teknisi.** Sales tidak
   melihat tombol itu, dan memang harus lewat Quotation. Pastikan tidak ada
   sales yang mengira fitur ini hilang.
6. **Quotation `revision`.** Pastikan alur dari `revision` kembali ke `ready`
   atau `sent` jelas di layar. Kalau tidak ada tombolnya, catat sebagai
   temuan alur.

---

## 8. Urutan Testing yang Disarankan

Supaya data saling nyambung, jalankan berurutan:

```text
Hari 1  : T-01 → T-07   (lead & assignment)
Hari 1  : T-08 → T-19   (sales follow up, meeting, schedule)
Hari 2  : T-20 → T-28   (quotation sampai convert)
Hari 2  : T-29 → T-32   (request hub)
Hari 3  : T-33 → T-41   (inside sales)
Hari 3  : T-42 → T-49   (estimator)
Hari 3  : T-50 → T-54   (keamanan, notifikasi, tampilan)
```

Satu lead bisa dipakai untuk seluruh rantai T-01 → T-28. Satu request bisa
dipakai untuk T-29 → T-41. Satu product price request untuk T-32 → T-49.

---

*Dokumen ini panduan uji manual. Bukan pengganti test otomatis. Setiap
perbaikan bug tetap wajib punya test otomatis sesuai aturan repo.*
