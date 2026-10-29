# PLAN V2 — Ekosistem Sales & Quotation 3DY App

> **Status:** draft untuk konsultasi, **belum dieksekusi**
> **Baseline kode:** `main` @ `1ac4b6b` (27 Oktober 2026)
> **Menggantikan:** `docs/COMPLETE_SALES_ECOSYSTEM_PLAN.md`
> **Tujuan:** menyambungkan alur Marketing → Sales → Inside Sales → Teknisi → Purchasing → Finance tanpa menggandakan data, tanpa membocorkan harga supplier, dan tanpa memecah fitur yang sudah jadi.

---

## 1. Ringkasan

Ekosistem Sales di 3DY App saat ini sudah memiliki banyak fondasi:

- Lead & pipeline Marketing → Sales.
- Quotation (nama UI; tabel tetap `proposals`).
- Technical Request & Price Request.
- Supplier PO, goods receipt, goods delivery.
- Invoice DP/termin/pelunasan.
- BAST & closing komersial.

Plan lama ditulis dengan asumsi kode sebelum 27 Oktober. Banyak “celah” di plan lama sudah tertutup, dan beberapa nama kolom di plan lama **berbeda** dengan yang sudah ada. Karena itu plan ini:

1. **Memakai kolom yang sudah ada** (`po_number`, `po_date`, `delivery_deadline`, `unit_cost`, dll).
2. **Tidak membuat dua sumber data** untuk hal yang sama.
3. **Memisahkan** harga jual, harga modal quotation, dan harga beli supplier.
4. **Mengunci aturan bisnis** yang sudah disepakati.
5. Dibagi menjadi batch kecil yang bisa diuji per batch.

---

## 2. Keputusan bisnis yang sudah dikunci

| # | Keputusan | Konsekuensi teknis |
|---|---|---|
| 1 | **Sales tidak boleh melihat harga beli supplier.** | Nominal Supplier PO, invoice supplier, dan laporan pengadaan harus disembunyikan dari Sales. Sales tetap boleh melihat **status** pengadaan. |
| 2 | **PO pelanggan wajib ada sebelum Quotation menjadi Project.** | `convertToProject` wajib `accepted` + dokumen PO + nomor PO. Test lama yang convert tanpa PO harus diubah. |
| 3 | **Syarat pembayaran (TOP) harus terstruktur.** | Tambah tipe pembayaran, DP %, term days, dan opsional jadwal termin. Invoice bisa memakai data ini. |
| 4 | **PDF Quotation dibuat otomatis setelah pelanggan ACC.** | Saat status menjadi `accepted`, generate PDF, simpan privat, lalu lampirkan ke Documents Customer (kategori Quotation). |
| 5 | **Engineer membalas Price Request dengan PDF berisi perhitungan jasa.** | Tidak ada auto-import/parsing PDF. Sales membuka PDF lalu mengetik satu baris jasa instalasi di Quotation. Sistem hanya menyimpan link/referensi ke Price Request. |
| 6 | **Tidak ada import item dari Sizing / Request Harga ke Quotation.** | Dua modul itu milik Project (setelah deal), bukan pra-quotation. Barang tetap dari katalog produk. |

### Asumsi default (perlu dikonfirmasi sebelum eksekusi)

- **Sales juga tidak melihat `unit_cost`/margin quotation** untuk sementara. Jika bisnis ingin Sales melihat margin, cukup beri permission `view-cost` tanpa membuka `view-supplier-cost`.
- **PO wajib hanya untuk project yang berasal dari Sales/Quotation.** Project manual internal yang dibuat Teknisi/Management tetap boleh tanpa PO, dengan catatan alasan.
- **ACC = pelanggan menyetujui Quotation** (status `accepted`), bukan ACC internal.
- **PDF disimpan saat accepted**, bukan saat draft/sent.
- **Closing project jasa tanpa barang** perlu aturan terpisah (lihat Open Decisions).

---

## 3. Baseline: yang sudah ada — JANGAN dibuat ulang

