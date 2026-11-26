# Phase 2 - Database Findings

**Audit:** Phase 2 - Database Architecture
**Repository:** `/var/www/3dyapp`
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada kode, migration, schema, atau data yang diubah.

## 1. Executive Summary

Database 3DY App sudah memiliki fondasi yang cukup lengkap untuk CRM: customer, lead, DAR, quotation, harga modal, procurement parsial, project, BAST, invoice, dan payment. Semua tabel memiliki primary key, 182 foreign key tersedia, dan sebagian business key utama sudah unique.

Masalah utama bukan kekurangan tabel, tetapi **integritas yang belum konsisten**:

- Satu foreign key `CASCADE` dapat menghapus seluruh project saat Work Type dihapus.
- Force delete customer dapat menghapus invoice dan payment.
- Audit log global tidak tersedia; menu Audit Log di Admin Panel kosong.
- Workflow project bergantung pada nama status yang dapat diubah atau di-soft-delete.
- 25+ kolom status tidak memiliki check constraint.
- 111 kolom FK tidak memiliki index; 34 tabel soft delete tidak memiliki index `deleted_at`.
- Beberapa data bisnis diduplikasi: customer PO, supplier master, price request, technician assignment.
- Sebagian derived data hanya dijaga application logic, tanpa trigger/reconciliation database.

## 2. Severity Summary

| Severity | Jumlah | Finding |
|---|---:|---|
| Critical | 1 | DB-001 |
| High | 3 | DB-002, DB-003, DB-004 |
| Medium | 17 | DB-005, DB-006, DB-007, DB-008, DB-009, DB-010, DB-011, DB-012, DB-013, DB-014, DB-015, DB-016, DB-017, DB-018, DB-019, DB-021, DB-024 |
| Low | 3 | DB-020, DB-022, DB-023 |
| **Total** | **24** | |

## 3. Domain Summary

| Domain | Finding |
|---|---|
| Data loss / lifecycle | DB-001, DB-002, DB-020 |
| Audit dan history | DB-003, DB-008, DB-017 |
| Constraint dan uniqueness | DB-005, DB-006, DB-009, DB-013, DB-015, DB-022 |
| Relasi dan master data | DB-010, DB-011, DB-014, DB-016, DB-018, DB-024 |
| Performance | DB-007, DB-019 |
| Billing traceability | DB-012 |
| Model/schema drift | DB-021, DB-023 |

---

## DB-001 - Work Type CASCADE menghapus project

**Finding ID:** DB-001
**Title:** `projects.work_type_id` memakai `ON DELETE CASCADE`
**Severity:** Critical
**Domain:** Project / master data
**Related:** BR-020, GAP-011, DEC-005
**Confidence:** High

**Evidence:**
- `database/migrations/2026_06_12_090951_create_projects_table.php:26-28` membuat `work_type_id` dengan `cascadeOnDelete()`.
- `app/Http/Controllers/WorkTypeController.php:52-54` melakukan hard delete `$workType->delete()`.
- `app/Models/WorkType.php` tidak memakai `SoftDeletes`.
- `work_types.name` tidak unique.

**Current Implementation:**
Work Type adalah master data yang dapat dihapus dari Admin Panel. Project memiliki `work_type_id` wajib dengan FK CASCADE. Tidak ada pengecekan jumlah project sebelum delete.

**Expected Business Requirement:**
Work Type adalah referensi master. Menghapus Work Type tidak boleh menghapus project historis. Project harus tetap ada, atau delete harus ditolak.

**Gap / Risk:**
Menghapus satu Work Type dapat menghapus semua project yang memakainya di level database, termasuk task, support, activity, dan document. Karena delete terjadi di database, `ProjectObserver` tidak berjalan, sehingga cleanup file dan pencatatan aktivitas juga tidak terjadi.

**Business Impact:**
Kehilangan data project, riwayat pekerjaan, dokumen, dan dasar BAST/invoice secara permanen. Tidak ada konfirmasi atau audit yang memadai.

**Technical Impact:**
Hard delete project dari FK cascade, orphan file di storage, hilangnya activity log, dan kemungkinan invoice/proposal kehilangan relasi project (`SET NULL`).

**Recommendation:**
Ubah FK menjadi `RESTRICT` atau `SET NULL`, atau ubah Work Type menjadi soft delete. Blokir delete jika `projects` masih memakai Work Type tersebut. Tambahkan test yang memastikan delete Work Type gagal saat dipakai.

---

## DB-002 - Force delete customer menghapus data finansial

**Finding ID:** DB-002
**Title:** Customer force delete cascade ke invoice, payment, lead, dan project
**Severity:** High
**Domain:** Customer / finance
**Related:** BR-019, BR-020, GAP-013, DEC-012
**Confidence:** High

**Evidence:**
- `invoices.customer_id -> customers.id` CASCADE (`2026_07_28_000010_create_invoices_table.php:14`).
- `payments.invoice_id -> invoices.id` CASCADE (`2026_07_28_000012_create_payments_table.php:13`).
- `leads.customer_id -> customers.id` CASCADE.
- `projects.customer_id -> customers.id` CASCADE.
- `TrashController.php:131-139` memanggil `$customer->forceDelete()`.
- `CustomerObserver.php:9-18` hanya meng-soft-delete project, contact, dan document; invoice/payment tidak ikut di-soft-delete.
- `CustomerPolicy.php:47-69` mengizinkan owner melakukan force delete.

**Current Implementation:**
Customer yang sudah dipindahkan ke Trash dapat dihapus permanen oleh pemiliknya atau super-admin. FK CASCADE menghapus seluruh child record, termasuk invoice dan payment.

**Expected Business Requirement:**
Data finansial dan dokumen transaksi harus memiliki retensi/arsip. Menghapus customer tidak boleh menghapus invoice/payment historis tanpa prosedur khusus.

