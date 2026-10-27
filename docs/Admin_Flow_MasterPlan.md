# MASTER PLAN: Integrasi End-to-End Sales, Purchasing, Logistik & Finance (3DY App)

Dokumen ini adalah penyempurnaan dari **Rencana Arsitektur 6 Tahap (Raafi)** yang telah dipetakan secara presisi ke skema database, model, controller, dan rute aktual di **3DY App** (`PROJECT_SPEC_AUDIT.md`) serta kebutuhan nyata dari **Hasil Wawancara Admin & Sales**.

> **INSTRUKSI WAJIB UNTUK AI AGENT (OPENCODE / CLAUDE CODE):**
> 1. **DILARANG** membuat modul dari nol jika tabel/model sudah ada di codebase (`proposals`, `proposal_items`, `products`, `partners`, `request_hargas`, `projects`, `purchase_orders`, `invoices`, `payments`). Gunakan migrasi aditif (`ALTER TABLE` / tabel detail pendamping).
> 2. **SATU-SATUNYA FILE ROUTE AKTIF** adalah `routes/web.php`. Jangan pernah mengedit `routes/admin.php` atau `routes/teknisi.php` (file duplikat legacy yang tidak di-load oleh `bootstrap/app.php`).
> 3. **PRINSIP NON-DESTRUKTIF:** Seluruh fitur existing dan test suite (`php artisan test`) wajib tetap lulus (PASS) di setiap akhir tahap.

---

## TAHAP 0 — Audit Read-Only, Fondasi Keamanan RBAC & Penomoran Anti-Ganda

Sebelum membangun fitur baru, kita wajib menutup celah keamanan yang dikeluhkan Admin di wawancara (*"Sales tidak boleh melihat menu Admin / harga modal supplier"*) dan masalah nomor dokumen kembar.

### 0.1 Target Perubahan Presisi di Codebase
1. **Isolasi Menu Admin dari Role Sales (`database/seeders/RoleAndPermissionSeeder.php`):**
   * **Masalah Aktual:** Saat ini role `sales` dan `sales-{nti,mgk,tps,wani}` masih diberi permission `view-admin`.
   * **Tindakan:** Hapus `'view-admin'` dari array permission milik role `sales` (dan varian PT-nya). Pastikan di sidebar (`resources/views/layouts/`), menu `/manage/admin` dan `/admin/*` hanya tampil untuk `@canany(['view-admin', 'manage-admin'])`.
2. **Kunci Transaksi & Anti-Nomor Ganda (`app/Http/Controllers/ProposalController.php` & `app/Services/AdminService.php`):**
   * **Masalah Aktual:** Admin mengeluhkan nomor *Quotation/Proposal* dari Sales sering kembar/dobel, dan di kode saat ini `ProposalController@generateNumber` belum memakai *database locking*.
   * **Tindakan:**
     * Bungkus `ProposalController@generateNumber()` dan `Proposal::recalculate()` di dalam `DB::transaction()` dengan `lockForUpdate()`.
     * Tambahkan validasi `Rule::unique('proposals', 'proposal_number')->ignore($proposal?->id)` pada `ProposalController@store/update`, serta validasi `unique` ketat pada `po_number` (`purchase_orders`) dan `invoice_number` (`invoices`) agar sistem otomatis **decline (menolak)** jika diinput nomor dokumen yang sama.

### 0.2 Kriteria Selesai (DoD Tahap 0)
- [ ] User dengan role `sales` mendapat `403 Forbidden` saat mengakses `/manage/admin`, `/admin/pos`, `/admin/invoices`, dan `/admin/payments`.
- [ ] Input nomor Proposal, PO, atau Invoice yang sudah terpakai langsung ditolak oleh validasi form dengan pesan error bahasa Indonesia yang jelas.

---

## TAHAP 1 — Rapikan Proses Sales, Quotation (`Proposals`), Perhitungan Proyek & Handoff ke Admin