| Area | Yang sudah ada | Lokasi |
|---|---|---|
| Nama UI Quotation | Sidebar, header, judul halaman sudah “Quotation” | `resources/views/layouts/partials/sidebar.blade.php`, `header.blade.php`, `sales/proposals/*` |
| Customer PO | `po_document_path`, `po_number`, `po_date`, `delivery_deadline`, `delivery_address` | `database/migrations/2026_10_27_000004_add_customer_po_fields_to_proposals.php` |
| Cost & margin | `products.cost_price`, `proposal_items.unit_cost`, `proposals.total_cost/gross_profit/gross_margin`, `proposals.internal_notes` | `2026_10_27_000002`, `000003` |
| Link Project | `projects.proposal_id` unique, `quotation_number`, `sales_user_id` | `2026_10_27_000001`, `proposals.convertToProject` |
| Purchasing | `suppliers`, `product_suppliers`, `supplier_purchase_orders`, `supplier_purchase_order_items`, antrean handoff | `2026_10_27_120002`, `120003` |
| Barang masuk/keluar | `goods_receipts`, `goods_deliveries` | `2026_10_27_130001`, `130002` |
| Invoice pelanggan | `invoices.proposal_id`, `invoice_type`, `paid_amount`, status `unpaid/partial/paid` | `2026_10_27_130005` |
| BAST & closing | `projects.bast_number`, `bast_date`, `bast_document_id`, `delivery_deadline`, `commercial_closed_at`, `evaluateCommercialClosing()` | `2026_10_27_130004` |
| Preview cost-safe | Preview pelanggan tidak menampilkan `unit_cost`, margin, `internal_notes` | `resources/views/sales/proposals/preview.blade.php` |
| Quick follow-up | Form inline “Log Follow Up” di detail lead | `resources/views/leads/show.blade.php` |
| SalesService | Sudah ada `SalesService` untuk follow-up | `app/Services/SalesService.php` |
| Test | Inside Sales, cost/margin, handoff, unique convert | `tests/Feature/ProposalInsideSalesTest.php`, `ProposalCostMarginTest.php`, `PurchasingHandoffTest.php`, `ProposalConvertUniqueTest.php` |

### Catatan worktree

Di `/var/www/3dyapp-sales` sudah ada migrasi **belum di-commit**:

```text
database/migrations/2026_10_27_140001_add_billing_shipping_and_finance_fields_to_customers.php
```

Migrasi itu sudah menambah `billing_address`, `shipping_address`, `finance_pic_name`, `finance_pic_phone`, `finance_pic_email`.

**Aturan:** jangan buat migrasi baru dengan kolom yang sama. Lanjutkan migrasi tersebut, lalu tambahkan `$fillable`, validasi, form, dan cetakan invoice di batch yang sesuai.

Selain itu, 3 view produk di worktree sales sedang dihapus/dipindah ke `.legacy-products`. Pastikan route `products.*` tidak pecah sebelum merge.

---

## 4. Perubahan database

Semua perubahan **aditif** dan memakai nama yang konsisten.

### 4.1 `proposals`

| Kolom | Tipe | Nullable | Default | Keterangan |
|---|---|---:|---|---|
| `sales_owner_id` | FK `users.id` | ya | null | Snapshot Sales pemilik lead saat quotation dibuat. Dipakai untuk akses, PDF, KPI, dan convert. |
| `revision_number` | unsigned integer | tidak | 0 | `Rev.0`, `Rev.1`, dst. |
| `payment_term_type` | string(20) | ya | null | `full`, `dp`, `termin`, `net`. |
| `dp_percent` | decimal(5,2) | ya | null | Contoh 30.00. |
| `term_days` | unsigned small int | ya | null | Contoh 14/30. |
| `payment_schedule` | JSON | ya | null | Opsional, untuk termin lebih dari 2: `[{label, percent, due_days}]`. |
| `delivery_terms` | string(255) | ya | null | Estimasi pengiriman/inden. |
| `price_request_id` | FK `price_requests.id` | ya | null | Opsional; menandai Price Request jasa yang dipakai quotation. |

**Jangan tambah:** `client_po_number`, `client_po_date`, `delivery_deadline_date` — sudah ada dengan nama lain.

### 4.2 `proposal_items`

| Kolom | Tipe | Nullable | Default | Keterangan |
|---|---|---:|---|---|
| `item_notes` | string(255) | ya | null | Catatan internal per item (tidak tercetak di PDF pelanggan). |
| `source_type` | string(20) | tidak | `manual` | `catalog`, `service`, `manual`. Memudahkan laporan asal item. |
| `price_request_reply_id` | FK `price_request_replies.id` | ya | null | Opsional; menandai baris jasa yang diambil dari balasan engineer. |

**Jangan tambah:** `cost_price` di `proposal_items` — sudah ada `unit_cost`. `unit_cost` harus tetap **nullable** (bukan default 0) agar item tanpa modal tidak dihitung sebagai margin 100%.

**Jangan tambah:** `partner_id` ke `partners` — `partners` adalah relasi marketing, bukan supplier. Jika perlu preferensi supplier per item, gunakan `suppliers` / `product_suppliers`, atau biarkan Purchasing memilih supplier di Supplier PO.

### 4.3 `customers`

Gunakan migrasi yang sudah ada di worktree sales:

- `billing_address` (text, nullable)
- `shipping_address` (text, nullable)
- `finance_pic_name` (string, nullable)
- `finance_pic_phone` (string, nullable)
- `finance_pic_email` (string, nullable)

Wajib ditambahkan juga ke:

- `app/Models/Customer.php` → `$fillable`
- `app/Http/Controllers/CustomerController.php` → validasi store/update
- `resources/views/customers/create.blade.php` dan `edit.blade.php`
- Cetakan invoice / halaman invoice admin (billing address & finance contact)
- `resources/views/customers/show.blade.php` (ringkasan)

### 4.4 `projects`

Tidak perlu kolom baru untuk link quotation. Yang sudah ada dipakai:

- `proposal_id` (unique)
- `quotation_number`
- `sales_user_id`
- `delivery_deadline`
- `requires_installation`
- `bast_*`
- `commercial_closed_at`

**Yang harus diperbaiki:** `convertToProject` harus menyalin `delivery_deadline` dan mengisi `requires_installation` sesuai jenis pekerjaan. Jangan memakai `end_date` untuk tenggat komersial.

### 4.5 Dokumen Quotation

Gunakan `project_documents`:

- `customer_id` = customer quotation
- `document_category_id` = kategori **Quotation** (sudah ada di `DocumentCategorySeeder`)
- `doc_type` = `quotation`
- `documentable_type` = `App\Models\Proposal`
- `documentable_id` = id proposal
- file PDF disimpan di disk `private`

Perlu:

- Tambah `quotation` ke `ProjectDocument::DOC_TYPES` dan labelnya.
- Route download/preview quotation PDF.
- Tampilkan di tab Documents Customer dan di halaman Quotation.

### 4.6 Permission baru

| Permission | Untuk | Fungsi |
|---|---|---|
| `view-cost` | Admin, Purchasing, Management, CEO, Super Admin | Melihat `unit_cost`, `total_cost`, `gross_profit`, `gross_margin` di quotation. |
| `view-supplier-cost` | Admin, Purchasing, Management, CEO, Super Admin | Melihat nominal Supplier PO, invoice supplier, dan laporan pengadaan. |
| `update-cost` | Admin, Purchasing, Super Admin | Mengubah `unit_cost`/`item_notes` lewat form costing khusus. |

**Sales, Inside Sales, Lead Technician, Prakerin tidak mendapat permission di atas** (kecuali bisnis memutuskan lain untuk `view-cost`).

---

## 5. Matriks akses final

| Peran | Quotation | Harga jual | `unit_cost`/margin | Nominal Supplier PO | Status pengadaan/invoice |
|---|---|---|---|---|---|
| Sales | Buat/ubah quotation milik lead-nya | Ya | **Tidak** (default) | **Tidak** | Ya, read-only |
| Inside Sales | Buat/ubah quotation untuk lead yang di-task | Ya | Tidak | Tidak | Terbatas pada quotation-nya |
| Admin/Purchasing | Lihat semua, isi costing | Ya | Ya | Ya | Ya |
| Management | Read-only semua | Ya | Ya | Ya | Ya |
| CEO | Read-only semua | Ya | Ya | Ya | Ya |
| Lead Technician | Read-only quotation? | Ya | Tidak | Tidak | Status teknis |
| Technician | Tidak | — | — | — | Status teknis |

Catatan:

- `view-admin` **tetap dipertahankan** untuk Sales agar bisa melihat status invoice/pengadaan.
- Nominal supplier **disembunyikan** dari Sales walaupun punya `view-admin`.
- Jika nanti Sales boleh melihat margin quotation, cukup grant `view-cost`; `view-supplier-cost` tetap tidak diberikan.

---

## 6. Alur bisnis final

```text
Marketing buat Lead
   ↓
Sales acknowledge Lead
   ↓
Sales buat Quotation
   ├─ Barang: pilih dari katalog produk
   ├─ Jasa instalasi: buka PDF balasan Price Request, ketik manual 1 baris jasa
   ├─ Isi payment terms, delivery terms, valid until
   └─ Admin/Purchasing isi unit_cost (jika perlu)
   ↓
Quotation: draft → ready → sent → viewed → revision → accepted
   ↓ (accepted)
Generate PDF Quotation
   ├─ Simpan privat
   ├─ Lampirkan ke Documents Customer (kategori Quotation)
   └─ Lead menjadi WON
   ↓
Upload PO pelanggan
   ├─ File PO
   ├─ Nomor PO
   ├─ Tanggal PO
   └─ Delivery deadline
   ↓
Convert to Project (wajib accepted + PO lengkap)
   ├─ proposal_id
   ├─ quotation_number
   ├─ sales_user_id (dari sales_owner_id)
   ├─ delivery_deadline
   └─ requires_installation
   ↓
Purchasing: Supplier PO → Goods Receipt → Goods Delivery
   ↓
Teknisi: Instalasi → BAST
   ↓
Finance: Invoice DP/Termin/Pelunasan
   ↓
Project commercial_closed_at
   ↓
Sales melihat tracker status (tanpa nominal supplier)
```

---

## 7. Batch eksekusi

### BATCH 0 — Rekonsiliasi & keputusan (tanpa coding)

