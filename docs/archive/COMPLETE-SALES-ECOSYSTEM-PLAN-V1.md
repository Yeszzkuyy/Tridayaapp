# COMPLETE PLAN: Penyempurnaan Ekosistem Menu Sales & Quotation Lintas Divisi (3DY App)

## 1. Konteks & Tujuan Utama
Di dalam **3DY App** (Grup Tridaya: **NTI, MGK, TPS, WANI**)[cite: 4], divisi **Sales** berada di tengah-tengah ekosistem operasional:
1. Menerima prospek (*Lead*) dari **Marketing** & **Management**[cite: 4].
2. Bekerja sama dengan **Inside Sales** untuk menyusun penawaran harga[cite: 1, 4].
3. Berkolaborasi dengan **Teknisi** untuk *Survey*, *Sizing*, dan *Request Harga*[cite: 4].
4. Menyerahkan pesanan yang sudah *Deal (Won)* ke **Admin Purchasing** (belanja barang ke supplier) dan **Admin Finance** (penagihan *Invoice* ke pelanggan)[cite: 1, 2, 4].
5. Memantau kembali (*feedback loop*) status pengiriman barang, BAST teknisi, dan pelunasan *Invoice* pelanggan tanpa membocorkan rahasia harga modal beli supplier[cite: 1, 4].

> **ATURAN TERMINOLOGI WAJIB ("QUOTATION"):**
> Secara tampilan antarmuka (UI, Sidebar, Judul Halaman, Breadcrumb, Tombol, dan Notifikasi), istilah **"Proposals"** kini resmi diganti menjadi **"Quotation"**. Namun, agar tidak merusak relasi database dan migrasi yang sudah berjalan di Laravel 13, nama tabel di database (`proposals` & `proposal_items`), nama Model (`App\Models\Proposal`), nama Controller (`ProposalController`), dan route URI internal (`sales/proposals/...`) **tetap dipertahankan di balik layar**[cite: 4].

---

## 2. Peta Masalah Saat Ini yang Harus Ditutup (12 Celah Koneksi)

Berdasarkan audit kode (`PROJECT_SPEC_AUDIT.md`)[cite: 4] dan wawancara operasional Admin[cite: 1]:
1. **Marketing -> Sales:** Belum ada tombol 1-klik *"Terima / Acknowledge Lead"* di halaman Detail Lead/`My Leads` (selama ini `acknowledged_at` hanya terisi kalau digeser di Kanban Pipeline), dan dokumen awal dari Marketing (`lead_documents`) tidak otomatis terbawa saat Sales membuat `Technical Request` ke Teknisi[cite: 4].
2. **Sales <-> Inside Sales:** Role `inside-sales` terkena *403 Forbidden* saat mengakses katalog `/products` karena middleware route `/products` hanya mengizinkan `view-sales|manage-sales|view-admin|manage-admin` (belum menyertakan `manage-inside-sales`)[cite: 4]. Selain itu, perlu pembedaan tegas antara **Pembuat Quotation (`created_by` = Inside Sales)** dan **Pemilik Klien/Komisi (`sales_owner_id` = Sales Lapangan)**[cite: 1, 4].
3. **Sales <-> Teknisi (Sizing & Request Harga):** Route baca untuk `sizing-projects` dan `request-hargas` saat ini hanya mengizinkan `view-technician|manage-technician`[cite: 4]. Akibatnya, Sales tidak bisa melihat hasil *Sizing* teknisi dan belum ada tombol untuk menarik barang hasil *Sizing/Request Harga* langsung ke baris item Quotation[cite: 4].
4. **Sales <-> Admin Purchasing (Pre-Sales Costing / Perhitungan Proyek):** Untuk penjualan barang murni/lisensi (tanpa survei teknisi), Sales/Inside Sales butuh lembar **Perhitungan Proyek (Harga Modal Beli vs Harga Jual)** di dalam Quotation yang bisa diisi oleh Admin Purchasing, namun harga modal tersebut disembunyikan dari cetakan PDF pelanggan dan dari akun Sales biasa[cite: 1].
5. **Quotation -> Admin Finance (Syarat Pembayaran / TOP):** Tabel `proposals` belum memiliki kolom cara bayar (`payment_terms`, misal: *DP 30% - Pelunasan 70%*, *CBD*, *Net 30*), sehingga Admin Finance tidak tahu kapan dan berapa persen harus menagih *Invoice*[cite: 2, 4].
6. **Upload PO Pelanggan / SPK Masih "Buta":** Saat Quotation *Deal (`accepted`)*, fungsi `ProposalController@uploadPo` hanya mengunggah file PDF (`po_document_path`)[cite: 4]. Belum ada kolom input **Nomor PO Klien (`client_po_number`)**, **Tanggal PO Klien (`client_po_date`)**, dan **Batas Waktu Pengiriman (`delivery_deadline_date`)** untuk mencegah denda keterlambatan SPK[cite: 1].
7. **Data Pajak & Penagihan di `customers` Kurang Lengkap:** Tabel `customers` baru memiliki `npwp` dan `npwp_document_path`, namun belum memiliki **Alamat Penagihan (`billing_address`)**, **Alamat Pengiriman (`shipping_address`)**, serta **Nama & Kontak PIC Finance Klien**[cite: 4].
8. **Dua Pintu "Convert to Project" yang Berbahaya:** Saat ini proyek bisa dibuat dari `PATCH /leads/{lead}/convert` ATAU `POST /sales/proposals/{proposal}/convert`[cite: 4]. Jika Sales melakukan *convert* dari halaman `Lead` langsung, maka `Project` terbentuk tanpa `proposal_id`, tanpa nilai rupiah, dan tanpa daftar barang[cite: 4].
9. **Keamanan Harga Modal Admin:** Role `sales` di `RoleAndPermissionSeeder.php` masih memegang permission `view-admin`, sehingga Sales bisa mengintip menu Admin yang berisi harga beli dari supplier[cite: 1, 4].
10. **Race Condition Nomor Quotation & Tercampurnya Produk AI:** `ProposalController@generateNumber()` belum memakai `DB::transaction` + `lockForUpdate()`, dan picker produk BOQ belum memfilter ketat produk hasil *crawl* AI (`status = approved` & `is_active = true`)[cite: 4].
11. **Sales Kehilangan Jejak Proyek Setelah Deal (Feedback Loop):** Setelah Quotation di-*convert* jadi `Project`, Sales tidak punya panel pantauan di halaman Lead/Quotation untuk melihat apakah barang sudah dipesan/dikirim oleh Admin Purchasing, apakah BAST Teknisi sudah selesai, dan apakah Invoice Klien sudah lunas (*Closed*)[cite: 1, 4].
12. **Produktivitas Harian Sales:** Duplikasi kode *dropdown* Lead/Customer di 3 controller (`FollowUpController`, `PocController`, `SalesScheduleController`)[cite: 4] dan belum adanya tombol catat cepat (*Quick Follow-Up*) langsung dari halaman Detail Lead.