Fondasi pertama adalah memastikan data dari Sales dan *Inside Sales* rapi sejak dari hulu agar Admin tidak perlu mengetik ulang ke spreadsheet ketika proyek *Deal (Won)*.

### 1.1 Pemetaan Entitas di Codebase (`proposals` = Quotation)
Di 3DY App, dokumen **Quotation** sudah direpresentasikan oleh tabel `proposals` dan `proposal_items`. Jangan buat tabel `quotations` baru; sempurnakan tabel `proposals` yang sudah ada.

### 1.2 Target Perubahan Presisi di Codebase
1. **Pemisahan Pembuat Quotation (`Inside Sales`) vs Pemilik Relasi Pelanggan (`Sales`):**
   * **Kondisi Aktual:** Tabel `proposals` memiliki `lead_id` dan `created_by`. Pemilik pelanggan adalah `leads.assigned_to` (Sales), sedangkan pembuat dokumen adalah `proposals.created_by` (bisa *Inside Sales* atau *Sales*).
   * **Tindakan di `ProposalController.php` & `LeadPolicy.php`:**
     * Pastikan `inside-sales` (yang punya permission `manage-inside-sales`) dapat menyusun/mengedit draf `Proposal` untuk Lead yang ditugaskan kepadanya **tanpa** mengubah kolom `leads.assigned_to` (pemilik komisi/relasi pelanggan tetap Sales utama).
     * Tampilkan kedua informasi secara eksplisit di halaman detail Proposal & Admin: **"Sales Owner (PIC Klien): [Nama Sales]"** dan **"Disusun Oleh (Quotation Maker): [Nama Inside Sales/Sales]"**.
2. **Integrasi "Perhitungan Proyek" (Kalkulasi HPP / Modal vs Harga Jual) ke Quotation:**
   * **Kebutuhan Sales & Admin:** Sebelum Quotation dikirim ke pelanggan, ada lembar *Perhitungan Proyek* (menghitung harga beli dari Supplier A/B/C vs harga jual & margin, serta apakah ada biaya instalasi).
   * **Tindakan Database (Migrasi Aditif pada `proposal_items` & `proposals`):**
     * Buat migrasi `add_project_calculation_fields_to_proposals_and_items_table`:
       * Di `proposal_items`, tambahkan:
         * `cost_price` (`decimal(15,2) default 0`) — Harga modal/beli satuan hasil survei/request harga supplier.
         * `partner_id` (`foreignId nullable constrained('partners')->nullOnDelete()`) — Referensi supplier acuan.
         * `item_type` (`string default 'barang'`) — Nilai: `barang`, `jasa`, `instalasi`, `lisensi`.
         * `notes` (`text nullable`) — Catatan spesifikasi/part number.
       * Di `proposals`, tambahkan:
         * `total_cost` (`decimal(15,2) default 0`) — Total modal keseluruhan (`sum(cost_price * quantity)`).
         * `gross_margin` (`decimal(15,2) default 0`) — Estimasi margin (`subtotal - discount - total_cost`).
         * `customer_po_number` (`string nullable`) — Nomor PO Pelanggan / SPK saat Deal.
         * `customer_po_date` (`date nullable`) — Tanggal PO Pelanggan masuk.
         * `delivery_deadline` (`date nullable`) — Tenggat waktu wajib kirim barang (agar Admin ada pengingat anti-denda sesuai wawancara menit 06:05).
   * **Aturan Visibilitas Harga Modal:** Kolom `cost_price`, `total_cost`, dan `gross_margin` hanya boleh dilihat oleh role yang berwenang (`admin`, `management`, `ceo`, `super-admin`, atau saat mode kalkulasi internal). Cetakan/Preview PDF untuk pelanggan (`sales.proposals.preview`) **dilarang keras** menampilkan `cost_price` dan `gross_margin`.