**Gap / Risk:**
Satu aksi force delete dapat menghapus riwayat penjualan, piutang, pembayaran, dan bukti finansial. Tidak ada guard “customer masih punya invoice/payment” dan tidak ada arsip/anonymization.

**Business Impact:**
Laporan penjualan, target, piutang, dan audit finansial dapat berubah atau kehilangan data. Risiko kepatuhan dan sengketa pembayaran.

**Technical Impact:**
Hard delete berantai, file bukti pembayaran di storage menjadi orphan, dan tidak ada jejak audit karena baris sudah hilang.

**Recommendation:**
Blokir force delete jika customer memiliki invoice/payment, atau ubah relasi finansial menjadi `RESTRICT`/`SET NULL` dengan arsip. Pisahkan “hapus customer” dari “hapus data finansial”. Tambahkan test force delete dengan invoice.

---

## DB-003 - Audit log global tidak tersedia

**Finding ID:** DB-003
**Title:** Menu Audit Log kosong; tidak ada tabel audit global
**Severity:** High
**Domain:** Audit / governance
**Related:** BR-002, BR-019, BR-020, GAP-013
**Confidence:** High

**Evidence:**
- `app/Http/Controllers/AdminPanelController.php:259-263` membuat `LengthAwarePaginator` kosong.
- Tidak ada tabel `audit_logs`, `activity_log`, atau sejenis di schema.
- Yang tersedia hanya `lead_activities`, `project_activities`, `document_activities`, dan `product_price_logs`.
- Invoice, payment, purchase order, goods receipt, delivery, BAST, role, dan permission tidak memiliki tabel history.

**Current Implementation:**
Audit Log di Admin Panel hanya view kosong. Beberapa modul menulis activity sendiri, tetapi tidak ada sumber audit terpadu.

**Expected Business Requirement:**
Perubahan penting harus dapat ditelusuri: siapa, kapan, dari nilai apa, menjadi nilai apa. Business Discovery meminta audit trail dan monitoring lintas divisi.

**Gap / Risk:**
Perubahan status invoice/payment/PO/receipt/delivery/BAST tidak dapat direkonstruksi. Perubahan role/permission tidak tercatat. Audit investigasi hanya mengandalkan `updated_at` dan activity parsial.

**Business Impact:**
Sulit membuktikan siapa yang mengubah nominal, status, atau dokumen saat terjadi sengketa. Management tidak dapat mengaudit kepatuhan proses.

**Technical Impact:**
Tidak ada sumber data untuk audit UI, laporan kepatuhan, atau investigasi. Menambah audit nanti membutuhkan instrumentasi ulang.

**Recommendation:**
Tambahkan tabel audit global (`audit_logs`) dengan subject polymorphic, actor, event, old_values/new_values JSON, dan timestamp. Instrumentasi minimal untuk invoice, payment, PO, receipt, delivery, BAST, role, dan permission. Jangan mengandalkan activity table per modul sebagai audit lengkap.

---

## DB-004 - Workflow project bergantung pada nama status yang mutable

**Finding ID:** DB-004
**Title:** `ProjectWorkflowService` mencari status berdasarkan nama; status dapat diubah/di-soft-delete
**Severity:** High
**Domain:** Project / status master
**Related:** BR-020, GAP-011, GAP-013, DEC-005, DEC-007
**Confidence:** High

**Evidence:**
- `ProjectWorkflowService.php:29` memakai daftar nama `Hold`, `Cancelled`, `Warranty`, `Maintenance`.
- `ProjectWorkflowService.php:280, 284, 290` memanggil `statusId('Done')` dan `statusId('On Progress')`.
- `ProjectWorkflowService.php:305-307` mencari `ProjectStatus::where('name', $name)`.
- `ProjectStatus` hanya memiliki `name`, `color`, `is_default`, `sort_order`, `is_active`; tidak ada `code`/`slug` stabil.
- `project_statuses.name` tidak unique; `is_default` tidak memiliki partial unique.
- `ProjectStatusController.php:66-76` mengizinkan soft delete status tanpa cek apakah masih dipakai project.

**Current Implementation:**
Workflow otomatis mengandalkan nama status yang dapat diedit admin. Status yang di-soft-delete tetap menjadi `project_status_id` di project, tetapi relasi `Project::status` menjadi null.

**Expected Business Requirement:**
Status sistem harus stabil dan tidak dapat dihapus/di-rename tanpa migrasi. Automation harus memakai kode status, bukan label.

**Gap / Risk:**
Rename “Done” menjadi “Selesai” atau delete status dapat mematikan closing otomatis. Dua status dengan nama sama dapat membuat `value('id')` memilih status yang salah.

**Business Impact:**
Project tidak otomatis menjadi Done/On Progress; laporan closing dan KPI dapat berbeda.

**Technical Impact:**
Silent failure di workflow, status null, cache status tidak konsisten, dan sulit dites karena bergantung pada data master.

**Recommendation:**
Tambahkan kolom `code` unik pada `project_statuses` dan gunakan kode di workflow. Tandai status sistem sebagai `is_system` dan larang rename/delete. Tambahkan unique partial untuk `is_default = true`.

---

## DB-005 - Status dan tipe bisnis tidak memiliki check constraint

**Finding ID:** DB-005
**Title:** 28 kolom status, hanya 5 check constraint
**Severity:** Medium
**Domain:** Data integrity
**Related:** BR-001, BR-002, BR-006, BR-019, BR-020, DEC-002, DEC-007
**Confidence:** High

**Evidence:**
- `leads.status`, `proposals.status`, `invoices.status`, `goods_receipts.status`, `goods_deliveries.status`, `supplier_invoices.status`, `supplier_purchase_orders.status`, `price_requests.status`, `technical_requests.status`, `lead_tasks.status`, `follow_ups.status`, `surveys.status`, `sizing_projects.status`, `instalasis.status`, `sales_schedules.status`, `pocs.status`, `products.status`, `product_sources.status`, `request_hargas.status`, `technician_schedules.status`, dan status lain bertipe `varchar` tanpa check.
- Hanya `customers.status`, `project_tasks.status`, `purchase_orders.status`, `partners.type`, dan `whatsapp_messages.direction` yang memiliki check.
- Nilai valid hanya ada di konstanta model/controller.