**Tujuan:** memastikan plan tidak bentrok dengan pekerjaan yang sudah ada.

**Tugas:**

1. Cek `/var/www/3dyapp-sales`:
   - migrasi customer fields yang belum di-commit,
   - view produk yang dihapus/dipindah.
2. Konfirmasi Open Decisions di bagian 10.
3. Kunci nama kolom:
   - `po_number`, `po_date`, `delivery_deadline`, `delivery_address`
   - `unit_cost` (bukan `cost_price`)
   - `suppliers` (bukan `partners`)
   - `delivery_deadline` (bukan `end_date`)
4. Audit test yang akan berubah:
   - `ProposalModuleTest::test_convert_to_project_once_with_mapping`
   - `ProposalModuleTest::test_po_upload_and_download`
   - `ProposalCostMarginTest::test_role_matrix_for_cost_access`
   - `RoleMenuTest::test_sales_menu_and_access`
   - `ProcurementFlowTest` (sales read admin)
   - `SupplierPurchasingTest`
   - `PurchasingHandoffTest`
5. Tulis hasil keputusan ke plan ini (update bagian 10).

**Output:** plan final yang disetujui, daftar test yang akan diubah.

---

### BATCH 1 — Akses & keamanan

**Tujuan:** Sales bisa bekerja tanpa melihat harga supplier; Inside Sales bisa mengakses katalog; dokumen lead bisa dipakai teknisi.

**File utama:**

- `database/seeders/RoleAndPermissionSeeder.php` atau command sync permission baru
- `app/Policies/ProposalPolicy.php`
- `app/Policies/ProductPolicy.php`
- `routes/web.php`
- `resources/views/layouts/partials/sidebar.blade.php`
- `resources/views/sales/proposals/show.blade.php`, `_form.blade.php`
- `resources/views/admin/supplier-pos/*`
- `resources/views/admin/supplier-invoices/*`
- `resources/views/admin/purchasing/*`
- `resources/views/admin/hub.blade.php`
- `app/Http/Controllers/ProposalController.php`
- `app/Http/Controllers/TechnicalRequestController.php`
- `resources/views/sales/technical-requests/show.blade.php`

**Tugas:**

1. **Permission baru**
   - Tambah `view-cost`, `view-supplier-cost`, `update-cost`.
   - Assign ke Admin/Purchasing/Management/CEO/Super Admin.
   - Jangan assign ke Sales/Inside Sales/Lead Technician/Prakerin.
   - **Jangan jalankan `RoleAndPermissionSeeder` di produksi tanpa backup** karena seeder menghapus semua assignment role. Buat command/migration sync permission yang idempotent.

2. **Quotation cost**
   - `ProposalPolicy::viewCost` memakai `view-cost` (bukan `manage-sales`).
   - `ProposalPolicy::updateCost` memakai `update-cost`.
   - `show`/`_form` hanya menampilkan `unit_cost`/margin jika `view-cost`.
   - Backend anti-tamper tetap menolak `unit_cost` dari yang tidak berhak.

3. **Form costing khusus Admin/Purchasing**
   - Route baru: `POST sales/proposals/{proposal}/costing` (nama bisa disesuaikan).
   - Hanya mengubah `unit_cost`, `item_notes`, dan opsional preferensi supplier.
   - Tidak boleh mengubah `unit_price`, diskon, status, atau item description.
   - Tambah audit log (`LeadActivity` atau activity log khusus).

4. **Supplier cost**
   - `admin/supplier-pos` index/show: sembunyikan `grand_total`, `unit_price`, dan subtotal dari yang tidak punya `view-supplier-cost`.
   - `admin/supplier-invoices` index/show: sembunyikan amount/paid/remaining.
   - `admin/purchasing` handoff: sembunyikan nominal Supplier PO.
   - `manage/admin` hub: sembunyikan `supplier_invoices_outstanding_amount`.
   - Sales tetap melihat status (`draft/ordered/received`, `unpaid/partial/paid`).

5. **Inside Sales produk**
   - Tambah `manage-inside-sales` ke route baca `/products` dan `/products/picker`.
   - `ProductController@index` dan `Product::saleable()` filter `is_active = true` dan `status in (approved, published)`.
   - Pastikan produk hasil crawl AI (`review`) tidak muncul di picker.

6. **Dokumen Lead → Technical Request**
   - Tampilkan daftar `lead.documents` di halaman detail Technical Request.
   - Filter kategori yang aman untuk teknisi: `technical`, `requirement`, `supporting`, `company_profile`.
   - **Jangan tampilkan** `proposal`/`quotation` jika berisi harga.
   - Tambah route download/preview yang mengizinkan teknisi terkait, dengan `LeadPolicy::view`.
   - Jangan memakai route marketing-only yang sudah ada.

**Test:**