3. **Handoff Otomatis Sales $\rightarrow$ Admin (Saat Quotation `accepted` + Upload PO Pelanggan):**
   * Saat Sales menekan `uploadPo` dan status `Proposal` menjadi `accepted` (serta `convertToProject`), data proyek + rincian item dari `proposal_items` otomatis masuk ke antrean **"PO Masuk (Deal Baru) — Siap Diproses Purchasing"** di `/manage/admin` tanpa Admin perlu input ulang ke spreadsheet.

### 1.3 Kriteria Selesai (DoD Tahap 1)
- [ ] *Inside Sales* dapat membuat Quotation untuk Lead milik Sales tanpa mengambil alih `leads.assigned_to`.
- [ ] Quotation memiliki fitur *Perhitungan Proyek* (Harga Beli/Modal vs Harga Jual + Margin + penanda item Instalasi) dan terhubung ke `request_hargas`.
- [ ] Saat Proposal *Accepted* + PO Klien diunggah (+ input `customer_po_number` & `delivery_deadline`), datanya langsung muncul di antrean kerja Admin.

---

## TAHAP 2 — Master Data Barang (`Products`) dan Purchase Order Supplier (`PurchaseOrders`)

Setelah handoff dari Sales beres, kita membangun fondasi **Purchasing** agar Admin bisa menerbitkan **PO Supplier** dengan menarik data dari Quotation dan **Master Data Barang**, serta mendukung pemecahan komponen (kasus produk **Cisco** yang 1 item di Quotation pecah menjadi beberapa komponen + lisensi saat PO ke Supplier).

### 2.1 Target Perubahan Presisi di Codebase

#### A. Penyempurnaan Master Data Barang (`products` & `ProductController.php`)
* **Kondisi Aktual:** Tabel `products` saat ini dipakai ganda untuk AI Knowledge Base dan katalog BOQ (`brand`, `name`, `model`, `sku`, `category`, `unit`, `default_price`, `is_active`).
* **Tindakan Database (Migrasi Aditif `add_purchasing_fields_to_products_table`):**
  * Tambahkan kolom:
    * `internal_code` (`string nullable unique`) — Kode barang internal 3DY (bisa disinergikan dengan `sku`).
    * `part_number` (`string nullable index`) — **PN Number** resmi dari supplier/prinsipal (wajib ada sesuai wawancara Admin menit 02:35).
    * `partner_id` (`foreignId nullable constrained('partners')->nullOnDelete()`) — Supplier utama barang tersebut.
    * `buy_price` (`decimal(15,2) default 0`) — Acuan harga beli dari supplier (`default_price` tetap sebagai acuan harga jual).
    * `has_serial_number` (`boolean default false`) — Penanda apakah barang ini wajib dicatat *Serial Number*-nya saat diterima.
    * `is_catalog_item` (`boolean default true`) — Pemisah tegas agar produk hasil *web crawl* AI Knowledge Base tidak mengotori pilihan Master Barang Purchasing & BOQ Sales sebelum diverifikasi.
* **Menu di Sidebar Admin:** Tambahkan akses cepat **"Data Barang (`/products`)"** di bawah seksi Purchasing agar Admin tidak mengetik manual di dalam form PO.