**Current Implementation:**
Validasi status dilakukan di form/controller. Database menerima nilai apa pun.

**Expected Business Requirement:**
Status bisnis harus konsisten dan tidak dapat berisi nilai di luar daftar resmi.

**Gap / Risk:**
Import, console command, bug controller, atau query manual dapat menulis status invalid. Laporan dan workflow kemudian mengabaikan record tersebut.

**Business Impact:**
Data pipeline, invoice, atau project bisa “hilang” dari filter karena status tidak dikenal.

**Technical Impact:**
Silent data corruption, sulit dibersihkan, dan test hanya mencakup jalur aplikasi.

**Recommendation:**
Tambahkan check constraint atau tabel lookup untuk status yang sudah final. Jangan menambahkan constraint untuk status yang masih terbuka keputusannya sebelum daftar final disepakati. Minimal untuk `leads`, `proposals`, `invoices`, `goods_receipts`, `goods_deliveries`, `supplier_invoices`, dan `supplier_purchase_orders`.

---

## DB-006 - Foreign key hilang pada relasi bisnis

**Finding ID:** DB-006
**Title:** `technical_request_supports` dan `project_documents.deleted_by` tidak memiliki FK
**Severity:** Medium
**Domain:** Referential integrity
**Related:** BR-003, BR-019, GAP-011
**Confidence:** High

**Evidence:**
- `technical_request_supports.technical_request_id` dan `user_id` tidak memiliki FK.
- Migration comment menyebut akun database tidak memegang izin `REFERENCES` (`2026_11_11_091232_create_technical_request_supports_table.php:20-27`).
- `project_documents.deleted_by` hanya `unsignedBigInteger` + index (`2026_10_23_000001_add_deleted_by_to_project_documents_table.php:17`), tanpa FK.
- `ProjectDocument.php:140` mendefinisikan relasi `deleter`, tetapi database tidak menjaganya.

**Current Implementation:**
Relasi dijaga oleh `belongsToMany`/`belongsTo` dan soft delete aplikasi.

**Expected Business Requirement:**
Relasi user/request harus valid; delete user/request harus membersihkan atau menahan child.

**Gap / Risk:**
Support engineer dan deleted_by dapat menjadi ID yatim. `technical_request_supports` tidak ikut terhapus saat request/user dihapus.

**Business Impact:**
Tim support bisa menampilkan user yang sudah tidak ada, atau audit dokumen kehilangan pelaku.

**Technical Impact:**
Orphan row, query join gagal, dan cleanup manual.

**Recommendation:**
Tambahkan FK setelah memastikan user database produksi memiliki izin `REFERENCES`. Gunakan `CASCADE` untuk pivot request-user dan `SET NULL` untuk `deleted_by`. Jika izin belum memungkinkan, tambahkan cleanup di service dan test orphan.

---

## DB-007 - 111 kolom FK tanpa index dan 34 tabel soft delete tanpa index

**Finding ID:** DB-007
**Title:** Index tidak mengikuti pola query FK/soft delete
**Severity:** Medium
**Domain:** Performance
**Related:** GAP-004, GAP-011
**Confidence:** High

**Evidence:**
- 111 dari 182 kolom FK tidak tercakup index apa pun.
- Contoh: `invoices.customer_id`, `projects.customer_id`, `leads.customer_id`, `goods_receipts.supplier_purchase_order_id`, `project_documents.document_category_id`.
- 34 tabel memiliki `deleted_at`, tetapi tidak ada index yang mencakup kolom itu.
- Query Eloquent default menambahkan `deleted_at is null`.
- Index yang sudah baik: `leads(assigned_to,status)`, `invoices(status,due_date)`, `proposals(lead_id,status)`, `product_price_logs(product_id,created_at)`.

**Current Implementation:**
Sebagian query mengandalkan index komposit yang ada; banyak query per customer/project/PO tidak memiliki index pendukung.

**Expected Business Requirement:**
Daftar per customer, project, PO, invoice, dan soft delete harus tetap cepat saat data bertambah.

**Gap / Risk:**
Sequential scan pada tabel transaksi yang tumbuh. Dampak baru terasa setelah volume data meningkat.

**Business Impact:**
Dashboard dan daftar transaksi melambat; pengalaman Sales/Admin menurun.

**Technical Impact:**
Query plan memburuk, beban database naik, dan cache tidak selalu menutupi.

**Recommendation:**
Tambahkan index berdasarkan query nyata, bukan semua FK. Prioritaskan `(customer_id, deleted_at, created_at)`, `(project_id, deleted_at)`, `(supplier_purchase_order_id)`, `(invoice_id, payment_date)`, dan `(assigned_to, status)`. Ukur dengan `EXPLAIN` sebelum dan sesudah.

---

## DB-008 - Derived data tidak memiliki penjaga database

**Finding ID:** DB-008
**Title:** Status/nominal/progress adalah cache yang hanya dijaga service
**Severity:** Medium
**Domain:** Data integrity
**Related:** BR-019, BR-020, BR-023, GAP-013, GAP-014, DEC-012, DEC-017
**Confidence:** High

**Evidence:**
- `invoices.paid_amount`, `status`, `paid_at` dihitung dari `payments` (`AdminService.php:456-500`).
- `supplier_invoices.paid_amount/status` dihitung dari `supplier_payments` (`ProcurementService.php:153-171`).
- `supplier_purchase_orders.status`, item `quantity_received/quantity_delivered` dihitung dari receipt/delivery (`ProcurementService.php:54-118`).
- `proposals.subtotal/tax/grand_total/total_cost/gross_profit/gross_margin` dihitung dari item (`Proposal::recalculate`).
- `projects.progress/workflow_state/commercial_closed_at/bast_ready_at` dihitung dari workflow/BAST/payment.
- `products.cost_price` dan `product_suppliers.last_cost_price` adalah cache dari price log.