- Sales 403/ tidak melihat `unit_cost`/margin.
- Admin/Purchasing bisa mengisi costing.
- Sales tidak melihat nominal Supplier PO/invoice.
- Inside Sales bisa buka `/products` dan picker.
- Produk `review` tidak muncul di picker.
- Teknisi bisa melihat/mengunduh dokumen lead yang diizinkan, tidak bisa yang sensitif.

---

### BATCH 2 — Data Quotation, numbering, dan PDF

**Tujuan:** Quotation punya syarat bayar, revisi, penomoran aman, dan PDF resmi per PT.

**File utama:**

- migrasi baru: `proposals` + `proposal_items`
- `app/Models/Proposal.php`, `ProposalItem.php`
- `app/Http/Controllers/ProposalController.php`
- `app/Services/QuotationPdfService.php` (baru)
- `app/Services/DocumentNumberService.php` (baru/disarankan)
- `resources/views/sales/proposals/{create,edit,show,preview}.blade.php`
- `config/companies.php` atau seeder company profile (baru)
- `composer.json` (DomPDF)

**Tugas:**

1. **Schema**
   - Tambah kolom di bagian 4.1 dan 4.2.
   - Backfill `sales_owner_id` dari `leads.assigned_to`.
   - `revision_number` default 0 untuk data lama.
   - `unit_cost` tetap nullable.

2. **Sales owner**
   - `store`: `created_by = auth()->id()`, `sales_owner_id = lead.assigned_to ?? auth()->id()`.
   - `isOwner`, `scopedQuery`, `show`, `preview`, `convertToProject`, dan KPI memakai `sales_owner_id` (dengan fallback `lead.assigned_to` untuk data lama).
   - Tentukan perilaku saat lead di-reassign: snapshot tidak berubah; akses Sales baru mengikuti lead saat ini? Rekomendasi: akses berdasar `sales_owner_id` + `lead.assigned_to` (keduanya), agar transisi aman.

3. **Payment terms**
   - Form: tipe (`full/dp/termin/net`), DP %, term days, jadwal termin (opsional), delivery terms.
   - Validasi: jika `dp`, `dp_percent` wajib 1–99; jika `net`, `term_days` wajib.
   - Preview/PDF menampilkan label yang mudah dibaca.
   - Form invoice admin memakai data ini untuk menyarankan `invoice_type`, amount, dan `due_date`.

4. **Revision**
   - `revise()` menaikkan `revision_number`.
   - Sebelum edit setelah revisi, arsipkan PDF versi lama (jika sudah ada).
   - PDF menampilkan `Rev. N`.
   - Catat aktivitas revisi di `LeadActivity`.

5. **Numbering aman**
   - Jangan `lockForUpdate()` pada query `COUNT(*)` (tidak valid di PostgreSQL).
   - Buat `document_sequences` (prefix, period, last_number) dengan lock satu baris, atau retry saat unique violation.
   - Terapkan juga ke invoice/PO jika memungkinkan.

6. **PDF Quotation**
   - Tambah `barryvdh/laravel-dompdf` (atau pilihan lain).
   - Buat template PDF: kop/logo per PT, nomor, revisi, tanggal, valid until, customer, alamat, item, subtotal, diskon, PPN, grand total, payment terms, delivery terms, catatan, tanda tangan.
   - **Tidak boleh** menampilkan `unit_cost`, margin, `internal_notes`, atau data supplier.
   - Generate saat `respond(accepted)` berhasil.
   - Simpan ke `storage/app/private/quotations/{proposal_id}/...pdf`.
   - Buat `ProjectDocument`:
     - `customer_id`, `document_category_id` = Quotation
     - `doc_type` = `quotation`
     - `documentable_type` = `App\Models\Proposal`, `documentable_id` = proposal id
   - Tambah route download/preview.
   - Jika generate gagal, jangan batalkan status accepted; catat error dan sediakan tombol “Generate PDF”.
   - Arsipkan PDF lama saat revisi.

7. **PPN**
   - Jika tetap memakai `tax` nominal, PDF hanya menampilkan nominal.
   - Jika ingin “PPN 11%”, tambah `tax_rate` (opsional) dan hitung `tax` dari `net_sales * rate`. Ini keputusan terpisah.

8. **Price Request link**
   - Di form Quotation, tampilkan panel “Price Request terkait” + link PDF balasan engineer.
   - Saat Sales menambah baris jasa, isi `source_type = service`.
   - Opsional: simpan `price_request_id` dan `price_request_reply_id`.
   - Tidak ada parsing PDF.

**Test:**

- Payment terms tersimpan dan tampil di PDF.
- PDF accepted tersimpan + muncul di Documents Customer.
- PDF tidak memuat `unit_cost`/margin/internal notes.
- Revisi menaikkan nomor dan mengarsipkan PDF lama.
- Nomor quotation tidak dobel (test retry/unique).
- Sales tidak bisa mengubah `unit_cost` lewat update biasa.