#### B. Merombak `purchase_orders` & Membuat Tabel Rincian `purchase_order_items`
* **Kondisi Aktual:** Tabel `purchase_orders` saat ini hanya punya `customer_id`, `project_id`, dan `items` berupa `text` bebas, serta belum terhubung ke `partners` (Supplier) maupun `proposals` (Quotation).
* **Tindakan Database:**
  1. **Migrasi Aditif pada `purchase_orders`:**
     * Tambahkan `partner_id` (`foreignId nullable constrained('partners')->nullOnDelete()`) — **Supplier/Vendor tujuan PO**.
     * Tambahkan `proposal_id` (`foreignId nullable constrained('proposals')->nullOnDelete()`) — Referensi Quotation asal.
     * Tambahkan `expected_delivery_date` (`date nullable`) — Target barang tiba dari supplier.
     * Tambahkan `subtotal`, `discount`, `tax`, `grand_total` (`decimal(15,2) default 0`). *(Pertahankan kolom `amount` & `items` lama untuk kompatibilitas mundur, sinkronkan nilainya secara otomatis di `AdminService`)*.
  2. **Tabel Baru `purchase_order_items` (Mendukung Snapshot & Pemecahan Komponen Cisco):**
     * `id`
     * `purchase_order_id` (`foreignId constrained('purchase_orders')->cascadeOnDelete()`)
     * `proposal_item_id` (`foreignId nullable constrained('proposal_items')->nullOnDelete()`) — **Kunci relasi 1-ke-Banyak:** Satu `proposal_item` pada Quotation bisa menghasilkan banyak baris `purchase_order_items` (misal: 1 baris Router Cisco di Quotation dipecah menjadi 3 baris di PO Supplier: Hardware + SmartNet License + Power Cord) tanpa mengubah/merusak Quotation awal!
     * `product_id` (`foreignId nullable constrained('products')->nullOnDelete()`) — Referensi ke Master Data Barang.
     * **Kolom Snapshot Transaksi (Wajib disimpan saat PO dibuat):**
       * `part_number_snapshot` (`string nullable`)
       * `item_name_snapshot` (`string`)
       * `description_snapshot` (`text nullable`)
       * `category_snapshot` (`string default 'barang'`)
       * `unit_snapshot` (`string default 'unit'`)
       * `quantity_ordered` (`decimal(12,2)`)
       * `quantity_received` (`decimal(12,2) default 0`) — Diperbarui otomatis di Tahap 3.
       * `unit_price` (`decimal(15,2)`) — Harga beli aktual pada PO ini.
       * `subtotal` (`decimal(15,2)`)
* **Fitur "Tarik Data dari Quotation Deal" di `AdminController@createPo`:**
  * Saat Admin memilih Quotation/Project yang sudah Deal, form PO otomatis memuat daftar item dari `proposal_items`.
  * Admin dapat: (1) memilih untuk memesan sebagian item saja ke Supplier A dan sisanya di PO lain untuk Supplier B, (2) memecah/menambah baris komponen lisensi di bawah item Quotation terkait, dan (3) mengambil item dari Master Data Barang (`products`).

### 2.2 Kriteria Selesai (DoD Tahap 2)
- [ ] Master Data Barang memiliki `part_number` (PN Number), `partner_id` (Supplier), `buy_price`, dan `default_price` (Harga Jual).
- [ ] Admin dapat membuat PO Supplier dengan menarik item langsung dari Quotation Deal tanpa ketik ulang, serta dapat memecah 1 item Quotation menjadi beberapa baris item PO (kasus lisensi Cisco).
- [ ] Perubahan harga/deskripsi di Master Barang di kemudian hari tidak mengubah data historis pada PO lama karena tersimpan di kolom `*_snapshot`.

---

## TAHAP 3 — Penerimaan (`Goods Receipts`), Penyimpanan Nomor Seri & Pengiriman Barang

Sesuai wawancara Admin (menit 04:05 & 16:04), setelah PO diterbitkan ke Supplier, barang sering kali dikirim bertahap (parsial) dan perlu dicatat **Serial Number (SN)**-nya serta alur barang keluar ke pelanggan.

### 3.1 Target Perubahan Presisi di Codebase
1. **Tabel Baru `goods_receipts` (Terima Barang Masuk dari Supplier):**
   * `id`, `receipt_number` (`unique`), `purchase_order_id` (`constrained`), `partner_id` (`nullable constrained`), `project_id` (`nullable constrained`), `received_date` (`date`), `supplier_delivery_note_no` (`string nullable` — Nomor Surat Jalan Supplier), `received_by` (`foreignId constrained('users')`), `status` (`draft`, `verified`), `notes` (`text nullable`), `attachment_path` (`string nullable` di disk `private`), `timestamps`, `softDeletes`.