**Current Implementation:**
Service memanggil rekalkulasi setelah aksi tertentu. Tidak ada trigger, generated column, atau constraint yang memaksa konsistensi.

**Expected Business Requirement:**
Nilai yang ditampilkan untuk invoice, payment, progress, dan margin harus konsisten dengan sumber transaksinya.

**Gap / Risk:**
Perubahan langsung, command, atau kegagalan setelah transaksi dapat meninggalkan cache yang salah. Tidak ada mekanisme rekonsiliasi terjadwal.

**Business Impact:**
Invoice bisa tampil lunas padahal payment belum lengkap, atau progress/closing tidak sesuai.

**Technical Impact:**
Sulit dideteksi tanpa query rekonsiliasi; test hanya mencakup jalur service.

**Recommendation:**
Tambahkan command rekonsiliasi berkala dan test invariant. Jika volume tinggi, pertimbangkan trigger untuk status finansial. Jangan menambah trigger sebelum keputusan bisnis tentang status final.

---

## DB-009 - Customer PO memiliki dua sumber data

**Finding ID:** DB-009
**Title:** `proposals.po_*` aktif, `purchase_orders` legacy, dan `proposals.po_number` tidak unique
**Severity:** Medium
**Domain:** Quotation / procurement
**Related:** BR-017, BR-019, DEC-008, DEC-013
**Confidence:** High

**Evidence:**
- Customer PO baru disimpan di `proposals.po_document_path`, `po_number`, `po_date`, `delivery_deadline`, `delivery_address`, `po_verified_at` (`2026_10_27_000004_add_customer_po_fields_to_proposals.php:23-30`).
- Tabel `purchase_orders` masih ada dan dibaca di admin hub/Document Center; `AdminController@posStore` sekarang 403 (`AdminController.php:283-285`).
- `proposals.po_number` hanya `string nullable`, bukan unique.
- `purchase_orders.po_number` unique, tetapi tabel legacy.
- `DocumentCenterService` masih join `purchase_orders` (`DocumentCenterService.php:206`).

**Current Implementation:**
Quotation handoff memakai kolom proposal; tabel purchase_orders adalah data lama/read-only. Tidak ada migrasi yang menyatukan atau mengarsipkan tabel lama.

**Expected Business Requirement:**
Satu customer PO memiliki satu sumber data dan satu validasi nomor.

**Gap / Risk:**
Data PO lama dan baru dapat berbeda; laporan dapat menghitung dua kali; nomor PO customer ganda di proposal tidak ditolak.

**Business Impact:**
Admin dapat melihat PO ganda atau PO yang tidak terhubung ke quotation/project baru.

**Technical Impact:**
Dua jalur query, dua arti status, dan potensi data migration yang kompleks.

**Recommendation:**
Tetapkan `proposals.po_*` sebagai sumber resmi, arsipkan `purchase_orders` setelah migrasi data. Tambahkan unique index pada `proposals.po_number` setelah scope unik disepakati (global/per PT/per customer).

---

## DB-010 - Supplier master terduplikasi

**Finding ID:** DB-010
**Title:** `partners` bertipe supplier dan `suppliers` adalah dua master terpisah
**Severity:** Medium
**Domain:** Master data / procurement
**Related:** BR-011, BR-018, GAP-012
**Confidence:** High

**Evidence:**
- `partners.type` memiliki nilai `supplier` (`partners_type_check`).
- `leads.partner_id` hanya menunjuk `partners`.
- Procurement memakai `suppliers` (`product_suppliers`, `supplier_purchase_orders`, `supplier_invoices`, `product_price_logs`).
- Tidak ada `supplier_id` di `partners` atau `partner_id` di `suppliers`.

**Current Implementation:**
Marketing memakai `partners`; Purchasing memakai `suppliers`. Keduanya memiliki nama, kontak, telepon, dan email.

**Expected Business Requirement:**
Satu supplier master yang dipakai marketing, estimator, dan purchasing, atau relasi eksplisit antara partner dan supplier.

**Gap / Risk:**
Supplier yang sama dapat memiliki dua record dengan data kontak berbeda. Harga dan PO tidak terhubung ke partner marketing.

**Business Impact:**
Duplikasi data supplier, kesalahan kontak, dan laporan supplier tidak konsisten.

**Technical Impact:**
Sinkronisasi manual, query union, dan potensi migrasi data.

**Recommendation:**
Pilih satu master. Jika `suppliers` adalah master procurement, tambahkan `supplier_id` di `partners` atau migrasikan partner bertipe supplier. Jangan menghapus `partners` sebelum data marketing dipetakan.

---

## DB-011 - Tiga tabel price request dengan fungsi tumpang tindih

**Finding ID:** DB-011
**Title:** `price_requests`, `estimator_requests`, dan `request_hargas` hidup bersamaan
**Severity:** Medium
**Domain:** Estimator / pricing
**Related:** BR-010, BR-011, GAP-003
**Confidence:** High

**Evidence:**
- `price_requests` (17 kolom) untuk permintaan harga teknisi/sales.
- `estimator_requests` + items (18+21 kolom) untuk harga modal produk.
- `request_hargas` (9 kolom) menyimpan `items` dan `attachments` sebagai JSON; masih aktif di route `/teknisi/request-hargas` (`routes/web.php:497-537`).
- `AdminController@posCreate` masih membaca `RequestHarga.items` untuk prefill PO lama.

**Current Implementation:**
Tiga konsep berjalan dengan tabel, status, dan UI berbeda.

**Expected Business Requirement:**
Business Discovery memakai satu istilah “Price Request” untuk permintaan harga dari Sales ke Estimator. Perlu keputusan apakah tiga konsep ini memang berbeda atau harus disatukan.