---

### BATCH 3 — Handoff Deal: PO, Convert, Customer Finance

**Tujuan:** Deal hanya bisa menjadi Project setelah PO lengkap; data penagihan customer lengkap.

**File utama:**

- `app/Http/Controllers/ProposalController.php`
- `app/Http/Controllers/LeadController.php`
- `app/Http/Controllers/CustomerController.php`
- `app/Models/Customer.php`
- `app/Actions/ConvertQuotationToProject.php` (baru)
- `resources/views/sales/proposals/show.blade.php`
- `resources/views/customers/{create,edit,show}.blade.php`
- `resources/views/admin/invoices/*`
- `app/Models/Project.php` (jika perlu)

**Tugas:**

1. **PO pelanggan**
   - `uploadPo` validasi:
     - file wajib jika belum ada,
     - `po_number` wajib,
     - `po_date` wajib,
     - `delivery_deadline` wajib.
   - `isReadyForPurchasing()` = `accepted` + `po_document_path` + `po_number` + `po_date` + `delivery_deadline`.
   - Handoff purchasing hanya menampilkan yang lolos.
   - Update test `test_po_upload_and_download` dan handoff.

2. **Convert guard**
   - `convertToProject` wajib:
     - status `accepted`,
     - PO lengkap,
     - belum pernah dikonversi.
   - `LeadController@convert`:
     - jika lead punya quotation accepted → redirect ke quotation/convert, jangan buat project langsung,
     - jika tidak ada quotation accepted → tolak dengan pesan jelas,
     - **kecuali** project manual internal (Teknisi/Management) yang memang bukan dari Sales.
   - Buat satu action `ConvertQuotationToProject` yang dipakai kedua pintu.
   - Idempotensi: cek `proposal->project` dan unique `projects.proposal_id`; jangan pakai `Project::where(customer_id)->latest()`.

3. **Copy data ke Project**
   - `proposal_id`, `quotation_number`, `sales_user_id = sales_owner_id`.
   - `delivery_deadline` dari proposal.
   - `requires_installation` dari pilihan form/Work Type.
   - `start_date` tetap dari form.
   - Jangan isi `end_date` dengan delivery deadline.

4. **Customer finance**
   - Tambah `$fillable`, validasi, form create/edit, show.
   - `billing_address` untuk invoice/faktur.
   - `shipping_address` untuk pengiriman.
   - `finance_pic_*` untuk kontak penagihan.
   - Invoice admin memakai `billing_address` dan `finance_pic_email`.
   - Tampilkan peringatan di Quotation accepted jika `npwp`/`npwp_document_path` kosong.

5. **Precedence alamat**
   - Pengiriman: `proposal.delivery_address` → `customer.shipping_address` → `customer.address`.
   - Penagihan: `customer.billing_address` → `customer.address`.
   - Dokumentasikan di plan/komentar.

**Test:**

- Upload PO tanpa nomor → 422.
- Convert tanpa PO → 422.
- Convert dengan PO → project membawa `proposal_id`, `quotation_number`, `sales_owner_id`, `delivery_deadline`.
- Direct lead convert dengan quotation accepted → tidak membuat project kedua.
- Direct lead convert tanpa quotation → pesan jelas.
- Customer billing fields tersimpan dan tampil di invoice.
- Project manual internal tetap bisa dibuat tanpa PO.

---

### BATCH 4 — Post-deal tracker

**Tujuan:** Sales bisa memantau eksekusi tanpa melihat harga supplier.

**File utama:**

- `app/Services/SalesProjectTrackerService.php` (baru)
- `app/Http/Controllers/ProposalController.php`
- `app/Http/Controllers/LeadController.php`
- `app/Http/Controllers/ProjectController.php`
- `resources/views/sales/proposals/show.blade.php`
- `resources/views/leads/show.blade.php`
- `resources/views/projects/show.blade.php`
- `app/Console/Commands/RemindDeliveryDeadline.php` (baru)

**Tugas:**

1. **Service/DTO**
   - Ambil project dari `proposal->project`, bukan `lead->projects` (yang berisi semua project customer).
   - Eager load: `project.invoices`, `project.goodsDeliveries`, `project.supplierPurchaseOrders`, `project.bastDocument`.
   - Kembalikan data aman: status, tanggal, countdown, dokumen — **tanpa nominal supplier**.

2. **Status pengadaan**
   - Supplier PO: `draft`, `ordered`, `partially_received`, `received`, `cancelled`.
   - Goods delivery: `prepared`, `shipped`, `delivered`.
   - Tampilkan ringkasan: “Belum ada PO supplier” / “Sebagian diterima” / “Sudah dikirim”.

3. **Deadline**
   - Hitung sisa hari dari `project.delivery_deadline`.
   - Tampilkan warna merah jika ≤ 7 hari / lewat.
   - Command harian untuk notifikasi ke Sales/Admin/Teknisi.