2. **Tabel Baru `goods_receipt_items` (Detail Fisik & Serial Number):**
   * `id`, `goods_receipt_id` (`cascadeOnDelete`), `purchase_order_item_id` (`constrained('purchase_order_items')`), `product_id` (`nullable constrained`), `quantity_received` (`decimal(12,2)`), `serial_numbers` (`json nullable` — menyimpan array Serial Number per unit yang diterima), `condition_notes` (`string nullable`).
3. **Tabel Baru `goods_deliveries` & `goods_delivery_items` (Surat Jalan / Pengiriman Barang Keluar ke Pelanggan):**
   * Mencatat pengiriman barang dari gudang/kantor 3DY ke lokasi pelanggan (`project_id`, `customer_id`, `delivery_number`, `delivery_date`, `recipient_name`, `status`: `prepared`, `shipped`, `delivered`, serta item + `serial_numbers` yang dikirim).
4. **Logika Kalkulasi Parsial Otomatis di `AdminService`:**
   * Setiap kali `GoodsReceipt` disimpan/diverifikasi, sistem menghitung ulang `purchase_order_items.quantity_received`.
   * Contoh: Jika `quantity_ordered = 10` dan baru diterima `6`, maka sisa pesanan (`outstanding_qty`) otomatis tercatat `4`, dan status PO menjadi `diproses` (Partially Received) sampai seluruh item terpenuhi (`selesai`).

### 3.2 Kriteria Selesai (DoD Tahap 3)
- [ ] Admin dapat membuat dokumen **Terima Barang** dengan menarik langsung daftar item dari **PO Supplier** (`purchase_orders`).
- [ ] Mendukung penerimaan parsial (pesan 10, datang 6, sisa 4 terlihat jelas) dan pencatatan **Serial Number (SN)**.
- [ ] Pengiriman barang keluar ke pelanggan tercatat dan dapat ditelusuri per proyek.

---

## TAHAP 4 — Invoice Supplier (Tagihan Masuk), Uang Muka (DP) & Pembayaran Supplier

Transkrip wawancara Admin (menit 04:20 & 16:15) menegaskan fakta krusial: **barang sering datang lebih dulu hanya dengan Surat Jalan, sedangkan Invoice dari Supplier baru datang belakangan**. Selain itu, ada kalanya kantor harus membayar **Uang Muka (DP)** ke supplier.

### 4.1 Pemisahan Tegas: Invoice Supplier (AP) vs Invoice Pelanggan (AR)
* Tabel `invoices` dan `payments` yang sudah ada di codebase saat ini digunakan untuk **Pelanggan (Accounts Receivable / Uang Masuk)**.
* Untuk **Supplier (Accounts Payable / Uang Keluar)**, buat tabel khusus agar tidak bercampur!

### 4.2 Target Perubahan Presisi di Codebase
1. **Tabel Baru `supplier_invoices` (Invoice Masuk dari Supplier):**
   * `id`, `supplier_invoice_number` (`string` — Nomor Invoice resmi dari Vendor), `internal_ref_number` (`string unique`), `purchase_order_id` (`constrained('purchase_orders')`), `goods_receipt_id` (`foreignId nullable constrained('goods_receipts')->nullOnDelete()` — boleh kosong jika tagihan DP sebelum barang datang, atau diisi dengan menarik data dari *Terima Barang*), `partner_id` (`constrained('partners')`), `project_id` (`nullable constrained('projects')`), `invoice_type` (`enum: 'dp', 'partial', 'full'`), `amount` (`decimal(15,2)`), `paid_amount` (`decimal(15,2) default 0`), `status` (`unpaid`, `partial`, `paid`, `cancelled`), `issue_date` (`date`), `due_date` (`date nullable`), `invoice_file_path` (`string nullable` di disk `private`), `notes` (`text nullable`), `created_by`, `timestamps`, `softDeletes`.
   * Tambahkan *unique composite index* `unique(['partner_id', 'supplier_invoice_number'])` agar Admin tidak bisa menginput nomor invoice supplier yang sama dua kali.