---

## 3. Rincian Perubahan Database (Migrasi Aditif & Non-Destruktif)

Buat satu migrasi aditif baru (tanpa menghapus kolom lama agar *test* yang sudah ada tetap aman):

### A. Penambahan Kolom pada Tabel `proposals` (Quotation)
- `sales_owner_id` (`foreignId` nullable -> `users.id`, nullOnDelete): Menyimpan pemilik relasi/komisi Sales Lapangan (otomatis terisi dari `leads.assigned_to`), terpisah dari `created_by` (yang bisa berupa Inside Sales)[cite: 1, 4].
- `revision_number` (`unsignedInteger`, default `0`): Penanda revisi ke-berapa (`Rev.0`, `Rev.1`, dst.).
- `payment_terms` (`string` nullable): Ketentuan pembayaran (contoh: `Full Payment 100%`, `DP 30% - Pelunasan 70%`, `CBD`, `Net 14`, `Net 30`).
- `delivery_terms` (`string` nullable): Estimasi waktu pengiriman/inden barang (contoh: `Ready Stock 3-5 Hari`, `Inden 4-6 Minggu`).
- `client_po_number` (`string` nullable): Nomor surat PO / SPK resmi dari pelanggan saat *Deal*[cite: 1].
- `client_po_date` (`date` nullable): Tanggal surat PO dari pelanggan[cite: 1].
- `delivery_deadline_date` (`date` nullable): Batas waktu maksimal pengiriman/instalasi sesuai SPK pelanggan (alarm anti-denda)[cite: 1].

### B. Penambahan Kolom pada Tabel `proposal_items` (Rincian Item Quotation)
- `cost_price` (`decimal(15,2)` nullable, default `0`): Acuan harga modal beli satuan dari supplier (hanya boleh dilihat/diisi oleh `manage-admin` / `manage-inside-sales`, tersembunyi dari PDF klien)[cite: 1].
- `partner_id` (`foreignId` nullable -> `partners.id`, nullOnDelete): Referensi rencana pembelian ke Supplier/Distributor mana[cite: 1, 4].
- `item_notes` (`string` nullable): Catatan internal spesifikasi/garansi/lisensi tambahan[cite: 1].