4. **BAST & invoice**
   - BAST: `bast_number`, `bast_date`, `bast_document_id`.
   - Invoice: status `unpaid/partial/paid/cancelled`; nominal invoice pelanggan boleh dilihat Sales (itu harga jual).
   - Closing: `commercial_closed_at`.
   - Jangan tampilkan `supplierPurchaseOrders.grand_total`, `unit_price`, atau invoice supplier.

5. **Closing untuk jasa**
   - Saat ini `evaluateCommercialClosing` mewajibkan goods delivery.
   - Tentukan aturan:
     - opsi A: tambah `projects.requires_delivery` (default true; false untuk jasa),
     - opsi B: Admin bisa closing manual untuk project jasa.
   - Rekomendasi: opsi A + validasi.

6. **Tampilan**
   - Kartu di Quotation accepted dan Lead won.
   - Link ke Project.
   - Fallback teks deskriptif, bukan `-` mentah.

**Test:**

- Tracker hanya muncul untuk project yang benar.
- Sales tidak melihat nominal supplier.
- Countdown deadline benar.
- Closing project instalasi dan non-instalasi.
- Project jasa tanpa goods delivery bisa closing (sesuai aturan baru).

---

### BATCH 5 — Produktivitas & kebersihan

**Tujuan:** menghapus duplikasi dan mempermudah kerja Sales.

**File utama:**

- `app/Services/SalesService.php`
- `app/Http/Controllers/FollowUpController.php`
- `PocController.php`
- `SalesScheduleController.php`
- `SalesMeetingController.php`
- `PriceRequestController.php`
- `app/Http/Controllers/LeadController.php`
- `resources/views/leads/show.blade.php`
- `resources/views/sales/my-leads.blade.php`

**Tugas:**

1. **Service opsi terpadu**
   - Pindahkan `leadOptions`, `leadItems`, `customerOptions`, `customerLabel`, dan `scopeToOwnLeads` ke `SalesService`.
   - Ganti `hasRole('sales')` dengan `PtAccess::isSalesLike()` agar `sales-nti/mgk/tps/wani` ikut ter-scope.
   - Terapkan ke 5 controller (bukan 3): FollowUp, Poc, SalesSchedule, SalesMeeting, PriceRequest.
   - Pastikan `FollowUpController` dan `PocController` memakai aturan yang sama.

2. **Quick follow-up**
   - Form inline sudah ada; tambah input `next_follow_up_date`.
   - Pastikan `FollowUpController@store` menerima dan menyimpan.
   - Pertimbangkan akses `manage-inside-sales` jika Inside Sales juga mencatat follow-up.

3. **Acknowledge lead**
   - Reuse `LeadController@updateStatus` (sudah mengisi `acknowledged_at`).
   - Tambah tombol di `leads/show` dan `sales/my-leads`.
   - Opsional: tambah `acknowledged_by` untuk audit.
   - Tentukan apakah acknowledge hanya menandai “sudah dilihat” atau juga mengubah status.

4. **Rapikan label**
   - Ganti flash message “Proposal” → “Quotation” di controller.
   - Ganti label kategori dokumen jika perlu.
   - Jangan ubah nama tabel/model/route internal.

**Test:**

- Sales-PT hanya melihat lead/follow-up miliknya.
- Quick follow-up menyimpan `next_follow_up_date`.
- Acknowledge mengisi `acknowledged_at` dan menghilangkan lead dari kolom New.
- Dropdown customer/lead konsisten di 5 modul.

---

### BATCH 6 — UAT, data migration, dan rollout

**Tugas:**

1. Backfill:
   - `sales_owner_id` dari `leads.assigned_to`.
   - `revision_number = 0`.
   - Customer billing fields tetap null.
   - Jangan mengisi `unit_cost` lama dengan 0.
2. Permission sync:
   - Jangan jalankan `RoleAndPermissionSeeder` penuh di produksi.
   - Buat command `permissions:sync-sales` atau migration yang hanya menambah permission/role tanpa menghapus assignment.
3. Test:
   - `php artisan test` seluruh suite.
   - Test baru: `QuotationEcosystemV2Test`, `SupplierCostVisibilityTest`, `ConvertRequiresPoTest`, `QuotationPdfTest`, `SalesProjectTrackerTest`, `SalesPtScopeTest`.
4. UAT per role:
   - Sales, Inside Sales, Admin, Purchasing, Finance, Teknisi, Management, CEO.
5. Build:
   - `npm run build` dari `/var/www/3dyapp` (main), bukan worktree.
6. Rollout:
   - Backup DB.
   - `php artisan migrate`.
   - Sync permission.
   - Clear cache/view (`sudo -u www-data php artisan view:clear` jika perlu).
   - Monitor log.

---

## 8. Definition of Done