2. **Tabel Baru `supplier_payments` (Pembayaran Keluar / Uang Muka ke Supplier):**
   * `id`, `supplier_invoice_id` (`constrained('supplier_invoices')->cascadeOnDelete()`), `purchase_order_id` (`nullable constrained('purchase_orders')`), `amount` (`decimal(15,2)`), `payment_date` (`date`), `payment_method` (`string`), `proof_file` (`string nullable` di disk `private`, hanya bisa diakses `manage-admin`), `notes` (`text nullable`), `created_by`, `timestamps`, `softDeletes`.
3. **2 Laporan & Filter Tracking Wajib (Sesuai Permintaan Spesifik Admin di Menit 04:40):**
   * **Filter 1 — "Barang Sudah Diterima, Invoice Supplier Belum Masuk" (*Unbilled Goods Receipts*):** Query `GoodsReceipt` yang statusnya `verified` tetapi belum memiliki relasi di `supplier_invoices` (atau total nilai PO belum tertagih penuh).
   * **Filter 2 — "PO Sudah Terbit, Barang Belum Dikirim/Belum Lengkap" (*Pending PO Deliveries*):** Query `PurchaseOrder` berstatus `diproses` yang masih memiliki `quantity_ordered > quantity_received`.

### 4.3 Kriteria Selesai (DoD Tahap 4)
- [ ] Admin dapat menarik data dari **Terima Barang (`GoodsReceipt`)** untuk mencatat **Invoice Masuk (`SupplierInvoice`)**, atau mencatat Invoice Uang Muka (DP) langsung dari PO.
- [ ] Tersedia tab/laporan instan: **(1) Barang Sudah Diterima tapi Belum Ada Invoice Supplier**, dan **(2) Barang Sudah Di-PO tapi Belum Dikirim Supplier**.

---

## TAHAP 5 — Instalasi, BAST Teknisi, Penagihan Pelanggan (`Invoices`) & Closing Proyek Sales

Tahap ini menghubungkan hasil kerja lapangan **Teknisi** (`projects`, `instalasis`, `project_documents`) ke meja **Admin Finance** untuk penagihan ke pelanggan dan penentuan **Closing Project Sales** (wawancara menit 05:20 & 18:50).

### 5.1 Target Perubahan Presisi di Codebase
1. **Pencatatan Nomor & Tanggal BAST pada Proyek (`projects` & `project_documents`):**
   * **Kebutuhan Wawancara (Menit 18:55):** Pada order pelanggan yang memuat item instalasi, setelah teknisi selesai instalasi dan menyerahkan BAST, Admin harus menginput **Nomor BAST** dan **Tanggal BAST**.
   * **Tindakan Database (Migrasi Aditif pada `projects`):**
     * Tambahkan kolom:
       * `requires_installation` (`boolean default true`) — Otomatis `true` jika di `proposal_items` ada item bertipe `instalasi`/`jasa`.
       * `bast_number` (`string nullable unique`) — Nomor dokumen BAST resmi.
       * `bast_date` (`date nullable`) — Tanggal BAST ditandatangani klien.
       * `bast_document_id` (`foreignId nullable constrained('project_documents')->nullOnDelete()`) — Link ke file PDF BAST yang diunggah Teknisi/Admin di disk `private`.
       * `delivery_deadline` (`date nullable`) — Disalin otomatis dari `proposals.delivery_deadline` saat `convertToProject` untuk memantau tenggat waktu SPK (anti-denda).
       * `commercial_closed_at` (`timestamp nullable`) — Waktu proyek resmi dinyatakan **Closing Penuh** untuk Sales.