### C. Penambahan Kolom pada Tabel `customers` (Kelengkapan Finance & Pengiriman)
- `billing_address` (`text` nullable): Alamat penagihan Invoice & Faktur Pajak.
- `shipping_address` (`text` nullable): Alamat pengiriman barang lokasi proyek.
- `finance_pic_name` (`string` nullable): Nama PIC bagian Keuangan/Finance pelanggan.
- `finance_pic_phone` (`string` nullable): Nomor WA/Telepon PIC Finance pelanggan.
- `finance_pic_email` (`string` nullable): Email pengiriman Invoice tagihan.

---

## 4. Rencana Eksekusi Bertahap (6 Batch Terukur untuk AI Agent)

### BATCH 1: Keamanan Role, Akses Inside Sales, & Jembatan Marketing -> Sales
**File yang disentuh:**
- `database/seeders/RoleAndPermissionSeeder.php`[cite: 4]
- `routes/web.php`[cite: 4]
- `app/Http/Controllers/LeadController.php`[cite: 4]
- `app/Http/Controllers/TechnicalRequestController.php`[cite: 4]

**Detail Tugas:**
1. **Cabut `view-admin` dari Role `sales`:** Di `RoleAndPermissionSeeder.php`, hapus `'view-admin'` dari array permission milik role `'sales'` agar Sales tidak bisa membuka halaman Admin yang memuat harga beli supplier[cite: 1, 4].
2. **Buka Akses Katalog Produk & Sizing untuk Inside Sales & Sales:**
   - Di `routes/web.php`, tambahkan `manage-inside-sales` pada middleware grup baca `/products` (`permission:view-sales|manage-sales|manage-inside-sales|view-admin|manage-admin`) agar Inside Sales tidak *403 Forbidden* saat memilih barang untuk Quotation[cite: 4].
   - Di `routes/web.php`, tambahkan `view-sales|manage-sales|manage-inside-sales` pada route baca (GET index & show) untuk `sizing-projects` dan `request-hargas` agar Sales & Inside Sales bisa membaca hasil hitungan teknisi[cite: 4].
3. **Tombol 1-Klik "Acknowledge / Terima Lead":** Tambahkan endpoint/aksi cepat bagi Sales untuk menandai `acknowledged_at = now()` langsung dari halaman Detail Lead (`leads.show`) dan tabel `My Leads` tanpa harus menggeser kartu di Kanban Pipeline[cite: 4].
4. **Warisan Lampiran Awal (`lead_documents`) ke Teknisi:** Saat Sales membuat `Technical Request` dari sebuah Lead, tampilkan daftar `lead_documents` milik Lead tersebut secara otomatis di halaman detail `Technical Request` agar Teknisi bisa langsung mengunduh BOQ/spesifikasi awal dari Marketing tanpa Sales harus upload ulang[cite: 4].

---

### BATCH 2: Penamaan UI "Quotation", Lock Penomoran, & Kolaborasi Inside Sales <-> Teknisi
**File yang disentuh:**
- `app/Models/Proposal.php` & `app/Models/ProposalItem.php`[cite: 4]
- `app/Http/Controllers/ProposalController.php`[cite: 4]
- `resources/views/sales/proposals/*.blade.php` & `resources/views/layouts/navigation.blade.php`[cite: 4]

**Detail Tugas:**
1. **Rebranding UI ke "Quotation":** Ubah seluruh label menu sidebar, judul halaman, tombol, dan pesan flash dari *"Proposal"* menjadi *"Quotation"*.
2. **Lock Nomor Quotation & Kalkulasi (Fix Audit P1):** Bungkus `ProposalController@generateNumber()` dan `Proposal::recalculate()` di dalam `DB::transaction()` menggunakan `lockForUpdate()` agar tidak terjadi nomor Quotation ganda saat dua Sales/Inside Sales menyimpan bersamaan[cite: 4].
3. **Pemisahan Pembuat vs Pemilik Komisi:** Saat Quotation disimpan (`store`), isi `created_by = auth()->id()` (bisa Inside Sales) dan otomatis isi `sales_owner_id = $lead->assigned_to` (Sales pemilik klien)[cite: 1, 4].
4. **Filter Ketat Katalog Produk BOQ (Fix Audit P2):** Pastikan query dropdown/picker produk di Quotation hanya mengambil `Product::where('is_active', true)->whereIn('status', ['approved', 'published'])` agar hasil *scraping* mentah AI tidak bocor ke daftar harga Sales[cite: 4].
5. **Tombol "Tarik Item dari Sizing / Request Harga Teknisi":** Di halaman buat/edit Quotation (`sales/proposals/create` & `edit`), sediakan tombol untuk mengimpor daftar barang dari `sizing_projects` atau `request_hargas` milik proyek/lead terkait langsung menjadi baris `proposal_items`[cite: 4].