**Gap / Risk:**
Harga dan status tersebar; laporan harga tidak lengkap; `request_hargas` JSON tidak bisa di-join.

**Business Impact:**
Estimator/Sales dapat melihat dua antrean berbeda untuk kebutuhan yang mirip.

**Technical Impact:**
Tiga model, tiga status, dan migrasi data yang rumit.

**Recommendation:**
Buat decision table: produk vs jasa instalasi vs permintaan proyek. Setelah itu tetapkan satu tabel utama dan arsipkan sisanya. Jangan merge sebelum keputusan bisnis.

---

## DB-012 - Invoice item tidak tertaut ke quotation item

**Finding ID:** DB-012
**Title:** `invoice_items` tidak memiliki `proposal_item_id` atau `product_id`
**Severity:** Medium
**Domain:** Billing traceability
**Related:** BR-019, BR-020, GAP-002
**Confidence:** High

**Evidence:**
- `invoice_items` hanya berisi `description`, `quantity`, `unit`, `unit_price`, `discount`, `subtotal`.
- `invoices` memiliki `proposal_id` dan `project_id`, tetapi item invoice tidak menunjuk ke `proposal_items`.
- `AdminService::createInvoice` menyalin nilai item, bukan relasi (`AdminService.php:66-116`).

**Current Implementation:**
Invoice dapat dibuat dari quotation, tetapi baris invoice berdiri sendiri.

**Expected Business Requirement:**
Admin seharusnya dapat menelusuri item invoice ke item quotation untuk verifikasi dan audit.

**Gap / Risk:**
Tidak bisa membuktikan invoice sesuai quotation; perubahan/retur item sulit dilacak.

**Business Impact:**
Sengketa jumlah/quantity lebih sulit diselesaikan.

**Technical Impact:**
Laporan margin per invoice tidak dapat di-join ke quotation item.

**Recommendation:**
Tambahkan nullable `proposal_item_id` dan/atau `product_id` pada `invoice_items`, isi saat membuat invoice dari quotation, dan pertahankan snapshot teks untuk histori. Jangan mengubah invoice lama tanpa backfill yang disetujui.

---

## DB-013 - Business key belum unique

**Finding ID:** DB-013
**Title:** SKU, customer identity, Work Type, dan Project Status tidak memiliki unique constraint
**Severity:** Medium
**Domain:** Master data / uniqueness
**Related:** BR-017, DEC-013
**Confidence:** High

**Evidence:**
- `products.sku` hanya index, bukan unique (`2026_10_07_110501_create_products_table.php:16`).
- `customers.email` dan `customers.name` tidak unique.
- `work_types.name` tidak unique.
- `project_statuses.name` dan `is_default` tidak unique.
- `proposals.po_number` tidak unique.
- `customers` tidak memiliki external code.

**Current Implementation:**
Validasi aplikasi sebagian mencegah duplikat, tetapi tidak semua jalur dan tidak di database.

**Expected Business Requirement:**
Kode internal produk dan master reference harus unik sesuai scope yang disepakati.

**Gap / Risk:**
Produk ganda, customer ganda, Work Type ganda, dan status default ganda. Workflow yang mencari status berdasarkan nama bisa salah.

**Business Impact:**
Harga dan stok terpecah antar produk ganda; laporan customer tidak akurat.

**Technical Impact:**
Duplikasi data sulit dibersihkan dan dapat memperbesar tabel transaksi.

**Recommendation:**
Tetapkan scope unique (global/per PT) lalu tambahkan unique index. Minimal: `products.sku` (jika dipakai sebagai kode internal), `work_types.name`, `project_statuses.name`, dan partial unique `project_statuses.is_default`.

---

## DB-014 - Serial number disimpan sebagai JSON

**Finding ID:** DB-014
**Title:** Serial number tidak memiliki tabel relasional dan unique constraint
**Severity:** Medium
**Domain:** Procurement / inventory
**Related:** BR-015, BR-016, GAP-008
**Confidence:** High

**Evidence:**
- `goods_receipt_items.serial_numbers` JSON.
- `goods_delivery_items.serial_numbers` JSON.
- `GoodsReceiptController` memvalidasi duplikat serial di aplikasi, bukan database.
- Tidak ada tabel `product_serials` atau `serial_numbers`.

**Current Implementation:**
Serial number disimpan sebagai array JSON per baris receipt/delivery.

**Expected Business Requirement:**
Serial number harus dapat dilacak dari receipt ke delivery dan dicek unik jika produk memerlukan serial.

**Gap / Risk:**
Serial yang sama bisa masuk dua receipt melalui jalur berbeda; laporan per serial membutuhkan parsing JSON.

**Business Impact:**
Klaim garansi dan penelusuran unit bermasalah lebih sulit.

**Technical Impact:**
Tidak ada index/unique untuk serial; query JSON lebih mahal.

**Recommendation:**
Jika serial tracking adalah kebutuhan final, buat tabel `product_serials` dengan unique `(product_id, serial_number)`, relasi ke receipt item dan delivery item. Jika belum, dokumentasikan bahwa serial hanya informational.

---

## DB-015 - Project support pivot soft delete tidak difilter

**Finding ID:** DB-015
**Title:** `Project::supportUsers` membaca pivot yang sudah soft-deleted
**Severity:** Medium
**Domain:** Project / technician
**Related:** BR-018, BR-022, GAP-011
**Confidence:** High

**Evidence:**
- `project_supports` memiliki `deleted_at`.
- `Project::supportUsers()` memakai `belongsToMany` tanpa `wherePivotNull('deleted_at')` (`Project.php:136-139`).
- `ProjectSupportController@destroy` memanggil `$projectSupport->delete()` (`ProjectSupportController.php:109-128`).
- `ProjectSupport` fillable memuat `title`, `description`, `status`, `priority`, `assigned_to`, `resolved_at`, `resolved_by`, tetapi kolom itu tidak ada di tabel.
- `project_supports` hanya memiliki PK; tidak ada unique `(project_id,user_id)`.