2. **Penyempurnaan Invoice Pelanggan (`invoices` & `payments`):**
   * **Migrasi Aditif pada `invoices`:**
     * Tambahkan `proposal_id` (`foreignId nullable constrained('proposals')->nullOnDelete()`).
     * Tambahkan `invoice_type` (`string default 'full'` — Nilai: `dp`, `termin`, `pelunasan`, `full`).
     * Tambahkan `paid_amount` (`decimal(15,2) default 0`).
     * Perluas status `invoices` menjadi: `unpaid`, `partial` (Dibayar Sebagian/DP), `paid` (Lunas), `cancelled`.
   * **1-Click Pull dari Quotation/Project:** Di `AdminController@createInvoice`, Admin cukup memilih Project/Quotation, lalu seluruh rincian pelanggan, nilai tagihan, dan nomor referensi otomatis terisi tanpa ketik ulang.
   * **Auto-Update Status Saat Payment Masuk:** Saat Admin mencatat pembayaran pelanggan di `AdminController@storePayment`, sistem otomatis menjumlahkan `payments.amount`. Jika baru sebagian $\rightarrow$ `status = 'partial'`; jika sudah penuh $\rightarrow$ `status = 'paid'`.
3. **Aturan Otomatis "Closing Project Sales" (Wawancara Menit 05:20):**
   * Admin menjelaskan: *"Closing project itu kalau project udah done, udah selesai instalasi, udah selesai kirim barang semua, dan dia udah bayar, itu masuk ke closingan Sales."*
   * Buat method `Project::evaluateCommercialClosing()` di `AdminService` / `ProjectObserver`:
     * Sebuah proyek otomatis ditandai **Closing Sales (`commercial_closed_at = now()`)** apabila:
       1. Pengiriman barang ke pelanggan selesai, DAN
       2. Jika `requires_installation == true`, status instalasi/proyek sudah `Done` + memiliki `bast_number`, DAN
       3. Seluruh `invoices` pelanggan pada proyek tersebut sudah berstatus `paid` (Lunas 100%).

### 5.2 Kriteria Selesai (DoD Tahap 5)
- [ ] Admin dapat menginput `bast_number` dan melihat file BAST dari Teknisi langsung dari halaman proyek/Admin.
- [ ] Admin dapat menerbitkan Invoice Pelanggan (DP/Termin/Pelunasan) dengan menarik data 1-klik dari Quotation/Project.
- [ ] Status **Closing Project Sales** terhitung otomatis hanya jika Barang Terkirim + Instalasi/BAST Selesai + Invoice Pelanggan Lunas.

---

## TAHAP 6 — Dashboard Operasional Purchasing & Finance (`/manage/admin`)

Mengganti halaman stub `manage.placeholder` pada rute `GET /manage/admin` menjadi **Admin Command Center** berbasis **Modular Blade Partials + Alpine.js Tabs** yang dibangun di atas data riil Tahap 1–5.

### 6.1 Struktur View Modular (`resources/views/admin/`)
```text
resources/views/admin/
 ├── hub.blade.php                         <-- Layout utama /manage/admin + Filter PT (All, NTI, MGK, TPS, WANI)
 └── partials/
      ├── kpi-cards.blade.php              <-- Indikator antrean kerja & peringatan tenggat SPK (Anti-Denda)
      ├── tab-handoff-sales.blade.php      <-- Antrean Quotation Deal (PO Masuk) dari Sales siap diproses
      ├── tab-purchasing.blade.php         <-- Antrean PO belum dikirim & Terima Barang belum ada Invoice Supplier
      ├── tab-finance-ap.blade.php         <-- Antrean Invoice Supplier jatuh tempo & Uang Muka (DP) Vendor
      └── tab-finance-ar-bast.blade.php    <-- Proyek siap ditagih (BAST selesai), Invoice Klien belum lunas, & Proyek Closing
```

