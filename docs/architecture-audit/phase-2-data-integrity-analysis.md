# Phase 2 - Data Integrity Analysis

**Audit:** Phase 2 - Database Architecture
**Repository:** `/var/www/3dyapp`
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada query yang mengubah data.

## 1. Ringkasan Integritas

| Area | Kondisi | Status |
|---|---|---|
| Primary key | 87 dari 87 tabel memiliki PK | Baik |
| Foreign key | 182 FK tersedia | Cukup, tetapi tidak merata |
| FK tanpa index | 111 kolom FK | Risiko performa |
| Unique constraint | 40 constraint non-PK | Sebagian business key belum unik |
| Check constraint | 5 constraint | Sangat sedikit untuk 28 kolom status |
| Soft delete | 34 tabel | `deleted_at` tidak di-index di semua tabel |
| Audit trail | 3 activity table terpisah | Tidak ada audit global |
| Relasi polymorphic | 6 pola utama | Tidak dapat dijaga FK |
| JSON | 17 kolom | Sebagian menyembunyikan relasi penting |

## 2. Constraint dan Referential Integrity

### 2.1 FK tersedia, tetapi tidak merata

182 FK sudah mencakup relasi utama: customer-lead-project, proposal-item, supplier PO-receipt, invoice-payment, dan project-document. Ini lebih baik dari aplikasi yang hanya mengandalkan Eloquent.

Namun ada relasi bisnis yang tidak memiliki FK:

- `technical_request_supports.technical_request_id` dan `user_id`.
- `project_documents.deleted_by`.
- `document_activities.document_id`, `customer_id`, dan `project_id`.
- `sessions.user_id`.
- `agent_conversation_messages.conversation_id` dan `agent_conversations.user_id`.
- `notifications.notifiable_id`, `push_subscriptions.subscribable_id`, `model_has_roles.model_id`, `model_has_permissions.model_id` (polymorphic, wajar tanpa FK).

`technical_request_supports` secara eksplisit menyebut alasan di migration: akun database di server dianggap tidak memegang izin `REFERENCES`, sehingga relasi hanya dijaga aplikasi. Ini adalah keputusan sadar, bukan kelalaian, tetapi tetap menjadi technical debt karena integritas bergantung pada kode.

**Bukti:** `database/migrations/2026_11_11_091232_create_technical_request_supports_table.php:20-27`; `app/Models/TechnicalRequest.php:83-88`.

### 2.2 Delete rules tidak konsisten dengan lifecycle bisnis

| Rule | Jumlah | Dampak |
|---|---:|---|
| `CASCADE` | 66 | Menghapus parent menghapus child, termasuk data historis |
| `SET NULL` | 110 | Child tetap ada tanpa parent, bisa menjadi orphan logis |
| `RESTRICT` | 6 | Menahan penghapusan parent jika child ada |

**CASCADE yang paling berbahaya:**

1. `projects.work_type_id -> work_types.id` CASCADE.
   - `WorkTypeController@destroy` melakukan hard delete.
   - Menghapus Work Type akan menghapus project, task, support, activity, dan dokumen terkait di level database.
   - Tidak ada guard “work type masih dipakai”.
   - Ini adalah temuan DB-001, severity Critical.

2. `invoices.customer_id -> customers.id` CASCADE dan `payments.invoice_id -> invoices.id` CASCADE.
   - `TrashController@destroyCustomer` melakukan `forceDelete()`.
   - Force delete customer akan menghapus invoice, payment, lead, proposal, dan project.
   - Tidak ada guard retensi finansial.
   - Ini adalah temuan DB-002, severity High.

3. `project_documents.uploaded_by -> users.id` CASCADE.
   - Hard delete user akan menghapus dokumen yang ia unggah.
   - User memakai soft delete, jadi ini laten, tetapi tetap risiko jika ada force delete.

4. `project_activities.user_id -> users.id` CASCADE.
   - Hard delete user menghapus riwayat aktivitas project.

5. `product_price_logs.updated_by -> users.id` CASCADE.
   - Hard delete user menghapus riwayat harga.