**Current Implementation:**
Support dihapus secara soft delete, tetapi relasi pivot tetap membacanya. Model juga memiliki field yang tidak ada di schema.

**Expected Business Requirement:**
Support yang dihapus tidak boleh muncul; satu user tidak boleh diduplikasi pada project yang sama.

**Gap / Risk:**
Support “hantu” tetap tampil di halaman project. Penulisan field yang tidak ada dapat error jika suatu saat controller mengirim mass assignment.

**Business Impact:**
Tim teknisi bisa melihat engineer yang sudah dilepas; notifikasi bisa salah sasaran.

**Technical Impact:**
Bug relasi, data pivot ganda, dan model/schema drift.

**Recommendation:**
Tambahkan `wherePivotNull('deleted_at')` pada relasi, unique `(project_id,user_id)`, dan selaraskan `$fillable` dengan kolom nyata. Tambahkan test delete/restore support.

---

## DB-016 - Customer dari WhatsApp tidak memiliki owner/PT

**Finding ID:** DB-016
**Title:** WhatsApp conversion membuat `customers.created_by` dan `pt_group` kosong
**Severity:** Medium
**Domain:** Customer ownership / authorization
**Related:** BR-001, BR-002, BR-021, GAP-001
**Confidence:** High

**Evidence:**
- `WhatsAppCenterController.php:467-473` membuat `Customer::create` tanpa `created_by` dan tanpa `pt_group`.
- `LeadController.php:517-528` membuat customer dengan `created_by` dan `pt_group`.
- `CustomerAccess.php:60-86` memakai `customers.created_by`, lead ownership, dan `pt_group` untuk scope Sales.
- `customers.whatsapp_account_id` diisi, tetapi bukan owner.

**Current Implementation:**
Customer dari WhatsApp tidak memiliki owner eksplisit; lead-nya memiliki `pt_group` dari account code, tetapi customer tidak. Customer baru terlihat oleh Sales setelah lead di-assign; sebelum itu hanya management/admin yang melihatnya.

**Expected Business Requirement:**
Setiap customer harus memiliki owner atau PT yang jelas agar isolasi Sales, filter PT, dan laporan customer bekerja.

**Gap / Risk:**
Customer WhatsApp tidak muncul di daftar Sales sebelum assignment dan tidak memiliki PT untuk filter. Jika lead dihapus, customer dapat menjadi orphan dengan `created_by` null dan `pt_group` null.

**Business Impact:**
Customer dari channel WhatsApp lebih sulit ditelusuri dan dapat hilang dari laporan per PT. Tidak ada bukti kebocoran lintas Sales pada data normal, tetapi ownership tidak eksplisit.

**Technical Impact:**
Data ownership tidak konsisten; laporan PT harus memakai lead, bukan customer. Policy bergantung pada fallback.

**Recommendation:**
Isi `created_by` dari user yang melakukan convert, dan `pt_group` dari account/lead. Tambahkan test bahwa customer WhatsApp muncul untuk Sales PT yang benar setelah lead di-assign, dan tidak muncul untuk Sales PT lain sebelum assignment.

---

## DB-017 - Revision snapshot tidak lengkap dan PDF tertimpa

**Finding ID:** DB-017
**Title:** `proposal_versions` hanya menyimpan item/total; PDF memakai path yang sama
**Severity:** Medium
**Domain:** Quotation history
**Related:** BR-008, BR-009, DEC-015
**Confidence:** High

**Evidence:**
- `QuotationVersionService.php:14-36` menyimpan `snapshot` item, `total_before`, `total_after`, `change_summary`.
- `proposal_versions` tidak menyimpan header quotation, payment terms, PO, customer address, atau file PDF.
- `QuotationPdfService.php:30-43` menyimpan PDF ke `quotations/{proposal_number}.pdf`, sehingga regenerasi menimpa file lama.
- `proposals.pdf_path` hanya satu kolom.

**Current Implementation:**
Versi quotation menyimpan item dan total; dokumen PDF selalu versi terbaru.

**Expected Business Requirement:**
Revisi quotation harus dapat direkonstruksi dan dibandingkan dengan dokumen yang dikirim ke customer.

**Gap / Risk:**
Tidak bisa melihat isi lengkap quotation lama setelah header/payment terms berubah; PDF lama hilang.

**Business Impact:**
Sengketa harga/isi penawaran sulit dibuktikan.

**Technical Impact:**
Snapshot parsial; file storage tidak immutable.

**Recommendation:**
Simpan snapshot header + item + payment terms pada `proposal_versions`, dan simpan PDF per versi (`quotations/{proposal_id}/v{n}.pdf`). Jangan menimpa file lama.

---

## DB-018 - Assignment teknisi memiliki tiga sumber

**Finding ID:** DB-018
**Title:** `teknisi_user_id`, `pic_engineer`, `support_technicians`, dan pivot dapat berbeda
**Severity:** Medium
**Domain:** Project / technician
**Related:** BR-022, GAP-011
**Confidence:** High

**Evidence:**
- `projects.teknisi_user_id` FK user.
- `projects.pic_engineer` string.
- `projects.support_technicians` string.
- `project_supports` pivot project-user.
- `technician_schedules.user_id` (owner), `technician_user_id` (main engineer), dan `technician_schedule_user` pivot.
- `ProjectService` menulis `pic_engineer`, `support_technicians`, dan pivot (`ProjectService.php:23-104`).
- `ProjectSupportController@store` hanya menulis pivot, bukan teks.

**Current Implementation:**
Nama teknisi disimpan sebagai snapshot teks sekaligus relasi user. Beberapa jalur hanya memperbarui salah satunya.

**Expected Business Requirement:**
Assignment resmi harus satu sumber; nama teks hanya snapshot untuk histori.

**Gap / Risk:**
Project dapat menampilkan teknisi berbeda di halaman, laporan, dan notifikasi.

