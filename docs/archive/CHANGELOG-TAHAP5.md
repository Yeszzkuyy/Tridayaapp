# CHANGELOG — Tahap 5: Entitas Data Utama (Customer)

## Ringkasan
Migrasi skema tabel `customers` sebagai master data lintas divisi:
- Penambahan kolom `company` (perusahaan) dan `status` (lead/deal/instalasi/selesai)
- Data migration untuk mapping 5 customer existing ke struktur baru

## Skema Baru `customers`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint PK | |
| `name` | varchar | Nama customer/perusahaan |
| `company` | varchar nullable | Nama perusahaan (jika berbeda dari name) |
| `address` | text nullable | |
| `phone` | varchar nullable | |
| `email` | varchar nullable | |
| `notes` | text nullable | |
| `status` | enum('lead','deal','instalasi','selesai') | Default: 'lead' |
| `deleted_at` | timestamp nullable | Soft deletes |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## File yang Diubah/Ditambahkan

| File | Perubahan |
|------|----------|
| `database/migrations/2026_07_24_000000_add_company_and_status_to_customers_table.php` | **Baru** — tambah kolom company & status |
| `database/seeders/CustomerDataMigrationSeeder.php` | **Baru** — mapping data 5 customer existing ke kolom baru |
| `app/Models/Customer.php` | **Edit** — tambah `company`, `status` ke fillable + casts |
| `app/Http/Controllers/CustomerController.php` | **Edit** — tambah validasi `company` & `status` |
| `resources/views/customers/create.blade.php` | **Edit** — tambah input `company` |
| `resources/views/customers/edit.blade.php` | **Edit** — tambah input `company` |

## Logika Data Migration

Status ditentukan dari relasi project:

| Kondisi Project | Status |
|----------------|--------|
| Tidak punya project | `lead` |
| Ada project (none Done) | `instalasi` |
| Ada project dengan status 'Done' | `selesai` |
| `deal` | Tidak ada mapping otomatis (manual via form) |

Kolom `company` diisi dari nilai `name` yang sudah ada (karena sebelumnya
`name` digunakan sebagai nama perusahaan).

## Data Existing (5 Customer)

| ID | Name | Company | Status | Project Terkait |
|----|------|---------|--------|----------------|
| 1 | PT Telekomunikasi Maju Jaya | PT Telekomunikasi Maju Jaya | instalasi | Proyek Audio Visual (Open) |
| 2 | PT Tes | PT Tes | selesai | Proyek Audio Visual (Done) |
| 3 | PT. Ada nih | PT. Ada nih | lead | — |
| 4 | dummy | dummy | instalasi | Proyek Vicon (Open) |
| 5 | PT Telekomunikasi Maju Jaya | PT Telekomunikasi Maju Jaya | instalasi | 3 proyek (Open) |

---

# Tahap 5b: Modul Sales (Meeting & Follow Up)

## Ringkasan
Penambahan modul Sales: Tracker Meeting & Follow Up, terhubung ke Customer sebagai data induk.

## Skema Baru

### `meetings`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint PK | |
| `customer_id` | bigint FK | customers.id |
| `meeting_date` | date | Tanggal meeting |
| `participants` | varchar nullable | Nama peserta |
| `user_needs` | text nullable | Kebutuhan yang disampaikan user |
| `user_complaints` | text nullable | Keluhan user |
| `existing_system` | text nullable | Sistem existing user |
| `notes` | text nullable | Catatan tambahan |
| `created_by` | bigint FK nullable | users.id |
| `deleted_at` | timestamp nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `follow_ups`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint PK | |
| `customer_id` | bigint FK | customers.id |
| `meeting_id` | bigint FK nullable | meetings.id |
| `description` | text | Deskripsi follow up |
| `follow_up_date` | date nullable | Tanggal follow up |
| `created_by` | bigint FK nullable | users.id |
| `deleted_at` | timestamp nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## File yang Diubah/Ditambahkan

| File | Perubahan |
|------|----------|
| `database/migrations/2026_07_28_000001_create_meetings_table.php` | **Baru** — tabel meetings |
| `database/migrations/2026_07_28_000002_create_follow_ups_table.php` | **Baru** — tabel follow_ups |
| `app/Models/Meeting.php` | **Baru** — model Meeting |
| `app/Models/FollowUp.php` | **Baru** — model FollowUp |
| `app/Models/Customer.php` | **Edit** — tambah relasi `meetings()` & `followUps()` |
| `app/Services/SalesService.php` | **Baru** — service untuk Meeting & FollowUp |
| `app/Http/Controllers/MeetingController.php` | **Baru** — CRUD Meeting |
| `app/Http/Controllers/FollowUpController.php` | **Baru** — CRUD Follow Up |
| `routes/web.php` | **Edit** — tambah route prefix `sales/` (manage-sales & view-sales) |
| `resources/views/layouts/partials/sidebar.blade.php` | **Edit** — tambah menu Sales (Tracker Meeting, Follow Up) |
| `resources/views/customers/show.blade.php` | **Edit** — tambah tab Meetings & Follow Up |
| `resources/views/sales/meetings/index.blade.php` | **Baru** — list meeting dengan search & filter |
| `resources/views/sales/meetings/create.blade.php` | **Baru** — form catat meeting |
| `resources/views/sales/meetings/show.blade.php` | **Baru** — detail meeting + daftar follow up |
| `resources/views/sales/meetings/edit.blade.php` | **Baru** — form edit meeting |
| `resources/views/sales/follow-ups/index.blade.php` | **Baru** — list follow up |
| `resources/views/sales/follow-ups/create.blade.php` | **Baru** — form tambah follow up |
| `resources/views/sales/follow-ups/show.blade.php` | **Baru** — detail follow up |
| `resources/views/sales/follow-ups/edit.blade.php` | **Baru** — form edit follow up |