**Bukti CASCADE:**
- `database/migrations/2026_06_12_090951_create_projects_table.php:26-28`.
- `database/migrations/2026_07_28_000010_create_invoices_table.php:14`.
- `database/migrations/2026_07_28_000012_create_payments_table.php:13`.
- `app/Http/Controllers/WorkTypeController.php:52-58`.
- `app/Http/Controllers/TrashController.php:131-143`.

### 2.3 Soft delete tidak diikuti FK

34 tabel memakai `deleted_at`, tetapi FK database tidak memahami soft delete. Contoh:

- `projects.customer_id` CASCADE hanya berjalan saat customer benar-benar dihapus, bukan saat soft delete.
- `project_statuses` soft delete tidak mengosongkan `projects.project_status_id`; project tetap menunjuk status yang sudah di-trash, dan relasi `Project::status` menjadi null.
- `project_supports` soft delete tidak difilter pada relasi `Project::supportUsers`, sehingga support yang sudah dihapus masih bisa muncul.
- `suppliers` soft delete tetap terhubung ke `product_suppliers` dan `product_price_logs`; relasi produk dapat menampilkan supplier yang sudah tidak aktif jika scope tidak diterapkan.

## 3. Uniqueness dan Business Key

### 3.1 Yang sudah baik

- `proposals.proposal_number` unique.
- `invoices.invoice_number` unique.
- `supplier_purchase_orders.po_number` unique.
- `supplier_invoices` unique per supplier + supplier invoice number.
- `goods_receipts.receipt_number` dan `goods_deliveries.delivery_number` unique.
- `projects.project_code` dan `projects.bast_number` unique (nullable).
- `projects.proposal_id` unique.
- `sales_targets` unique per user/periode.
- `product_suppliers` unique per product/supplier.
- `customer_contacts` memiliki partial unique index satu primary per customer.
- `technician_schedules` memiliki partial unique index event Google aktif.

### 3.2 Yang belum unik

| Business key | Kondisi | Risiko |
|---|---|---|
| `products.sku` | Hanya index, bukan unique | Dua produk dengan kode internal sama |
| `customers.email` / `customers.name` | Tidak unique | Customer ganda |
| `work_types.name` | Tidak unique | Work type ganda; cascade delete berbahaya |
| `project_statuses.name` | Tidak unique | Workflow name lookup bisa memilih status yang salah |
| `project_statuses.is_default` | Tidak ada partial unique | Dua status default |
| `proposals.po_number` | Tidak unique | Nomor PO customer ganda |
| `account_managers.name` | Tidak unique | Master ganda |
| `goods_receipt_items` / `goods_delivery_items` per PO item | Tidak unique | Baris ganda bisa dihitung dua kali |
| `project_supports(project_id,user_id)` | Tidak unique | Support engineer ganda |
| `supplier_purchase_order_items` per proposal item/product | Tidak unique | Pemecahan paket boleh, tetapi duplikasi baris tidak dicegah |

**Bukti:** `pg_indexes` dan `information_schema.table_constraints`; migrasi `2026_10_07_110501_create_products_table.php:16` (sku hanya index).

## 4. Status dan Check Constraint

Ada 28 kolom bernama `status`, tetapi hanya 2 status bisnis yang memiliki check constraint:

- `customers.status`.
- `project_tasks.status`.
- `purchase_orders.status` (legacy).
- `partners.type`.
- `whatsapp_messages.direction`.

Status berikut tidak memiliki check constraint:

`leads.status`, `proposals.status`, `invoices.status`, `estimator_requests.status`, `estimator_request_items.status`, `price_requests.status`, `goods_receipts.status`, `goods_deliveries.status`, `supplier_invoices.status`, `supplier_purchase_orders.status`, `technical_requests.status`, `lead_tasks.status`, `follow_ups.status`, `surveys.status`, `sizing_projects.status`, `instalasis.status`, `sales_schedules.status`, `pocs.status`, `products.status`, `product_sources.status`, `request_hargas.status`, `technician_schedules.status`, `whatsapp_messages.status`.

**Dampak:** nilai status invalid bisa masuk melalui query langsung, console, import, atau bug controller. Aplikasi tetap memakai konstanta model untuk validasi form, tetapi database tidak menjadi penjaga terakhir.