---

### BATCH 3: Perhitungan Proyek (Costing), Syarat Bayar (TOP), & Cetak PDF Quotation 4 PT
**File yang disentuh:**
- `app/Http/Controllers/ProposalController.php`[cite: 4]
- `resources/views/sales/proposals/{create,edit,show,preview}.blade.php`[cite: 4]

**Detail Tugas:**
1. **Input `payment_terms` & `delivery_terms`:** Tambahkan pilihan syarat pembayaran (*Full Payment*, *DP 30% - Pelunasan 70%*, *CBD*, *Net 14*, *Net 30*, *Termin*) dan estimasi pengiriman pada form Quotation.
2. **Fitur Internal "Perhitungan Proyek / Costing":**
   - Pada tabel item Quotation, tambahkan kolom `cost_price` (Harga Modal) dan `partner_id` (Supplier) yang **hanya tampil dan bisa diedit** oleh user berizin `manage-admin` atau `manage-inside-sales`[cite: 1].
   - Tampilkan ringkasan *Total Harga Jual vs Estimasi Margin (%)* khusus untuk Admin/Inside Sales/Management[cite: 1].
3. **Halaman Preview & Cetak PDF Quotation (`sales/proposals/{p}/preview`):**
   - Sesuaikan kop surat, logo, dan informasi perusahaan otomatis berdasarkan `lead->pt_group` (**NTI, MGK, TPS, WANI**)[cite: 2, 4].
   - Tampilkan Nomor Quotation, Revisi, Daftar Barang/Jasa, Subtotal, Diskon, PPN, Grand Total, `payment_terms`, `delivery_terms`, dan `valid_until`[cite: 1, 4].
   - **WAJIB:** Sembunyikan `cost_price`, `partner_id`, dan hitungan margin dari halaman `preview` cetak pelanggan[cite: 1]!

---

### BATCH 4: Penguncian Handoff Deal (Upload PO Klien, Deadline SPK, & 1 Pintu Convert Project)
**File yang disentuh:**
- `app/Http/Controllers/ProposalController.php`[cite: 4]
- `app/Http/Controllers/LeadController.php`[cite: 4]
- `app/Http/Controllers/CustomerController.php`[cite: 4]
- `resources/views/sales/proposals/show.blade.php` & `resources/views/customers/*.blade.php`[cite: 4]

**Detail Tugas:**
1. **Lengkapi Form Upload PO Pelanggan (`ProposalController@uploadPo`):**
   Saat mengunggah file PO/SPK pelanggan (`po_document_path`), wajibkan Sales mengisi:
   - `client_po_number` (Nomor PO/SPK dari Pelanggan)[cite: 1]
   - `client_po_date` (Tanggal PO Pelanggan)[cite: 1]
   - `delivery_deadline_date` (Batas Waktu Pengiriman/SPK untuk alarm anti-denda di Admin & Teknisi)[cite: 1]
2. **Lengkapi Data Penagihan di `CustomerController`:** Tambahkan field `billing_address`, `shipping_address`, `finance_pic_name`, `finance_pic_phone`, dan `finance_pic_email` di form Customer. Berikan indikator peringatan di halaman Quotation jika pelanggan yang sudah `accepted` belum memiliki `npwp` atau `npwp_document_path`[cite: 2, 4].
3. **Satukan Pintu "Convert to Project" (`ProposalController@convertToProject`):**
   - Wajibkan konversi `Project` berasal dari **Quotation yang berstatus `accepted` dan sudah memiliki `po_document_path` + `client_po_number`**[cite: 1, 4].
   - Saat `convertToProject` dijalankan, otomatis salin `proposal_id`, `quotation_number = proposal_number`, `sales_user_id = sales_owner_id`, dan `end_date = delivery_deadline_date` ke tabel `projects`[cite: 4].
   - Pada `LeadController@convert` (`PATCH /leads/{lead}/convert`), tambahkan penjaga (*guard*): jika Lead memiliki Quotation `accepted`, arahkan otomatis menggunakan data Quotation tersebut; jika belum ada Quotation `accepted`, tolak dengan pesan jelas: *"Silakan buat dan setujui Quotation serta unggah PO Pelanggan terlebih dahulu sebelum mengonversi menjadi Proyek."*