### 6.2 Daftar Antrean Kerja (Actionable Queues) di Dashboard
1. **Alert Tenggat Waktu SPK / Pengiriman (Anti-Denda):** Menampilkan proyek yang `delivery_deadline`-nya kurang dari $7$ hari atau sudah lewat tetapi barang belum terkirim semua.
2. **Panel Purchasing:**
   * *Quotation Deal Baru (Siap Belanja):* Tombol langsung **"+ Tarik ke PO Supplier"**.
   * *PO Belum Dikirim Supplier (Outstanding Delivery):* Tombol langsung **"+ Catat Terima Barang"**.
   * *Barang Sudah Diterima tapi Belum Ada Invoice Supplier:* Tombol langsung **"+ Input Invoice Supplier"**.
3. **Panel Finance (AP & AR):**
   * *Tagihan Supplier Belum Dibayar (`supplier_invoices` unpaid/partial):* Tombol **"+ Bayar Supplier"**.
   * *Proyek Selesai BAST / Deal Siap Ditagih ke Pelanggan:* Tombol **"+ Buat Invoice Pelanggan"**.
   * *Invoice Pelanggan Belum Lunas (`invoices` unpaid/partial):* Tombol **"+ Catat Payment Pelanggan"**.

### 6.3 Aturan UI/UX & Query Performance (Wajib Dipatuhi Agent)
* **Anti Raw-Null (`-`):** Dilarang menampilkan tanda `-` mentah untuk data kosong; gunakan teks *muted italic* deskriptif (misal: *"Menunggu BAST Teknisi"*, *"Invoice Supplier belum masuk"*).
* **SQL-Level Filtering & Eager Loading:** Semua hitungan KPI dan tabel antrean di `/manage/admin` wajib menggunakan `with()`, `withSum()`, dan `whereHas()` di level SQLite/SQL (dilarang menarik `.get()` seluruh tabel lalu memfilter di Collection PHP).

---

## RINGKASAN URUTAN EKSEKUSI UNTUK AI AGENT (BATCH IMPLEMENTATION)

| Batch / Tahap | Fokus Area & File Utama | Gate Verifikasi sebelum Lanjut |
| :--- | :--- | :--- |
| **Tahap 0** | `RoleAndPermissionSeeder.php`, `ProposalController.php`, `AdminService.php` | `php artisan test` hijau + tes akses role `sales` ke `/admin/*` menghasilkan `403`. |
| **Tahap 1** | Migrasi `proposals` & `proposal_items`, `ProposalController.php`, view `sales/proposals/*` | Fitur *Perhitungan Proyek* (Modal vs Jual), pemisahan *Inside Sales* vs *Sales Owner*, dan handoff Deal berjalan. |
| **Tahap 2** | Migrasi `products`, `purchase_orders`, `purchase_order_items`, `ProductController.php`, `AdminController.php` | Pembuatan PO Supplier bisa tarik dari Quotation Deal, pecah komponen/lisensi (kasus Cisco), dan simpan *snapshot*. |
| **Tahap 3** | Migrasi & CRUD `goods_receipts`, `goods_receipt_items`, `goods_deliveries` | Penerimaan barang parsial (pesan 10 datang 6) dan input *Serial Number* berjalan. |
| **Tahap 4** | Migrasi & CRUD `supplier_invoices`, `supplier_payments` + 2 laporan tracking Purchasing | Barang datang duluan tanpa invoice tercatat rapi, laporan *Unbilled Receipt* & *Pending PO* akurat. |
| **Tahap 5** | Migrasi BAST di `projects`, update `invoices` & `payments` pelanggan, logika `evaluateCommercialClosing()` | Input Nomor BAST, 1-Click Invoice Klien (DP/Pelunasan), dan syarat *Closing Sales* berjalan otomatis. |
| **Tahap 6** | `AdminController@hub`, route `GET /manage/admin`, `resources/views/admin/hub.blade.php` + partials | Dashboard Purchasing & Finance tampil penuh dengan filter 4 PT (`NTI`, `MGK`, `TPS`, `WANI`) tanpa N+1 query. |