**Bukti:** `pg_constraint` hanya memiliki 5 check constraint; daftar status dari `information_schema.columns`.

## 5. Audit Trail dan Data History

### 5.1 Yang tersedia

- `lead_activities` menyimpan `action` dan `changes` JSON untuk lead.
- `project_activities` menyimpan judul/deskripsi aktivitas project.
- `document_activities` menyimpan aksi dokumen.
- `product_price_logs` menyimpan riwayat harga produk.
- `proposal_versions` menyimpan snapshot item dan total.
- `created_by`, `received_by`, `uploaded_by`, `generated_by` tersedia di banyak tabel.
- `created_at`, `updated_at`, dan `deleted_at` tersedia di mayoritas tabel.

### 5.2 Yang tidak tersedia

- Tidak ada tabel audit global.
- Admin Panel “Audit Log” mengembalikan paginator kosong; tidak ada query ke tabel audit.
- Tidak ada riwayat status invoice/payment/PO/receipt/delivery dalam tabel terpisah.
- Tidak ada riwayat perubahan harga jual default selain `product_price_logs` (yang mencatat harga, bukan semua field produk).
- Tidak ada riwayat perubahan role/permission user.
- Tidak ada riwayat perubahan `customers.pt_group`, `customers.status`, atau ownership customer.
- `project_activities` hanya berisi teks, bukan old/new terstruktur.
- `proposal_versions` tidak menyimpan header quotation, payment terms, PO, atau file PDF per versi.

**Bukti:** `app/Http/Controllers/AdminPanelController.php:259-263`; schema tidak memiliki tabel `audit_logs`; `project_activities` columns; `proposal_versions` columns.

## 6. Index dan Performa

### 6.1 FK tanpa index

111 dari 182 kolom FK tidak memiliki index yang mencakup kolom tersebut. PostgreSQL tidak otomatis membuat index untuk FK. Contoh penting:

- `invoices.customer_id` (query invoice per customer).
- `projects.customer_id` (project per customer).
- `leads.customer_id` (lead per customer).
- `payments.created_by`.
- `goods_receipts.supplier_purchase_order_id`.
- `supplier_invoices.supplier_purchase_order_id`.
- `project_documents.document_category_id`.
- `project_tasks.project_id`.
- `project_supports.project_id`.

**Dampak:** query daftar per customer/project/PO dapat melakukan sequential scan saat data tumbuh. Pada database kecil belum terasa; pada data produksi bisa menjadi masalah.

### 6.2 Soft delete tanpa index

34 tabel memiliki `deleted_at`, tetapi tidak ada index yang mencakup `deleted_at`. Query default Eloquent selalu menambahkan `deleted_at IS NULL`; tanpa index, filter ini tidak membantu dan bisa memindai seluruh tabel.

**Catatan:** index tunggal `deleted_at` tidak selalu efektif jika selectivity rendah. Yang lebih penting adalah composite index sesuai pola query, misalnya `(customer_id, deleted_at, created_at)`.

### 6.3 Index yang sudah baik

- `leads(assigned_to, status)`, `leads(status, incoming_date)`.
- `proposals(lead_id, status)`.
- `invoices(status, due_date)` dan `(status, issue_date)`.
- `price_requests(assigned_to, status)` dan `(customer_id, status)`.
- `supplier_purchase_orders(status, expected_delivery_date)`.
- `product_price_logs(product_id, created_at)`.
- `project_documents(documentable_type, documentable_id)`.
- `project_documents(document_group_id, version)`.
- Full-text GIN index pada `knowledge_chunks` dan `knowledge_documents`.

## 7. Denormalisasi dan Derived Data

Banyak nilai bisnis disimpan sebagai cache/denormalisasi:

| Derived value | Sumber sebenarnya | Risiko |
|---|---|---|
| `invoices.paid_amount`, `status`, `paid_at` | `payments` | Bisa drift jika payment diubah di luar service |
| `supplier_invoices.paid_amount`, `status` | `supplier_payments` | Sama |
| `supplier_purchase_orders.status`, `quantity_received`, `quantity_delivered` | receipt/delivery | Bisa drift |
| `supplier_purchase_order_items.quantity_received`, `quantity_delivered` | receipt/delivery | Bisa drift |
| `proposals.subtotal`, `tax`, `grand_total`, `total_cost`, `gross_profit`, `gross_margin` | proposal items | Bisa drift jika item diubah langsung |
| `proposal_items.subtotal` | quantity x price - discount | Bisa drift |
| `invoices.subtotal`, `amount` | invoice items | Bisa drift |
| `projects.progress`, `workflow_state`, `workflow_synced_at`, `commercial_closed_at`, `bast_ready_at` | workflow/delivery/BAST/payment | Bisa drift |
| `customer_contacts` cache `customers.contact_person` | customer contacts | Bisa drift |
| `products.cost_price`, `product_suppliers.last_cost_price` | price logs | Bisa drift |
| `projects.support_technicians`, `pic_engineer` | pivot/FK user | Bisa drift |

**Bukti:** `AdminService.php:456-500`; `ProcurementService.php:54-118`; `Proposal::recalculate`; `ProjectWorkflowService`; `CustomerContactService.php:187-204`.

**Assessment:** Denormalisasi ini sebagian disengaja untuk performa dan snapshot histori. Risiko muncul jika ada jalur tulis yang tidak memanggil service rekalkulasi. Tidak ada trigger database yang menjaga konsistensi.

## 8. JSON dan Relasi Tersembunyi

17 kolom JSON; yang paling berdampak pada integritas:

- `request_hargas.items` dan `attachments`: item/attachment tidak memiliki tabel relasional.
- `proposal_versions.snapshot`: snapshot item, tidak bisa di-join.
- `projects.workflow_state`: status langkah, tidak bisa di-query per langkah tanpa parsing JSON.
- `lead_activities.changes`: old/new dalam JSON.
- `goods_receipt_items.serial_numbers` dan `goods_delivery_items.serial_numbers`: serial number tidak bisa di-unique-kan di database.
- `surveys.documentation`, `sizing_projects.attachments`, `instalasis.checklist/documentation/attachments`: metadata teknis dalam JSON.
- `users.preferences`: preferensi user, wajar.
- `document_activities.meta`, `price_request_comments.meta`: metadata bebas.

**Assessment:** JSON pada snapshot/version dan preferensi adalah pilihan yang wajar. JSON pada item pengadaan dan serial number membatasi audit, pencarian, dan uniqueness.

## 9. Klasifikasi Risiko

### Confirmed structural risk

- DB-001 Work Type cascade.
- DB-002 Customer force delete cascade.
- DB-003 Audit log absent.
- DB-004 Project status name dependency.
- DB-005 Status check gap.
- DB-006 Missing FKs.
- DB-007 FK/soft-delete index gap.
- DB-008 Derived cache drift.
- DB-009 Customer PO duplication.
- DB-010 Supplier master duplication.
- DB-011 Price request table duplication.
- DB-012 Invoice item traceability.
- DB-013 Business key uniqueness.
- DB-014 Serial number JSON.
- DB-015 Project support pivot.
- DB-017 Revision snapshot.
- DB-018 Technician assignment duplication.
- DB-021 users.role legacy.
- DB-022 Project document duplicate category.
- DB-023 Wide lifecycle tables.
- DB-024 PT string no company table.

### Potential risk

- DB-016 WhatsApp-created customer ownership/PT.
- DB-019 Number generation race.
- DB-020 Customer lifecycle status drift.

### Business decision required

- DB-009 PO uniqueness scope.
- DB-010 Supplier master source.
- DB-011 Price request consolidation.
- DB-013 Business key scope.
- DB-024 Company master.
- DB-020 Customer lifecycle.

## 10. Batasan

- Tidak ada pemeriksaan data aktual untuk membuktikan duplikat atau orphan.
- Row estimate PostgreSQL tidak digunakan sebagai bukti karena bisa basi.
- Index yang tidak ada dinilai dari katalog, bukan dari query plan.
- Tingkat keparahan performa bergantung pada volume data produksi yang tidak diaudit.
- Relasi yang hanya ada di Eloquent tidak diuji dengan request nyata.