**Business Impact:**
Salah penugasan dan notifikasi; teknisi bisa tidak menerima pekerjaan.

**Technical Impact:**
Data drift dan query yang bergantung pada jalur penulisan.

**Recommendation:**
Tetapkan FK/pivot sebagai sumber assignment. Simpan nama sebagai snapshot read-only yang diisi service. Hapus atau hentikan penulisan ganda setelah migrasi.

---

## DB-019 - Nomor dokumen dapat bentrok saat concurrent

**Finding ID:** DB-019
**Title:** Generator nomor invoice/PO/GR/DO/SINV memakai read-max tanpa retry
**Severity:** Medium
**Domain:** Concurrency
**Related:** BR-017, DEC-013
**Confidence:** Medium

**Evidence:**
- `AdminService::generateInvoiceNumber` dan `generatePoNumber` membaca nomor terakhir lalu `+1` (`AdminService.php:30-46`).
- `ProcurementService::generateNumber` melakukan pola sama untuk GR/DO/SINV (`ProcurementService.php:38-48`).
- Unique index tersedia, tetapi hanya `QuotationNumberService` yang mencoba ulang sampai 10 kali (`QuotationNumberService.php:26-39`).
- `createInvoice` hanya menangkap QueryException jika ada idempotency key.

**Current Implementation:**
Nomor unik dijaga database, tetapi generator tidak selalu menangani tabrakan.

**Expected Business Requirement:**
Dua user yang menyimpan dokumen bersamaan tidak boleh mendapat error 500; salah satu harus mendapat nomor berikutnya.

**Gap / Risk:**
Tabrakan unik pada saat ramai dapat menyebabkan request gagal.

**Business Impact:**
Admin mengulang input; risiko duplikasi atau kehilangan pekerjaan.

**Technical Impact:**
Transaksi gagal, error 500, dan beban support.

**Recommendation:**
Gunakan database sequence per jenis dokumen atau retry transaksi pada unique violation. Sentralisasi generator nomor.

---

## DB-020 - Customer lifecycle memiliki status ganda

**Finding ID:** DB-020
**Title:** `customers.status` memiliki `instalasi/selesai`, sementara project punya status sendiri
**Severity:** Low
**Domain:** Customer lifecycle
**Related:** BR-001, DEC-001, DEC-007
**Confidence:** High

**Evidence:**
- `customers.status` check mencakup `lead`, `deal`, `instalasi`, `selesai`.
- `CustomerController.php:125` mengizinkan status tersebut di form.
- Tidak ada kode yang otomatis memindahkan customer ke `instalasi` atau `selesai`.
- `ProjectObserver.php:39-41` hanya menaikkan `lead` menjadi `deal`.
- Project memiliki `project_status_id` dan `commercial_closed_at`.

**Current Implementation:**
Customer status dapat diubah manual, tetapi tidak mengikuti lifecycle project. Dua sumber status hidup berdampingan.

**Expected Business Requirement:**
Status customer dan status project harus punya definisi yang tidak tumpang tindih.

**Gap / Risk:**
Customer bisa berstatus `deal` sementara project sudah selesai, atau sebaliknya.

**Business Impact:**
Laporan customer/management bisa berbeda dari laporan project.

**Technical Impact:**
Filter status ganda dan data yang tidak konsisten.

**Recommendation:**
Tetapkan customer status hanya `lead/deal` atau tambahkan aturan otomatis dari project. Jika `instalasi/selesai` dipertahankan, dokumentasikan sumber dan waktu perubahannya.

---

## DB-021 - `users.role` legacy bersaing dengan Spatie roles

**Finding ID:** DB-021
**Title:** Kolom `users.role` dan `model_has_roles` adalah dua sumber role
**Severity:** Medium
**Domain:** Authorization data
**Related:** BR-021, BR-022, GAP-012
**Confidence:** High

**Evidence:**
- `users.role` adalah kolom string default `guest`, tanpa check constraint.
- Spatie menyimpan role di `model_has_roles`.
- `AdminPanelController.php:54-56` menulis keduanya saat create; `:95-97` saat update.
- `ProjectTaskController.php:16` dan `ProjectSupportController.php:28` memakai `User::where('role','technician')`.
- Seeder menyelaraskan kolom legacy, tetapi multi-role dapat membuat nilai legacy tidak mewakili semua role.

**Current Implementation:**
Role utama disalin ke `users.role`; role tambahan hanya di Spatie. Beberapa query masih memakai kolom legacy.

**Expected Business Requirement:**
Satu sumber role yang otoritatif, terutama untuk teknis dan akses.

**Gap / Risk:**
User dengan role Spatie `technician` tetapi legacy `sales` tidak muncul di dropdown teknisi.

**Business Impact:**
Teknisi tidak bisa dipilih untuk project/task.

**Technical Impact:**
Query tidak konsisten dan bug sulit direproduksi.

**Recommendation:**
Hentikan query berbasis `users.role`; gunakan `hasRole`/`role()`. Setelah semua query dimigrasikan, hapus kolom legacy atau jadikan read-only sinkron.

---

## DB-022 - `project_documents.category_id` duplikat

**Finding ID:** DB-022
**Title:** Dua kolom kategori pada `project_documents`
**Severity:** Low
**Domain:** Document schema
**Related:** GAP-006
**Confidence:** High

**Evidence:**
- `project_documents.document_category_id` ditambahkan pada `2026_07_01_020244_migrate_document_type_to_category_id_on_project_documents.php:16`.
- `project_documents.category_id` ditambahkan pada `2026_07_07_081327_add_category_id_to_project_documents_table.php:12-18` dan migrasi duplikat `2026_07_07_081644`.
- Model hanya memakai `document_category_id` (`ProjectDocument.php:129`).
- Keduanya memiliki FK ke `document_categories`.

**Current Implementation:**
Satu kolom kategori dipakai; kolom kedua adalah warisan.

**Expected Business Requirement:**
Satu kolom kategori yang jelas.