## Permission

| Permission | Route | Siapa yang dapat |
|-----------|-------|-----------------|
| `manage-sales` | CRUD Meeting & Follow Up | Role `sales` |
| `view-sales` | List & Detail Meeting & Follow Up | Role `teknisi`, `admin`, `marketing`, `manager`, `super-admin` |

Follow Up & Meeting tampil di halaman Customer Detail untuk role dengan `view-sales` (transparansi lintas divisi sesuai PRD).

---

# Tahap 5c: Modul Admin (Invoice, PO, Payment)

## Ringkasan
Penambahan modul Admin: Invoice, Purchase Order, dan Payment — semua terhubung ke Customer sebagai data induk.

## Skema Baru

### `invoices`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint PK | |
| `invoice_number` | varchar unique | Format INV-{tahun}-{seq} |
| `customer_id` | bigint FK | customers.id |
| `project_id` | bigint FK nullable | projects.id |
| `amount` | decimal(15,2) | Nilai invoice |
| `status` | enum('unpaid','paid','cancelled') | Default 'unpaid' |
| `issue_date` | date | Tanggal terbit |
| `due_date` | date nullable | Jatuh tempo |
| `notes` | text nullable | |
| `created_by` | bigint FK nullable | users.id |
| `deleted_at` | timestamp nullable | |
| `created_at` / `updated_at` | timestamp | |

### `purchase_orders`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint PK | |
| `po_number` | varchar unique | Format PO-{tahun}-{seq} |
| `customer_id` | bigint FK | customers.id |
| `project_id` | bigint FK nullable | projects.id |
| `items` | text | Deskripsi barang/jasa |
| `amount` | decimal(15,2) | Nilai PO |
| `status` | enum('draft','diproses','selesai','dibatalkan') | Default 'draft' |
| `issue_date` | date | Tanggal terbit |
| `notes` | text nullable | |
| `created_by` | bigint FK nullable | users.id |
| `deleted_at` | timestamp nullable | |
| `created_at` / `updated_at` | timestamp | |

### `payments`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint PK | |
| `invoice_id` | bigint FK | invoices.id |
| `amount` | decimal(15,2) | Nilai pembayaran |
| `payment_date` | date | Tanggal bayar |
| `payment_method` | varchar nullable | Transfer Bank, Tunai, dll |
| `proof_file` | string nullable | Path file bukti transfer |
| `notes` | text nullable | |
| `created_by` | bigint FK nullable | users.id |
| `deleted_at` | timestamp nullable | |
| `created_at` / `updated_at` | timestamp | |

## File yang Diubah/Ditambahkan

| File | Perubahan |
|------|----------|
| `database/migrations/2026_07_28_000010_create_invoices_table.php` | **Baru** |
| `database/migrations/2026_07_28_000011_create_purchase_orders_table.php` | **Baru** |
| `database/migrations/2026_07_28_000012_create_payments_table.php` | **Baru** |
| `app/Models/Invoice.php` | **Baru** |
| `app/Models/PurchaseOrder.php` | **Baru** |
| `app/Models/Payment.php` | **Baru** |
| `app/Models/Customer.php` | **Edit** — tambah relasi `invoices()` & `purchaseOrders()` |
| `app/Services/AdminService.php` | **Baru** — generate nomor, CRUD, auto-update status invoice |
| `app/Http/Controllers/AdminController.php` | **Baru** — semua method Invoice/PO/Payment |
| `routes/web.php` | **Edit** — tambah route prefix `admin/` (manage-admin & view-admin) |
| `resources/views/layouts/partials/sidebar.blade.php` | **Edit** — tambah menu Admin (Invoice, PO, Payment) |
| `resources/views/customers/show.blade.php` | **Edit** — tambah tab Invoice, PO, Payment |
| `resources/views/admin/invoices/index.blade.php` | **Baru** — 3 tab (Invoice/PO/Payment) dalam satu halaman |
| `resources/views/admin/invoices/create.blade.php` | **Baru** |
| `resources/views/admin/invoices/edit.blade.php` | **Baru** |
| `resources/views/admin/invoices/show.blade.php` | **Baru** — detail + riwayat pembayaran |
| `resources/views/admin/pos/create.blade.php` | **Baru** |
| `resources/views/admin/pos/edit.blade.php` | **Baru** |
| `resources/views/admin/pos/show.blade.php` | **Baru** |
| `resources/views/admin/payments/create.blade.php` | **Baru** — form + upload bukti |
| `resources/views/admin/payments/show.blade.php` | **Baru** |
| `resources/views/admin/payments/index.blade.php` | **Baru** — tab payments saja |

## Permission

| Permission | Route | Siapa yang dapat |
|-----------|-------|-----------------|
| `manage-admin` | CRUD Invoice, PO, Payment | Role `admin` |
| `view-admin` | List & Detail Invoice, PO, Payment | Role `marketing`, `sales`, `teknisi`, `manager`, `super-admin` |

Payment otomatis mengubah status invoice menjadi `paid` saat dicatat. Jika payment dihapus dan tidak ada payment lain, invoice kembali ke `unpaid`.

Invoice, PO, dan Payment tampil di halaman Customer Detail untuk role dengan `view-admin`.