---

### BATCH 5: Feedback Loop Pasca-Deal (Panel Pantauan Proyek & Tagihan di Layar Sales)
**File yang disentuh:**
- `resources/views/leads/show.blade.php` & `resources/views/sales/proposals/show.blade.php`[cite: 4]

**Detail Tugas:**
1. **Panel "Status Eksekusi Proyek & Penagihan (Post-Deal Tracker)" untuk Sales:**
   Tampilkan kartu ringkasan *Read-Only* di halaman Detail Quotation (`accepted`) dan Detail Lead (`won`) yang berisi 4 indikator progres dari divisi Admin & Teknisi[cite: 1, 4]:
   - **Status Pengadaan & Pengiriman Barang (dari Admin Purchasing):** *Menunggu PO Supplier* / *Barang Diterima Sebagian* / *Barang Terkirim ke Klien*[cite: 1].
   - **Batas Waktu SPK (`delivery_deadline_date`):** Hitung mundur sisa hari menuju batas pengiriman[cite: 1].
   - **Status Instalasi & BAST (dari Teknisi):** Status proyek berjalan & apakah dokumen BAST sudah diunggah di `project_documents`[cite: 1, 4].
   - **Status Tagihan Invoice Klien (dari Admin Finance):** Status Invoice (`unpaid` / `partial` / `paid`) dan indikator apakah proyek sudah resmi **100% CLOSED** (Barang Terkirim + BAST Selesai + Invoice Lunas)[cite: 1, 2, 4].
   - **Aturan Keamanan Ketat:** Panel ini dilarang keras menampilkan nominal harga beli PO Supplier (`purchase_orders.amount` / `cost_price`) kepada role `sales`[cite: 1]!

---

### BATCH 6: Produktivitas Harian Sales (Service Opsi Terpadu & Quick Follow-Up)
**File yang disentuh:**
- `app/Services/SalesService.php`[cite: 4]
- `app/Http/Controllers/{FollowUpController,PocController,SalesScheduleController}.php`[cite: 4]
- `resources/views/leads/show.blade.php`[cite: 4]

**Detail Tugas:**
1. **Hapus Duplikasi Dropdown Lead/Customer (Fix Audit P1):** Satukan method `leadOptions()`, `customerOptions()`, dan `scopeToOwnLeads()` yang terduplikasi di `FollowUpController`, `PocController`, dan `SalesScheduleController` ke dalam `SalesService`[cite: 4].
2. **Quick Follow-Up Modal di Detail Lead:** Tambahkan form ringkas (1-klik catat Call/WA + `next_follow_up_date`) langsung di halaman `leads/show.blade.php` agar Sales tidak perlu bolak-balik pindah menu setiap selesai menelepon klien[cite: 3, 4].

---

## 5. Kriteria Selesai (Definition of Done) & Aturan Wajib Agent
1. **Patuhi Konvensi UI (`AGENTS.md` & `DESIGN.md`):**
   - Dilarang menampilkan karakter `-` mentah untuk data kosong/`null`[cite: 5]. Gunakan *fallback* deskriptif bergaya `text-slate-400 italic` (contoh: *"Belum ada Nomor PO Klien"*, *"Syarat bayar belum diatur"*, *"Belum ada jadwal batas kirim"*).
   - Tombol aksi utama (*Primary CTA*) menggunakan warna solid yang kontras[cite: 5].
2. **Keamanan & Performa Query:**
   - Gunakan *eager loading* (`with(...)`) untuk mencegah N+1 query di SQLite[cite: 4].
   - Pastikan rute hanya diedit di `routes/web.php`[cite: 2, 4].
3. **Testing Wajib Hijau:**
   - Buat/perbarui *Feature Test* (`tests/Feature/QuotationConnectivityTest.php`) yang menguji:
     1. Role `sales` tidak bisa lagi mengakses `/admin/pos` atau `/manage/admin` (*403 Forbidden*)[cite: 1, 4].
     2. Role `inside-sales` bisa mengakses `/products` dan membuat Quotation dengan `sales_owner_id` tetap mengarah ke Sales pemilik Lead[cite: 1, 4].
     3. Upload PO Klien menyimpan `client_po_number`, `client_po_date`, dan `delivery_deadline_date`[cite: 1].
     4. Konversi Quotation ke Project mewariskan `proposal_id`, `quotation_number`, dan `delivery_deadline_date` dengan benar[cite: 4].
   - Jalankan `php artisan test` hingga seluruh test berstatus **PASS (Hijau)**[cite: 2, 4].