- [ ] Tidak ada kolom duplikat (`client_po_*`, `cost_price` di item, `partner_id`).
- [ ] Sales tidak melihat `unit_cost`/margin dan nominal supplier (default).
- [ ] Admin/Purchasing bisa mengisi costing lewat route khusus.
- [ ] Inside Sales bisa mengakses katalog/picker.
- [ ] PO wajib sebelum convert; project membawa `proposal_id`, `quotation_number`, `sales_user_id`, `delivery_deadline`.
- [ ] Direct lead convert tidak membuat project duplikat.
- [ ] Payment terms terstruktur dan tampil di quotation/PDF.
- [ ] PDF accepted tersimpan dan muncul di Documents Customer.
- [ ] PDF tidak memuat cost/margin/internal notes.
- [ ] Customer billing/shipping/finance fields tersimpan dan dipakai invoice.
- [ ] Tracker pasca-deal menampilkan status tanpa nominal supplier.
- [ ] Project jasa/instalasi bisa closing sesuai aturan.
- [ ] `SalesService` dipakai 5 controller; sales-PT scoping benar.
- [ ] Semua test hijau.
- [ ] `AGENTS.md` §12 diperbarui jika matriks role berubah.

---

## 9. Risiko & mitigasi

| Risiko | Mitigasi |
|---|---|
| Migrasi customer fields dobel dengan worktree sales | Gunakan file yang sudah ada; jangan buat baru. |
| `RoleAndPermissionSeeder` menghapus assignment | Buat sync permission idempotent; backup DB. |
| Test lama bergantung pada Sales melihat cost/view-admin | Ubah test secara sadar; jangan “fix” dengan membuka akses. |
| PDF generation gagal/lambat | Jangan blok acceptance; sediakan retry/manual generate. |
| `lockForUpdate` pada COUNT | Pakai sequence table/retry unique. |
| Project jasa tidak pernah closing | Tambah `requires_delivery` atau mekanisme closing manual. |
| Supplier cost bocor lewat JSON/HTML | Gunakan whitelist field di service/DTO; test kebocoran. |
| Worktree sales setengah jadi | Selesaikan/commit dulu sebelum batch baru. |

---

## 10. Open decisions (perlu jawaban sebelum eksekusi)

1. **Apakah Sales boleh melihat `unit_cost`/margin quotation?**
   - Default plan: **tidak**.
   - Jika ya: grant `view-cost` ke Sales, tetapi tetap tanpa `view-supplier-cost`.

2. **PO wajib untuk semua project atau hanya project dari Sales?**
   - Default plan: **hanya project dari Sales/Quotation**.
   - Project manual Teknisi/Management tetap boleh dengan alasan.

3. **ACC PDF = pelanggan accepted?**
   - Default plan: ya, generate saat status `accepted`.

4. **Apakah perlu PPN otomatis (rate) atau tetap nominal manual?**
   - Default plan: tetap `tax` nominal; `tax_rate` opsional.

5. **Closing project jasa tanpa barang:**
   - Default plan: tambah `requires_delivery` atau closing manual Admin.

6. **Data kop surat 4 PT:**
   - Perlu nama legal, alamat, telepon, email, NPWP, dan file logo per PT.

7. **Revision history:**
   - Default plan: counter + arsip PDF lama.
   - Jika perlu audit lengkap: buat tabel `proposal_revisions`/clone item.

---

## 11. File yang tidak boleh diubah sembarangan

- `app/Models/Proposal.php` — nama model/tabel tetap `Proposal`/`proposals`.
- Route internal tetap `sales/proposals/...`.
- Jangan rename `proposal_number` menjadi `quotation_number` di tabel proposal.
- Jangan ubah `projects.proposal_id` menjadi nullable tanpa alasan; unique constraint sudah melindungi konversi ganda.
- Jangan hapus `unit_cost`; itu snapshot historis.

---

## 12. Ringkasan satu halaman

1. **Pakai yang sudah ada**: `po_number`, `po_date`, `delivery_deadline`, `unit_cost`, `proposal_id`, `invoice_type`.
2. **Tambah yang belum ada**: `sales_owner_id`, `revision_number`, payment terms terstruktur, `delivery_terms`, item notes, customer billing/finance, permission cost.
3. **Amankan**: Sales tidak melihat harga supplier; PDF tidak menampilkan cost; Admin/Purchasing punya route costing.
4. **Kunci handoff**: PO wajib sebelum convert; satu pintu convert; copy deadline & requires_installation.
5. **Buat PDF setelah accepted** dan simpan ke Documents Customer.
6. **Jangan import dari Sizing/Request Harga**; jasa dari PDF Price Request diketik manual.
7. **Tracker pasca-deal** memakai `proposal->project`, bukan semua project customer.
8. **Uji per batch** dan jangan jalankan seeder role penuh di produksi.