**Gap / Risk:**
Data bisa terisi di kolom yang salah; query filter bisa melewatkan dokumen.

**Business Impact:**
Dokumen tidak muncul di kategori yang benar.

**Technical Impact:**
Kebingungan schema dan index tambahan yang tidak perlu.

**Recommendation:**
Pastikan `category_id` kosong/tidak dipakai, lalu drop kolom setelah verifikasi data dan persetujuan. Jangan drop sebelum cek data produksi.

---

## DB-023 - Tabel lifecycle terlalu lebar dan mencampur domain

**Finding ID:** DB-023
**Title:** `proposals` 51 kolom dan `projects` 35 kolom mencampur banyak lifecycle
**Severity:** Low
**Domain:** Schema design
**Related:** GAP-002, GAP-013
**Confidence:** High

**Evidence:**
- `proposals` mencakup quotation, PO customer, payment terms, PDF, letterhead, margin, dan versi.
- `projects` mencakup project, BAST, workflow, delivery flags, closing, dan technician snapshot.
- Banyak kolom nullable dan bergantung pada tahap.

**Current Implementation:**
Satu tabel menyimpan seluruh lifecycle.

**Expected Business Requirement:**
Struktur harus cukup jelas untuk audit dan perubahan aturan.

**Gap / Risk:**
Kolom yang tidak relevan pada tahap tertentu membingungkan; constraint tidak bisa ketat.

**Business Impact:**
Tidak langsung, tetapi memperlambat perubahan dan meningkatkan risiko bug.

**Technical Impact:**
Wide table, nullable tinggi, dan sulit di-index secara optimal.

**Recommendation:**
Jangan pecah tabel tanpa kebutuhan nyata. Jika ada perubahan besar pada quotation/BAST, pertimbangkan tabel terpisah untuk PO customer dan BAST. Lakukan setelah keputusan bisnis.

---

## DB-024 - PT/company bukan entitas relasional

**Finding ID:** DB-024
**Title:** `pt_group` dan `letterhead_pt` adalah string; tidak ada tabel `companies`
**Severity:** Medium
**Domain:** Multi-PT
**Related:** BR-007, GAP-012, DEC-003, DEC-013
**Confidence:** High

**Evidence:**
- `customers.pt_group` string nullable.
- `leads.pt_group` string nullable.
- `proposals.letterhead_pt` string.
- `config/companies.php` menyimpan data PT, alamat, bank, dan signatory.
- Tidak ada tabel `companies` atau FK PT.
- Validasi aplikasi memakai `Lead::PT_GROUPS` dan `writablePtGroups`.

**Current Implementation:**
PT adalah string yang divalidasi di form. Data perusahaan ada di config, bukan database.

**Expected Business Requirement:**
Empat PT adalah entitas bisnis dengan identitas, dokumen, dan akses. Perubahan data PT seharusnya dapat dikelola tanpa deploy.

**Gap / Risk:**
Nama PT berubah di config tetapi data lama tetap string lama; tidak ada referential integrity. Laporan per PT bergantung pada string.

**Business Impact:**
Kesalahan kop quotation, akses PT, dan laporan per perusahaan.

**Technical Impact:**
Tidak ada join PT; validasi tersebar; config tidak dapat diubah admin.

**Recommendation:**
Tetapkan apakah PT cukup sebagai enum/config atau perlu tabel `companies`. Jika perlu, buat tabel company dan ganti string bertahap. Jangan mengubah semua kolom sekaligus.

---

## 4. Prioritas Tindak Lanjut

| Prioritas | Finding | Alasan |
|---:|---|---|
| 1 | DB-001 | Potensi kehilangan seluruh project |
| 2 | DB-002 | Potensi kehilangan invoice/payment |
| 3 | DB-003 | Tidak ada audit yang bisa dipertanggungjawabkan |
| 4 | DB-004 | Automation project dapat berhenti tanpa error |
| 5 | DB-005, DB-006 | Integritas status dan relasi |
| 6 | DB-008, DB-009, DB-012 | Konsistensi billing dan handoff |
| 7 | DB-011, DB-010, DB-024 | Duplikasi domain dan master |
| 8 | DB-007, DB-019 | Performa dan concurrency |
| 9 | DB-016, DB-021 | Ownership dan role yang membingungkan |
| 10 | DB-017, DB-018, DB-020, DB-022, DB-023 | Perbaikan bertahap |

## 5. Batasan Audit

- Tidak ada data aktual yang diperiksa untuk membuktikan duplikat, orphan, atau nilai invalid.
- Tidak ada query plan (`EXPLAIN`) yang dijalankan.
- Tidak ada test aplikasi yang dijalankan pada phase ini.
- Database yang terhubung belum tentu database produksi; temuan bersifat struktural.
- Beberapa keputusan (PO unik, company master, status final) tidak dapat diselesaikan tanpa keputusan bisnis.

## 6. Rekonsiliasi Angka

- Tabel diperiksa: 87.
- Model diperiksa: 68.
- Migration diperiksa: 210.
- Foreign key dipetakan: 182.
- Relasi polymorphic/application-only: minimal 10 pola (lihat `phase-2-entity-relationship-map.md`).
- Finding: 24 total (1 Critical, 3 High, 17 Medium, 3 Low).
- Business Rule: 8 Didukung, 13 Sebagian, 2 Tidak Ditemukan.
- GAP: 6 Didukung, 8 Sebagian.
- DEC: 1 Didukung, 1 Sebagian, 5 Tidak Ditemukan, 13 Perlu Keputusan.

## 7. Area yang Belum Dapat Diaudit

- Kualitas data produksi (duplikat customer, orphan project, serial ganda).
- Query plan dan beban database aktual.
- Retensi dan kebijakan arsip finansial.
- Kebijakan backup/restore dan point-in-time recovery.
- Akses database user produksi dan izin `REFERENCES`.
- Integrasi eksternal dan dampaknya pada struktur data.
