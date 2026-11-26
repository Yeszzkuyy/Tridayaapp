# Phase 2.2 - Migration Strategy

**Audit:** Phase 2.2 - Implementation Planning
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada migration yang dibuat atau dijalankan.

## 1. Prinsip Migrasi

1. **Backup dulu** - backup database dan file storage sebelum migration.
2. **Additive first** - tambah kolom/tabel nullable; constraint belakangan.
3. **Data migration terpisah** - bukan di dalam migration schema.
4. **Idempotent** - data migration aman dijalankan ulang.
5. **Dry-run** - jalankan di staging/PostgreSQL clone, bukan produksi.
6. **No destructive rollback** - rollback schema boleh; rollback data yang menghapus histori tidak boleh.
7. **Legacy tetap ada** - `partners`, `purchase_orders`, `request_hargas` tidak dihapus.
8. **Pending tidak dibuat** - PO unique dan margin minimum tidak masuk migration.

## 2. Kandidat Migration

Nama berikut adalah rancangan, belum dibuat.

| ID | Migration | Tujuan | BDA | Reversible? |
|---|---|---|---|---|
| M2.2-01 | `add_archived_fields_to_customers_table` | `archived_at`, `archived_by`; index | BDA-005 | Ya, tetapi kehilangan status arsip jika di-rollback |
| M2.2-02 | `add_is_active_to_work_types_table` | Nonaktifkan Work Type | BDA-006 | Ya |
| M2.2-03 | `change_projects_work_type_foreign_key` | CASCADE → RESTRICT/SET NULL | BDA-006 | Ya, tetapi berisiko |
| M2.2-04 | `create_audit_logs_table` | Audit global immutable | BDA-012 | Ya, tetapi audit hilang |
| M2.2-05 | `add_system_fields_to_project_statuses_table` | `code`, `is_system`; unique | BDA-007 | Ya |
| M2.2-06 | `create_project_bast_histories_table` | Riwayat metadata BAST | BDA-026 | Ya, tetapi history hilang |
| M2.2-07 | `add_supplier_id_to_partners_table` | Mapping partner → supplier | BDA-008 | Ya, mapping hilang |
| M2.2-08 | `add_unique_sku_to_products_table` | Identitas produk | BDA-009 | Ya, index bisa drop |
| M2.2-09 | `add_cost_log_constraints_and_indexes` | Supplier/index histori | BDA-010 | Sebagian |
| M2.2-10 | `create_proposal_payment_terms_table` | Jadwal termin | BDA-027 | Ya, schedule hilang |
| M2.2-11 | `add_payment_term_id_to_invoices_table` | Relasi invoice ke termin | BDA-027 | Ya |
| M2.2-12 | `create_goods_delivery_receipts_tables` | Receipt customer + bukti | BDA-025 | Ya, bukti/history hilang |
| M2.2-13 | `add_indexes_for_audit_and_ownership` | Index actor/subject/soft delete | BDA-012/013 | Ya |
| M2.2-14 | `add_end_user_fields_to_leads_table` | Opsional, tidak sekarang | BDA-016 | Ya |
| M2.2-15 | `add_status_check_constraints` | Constraint status final | BDA-005/007 | Ya, tetapi data invalid bisa gagal |

**Tidak termasuk migration Phase 2.2:**

- Unique `proposals.po_number` (pending).
- Target margin minimum (pending).
- SLA reminders (deferred).
- Penghapusan tabel legacy.
- Rename status massal.

## 3. Urutan Deployment

### Step 0 - Preflight

- Backup PostgreSQL dan storage.
- Clone database ke staging.
- Data profiling:
  - duplikat `products.sku`;
  - duplikat `work_types.name`;
  - duplikat `project_statuses.name/default`;
  - partner supplier vs supplier;
  - projects tanpa status valid;
  - customers dengan invoice/payment.
- Siapkan rollback plan dan maintenance window.

### Step 1 - Additive Schema

1. M2.2-01 archive customer.
2. M2.2-02 Work Type active.
3. M2.2-04 audit log.
4. M2.2-05 status code/system (nullable dulu).
5. M2.2-06 BAST history.
6. M2.2-07 partner supplier link (nullable).
7. M2.2-10 payment terms.
8. M2.2-11 invoice term link.
9. M2.2-12 delivery receipt tables.
10. M2.2-13 indexes.

### Step 2 - Data Migration

1. Backfill `project_statuses.code` dari nama; `is_system` untuk status inti.
2. Backfill `partners.supplier_id` dari suppliers (normalisasi nama/kode/email/phone).
3. Backfill BAST history dari `projects.bast_*` dan `project_documents`.
4. Backfill `proposal_payment_terms` dari `payment_scheme`/`dp_percent` bila cukup; tandai legacy.
5. Backfill `goods_delivery_receipts` hanya jika ada bukti; jika tidak, status `legacy_delivered`/unknown.
6. Backfill customer ownership dari lead/PT bila memungkinkan.
7. Jangan backfill audit log historis; audit mulai dari deployment.

### Step 3 - Constraints

1. M2.2-08 unique `products.sku` setelah duplikat bersih.
2. Unique `project_statuses.code` dan partial unique default.
3. M2.2-03 FK Work Type setelah data project aman.
4. M2.2-09 constraint/index cost log.
5. M2.2-15 check status setelah daftar final.
6. Unique PO customer **tidak** dijalankan.

### Step 4 - Application Rollout

- Deploy guard/policy/service di belakang feature flag.
- Aktifkan audit logger.
- Aktifkan receipt customer dan payment terms.
- Aktifkan KPI PO setelah perbandingan angka.

### Step 5 - Cleanup

- Arsipkan tabel/kolom legacy setelah nol referensi.
- Jangan drop `partners`, `purchase_orders`, `request_hargas` di Phase 2.2.

## 4. Detail Migration Kritis

### 4.1 Work Type FK

**Saat ini:** `projects.work_type_id` CASCADE.
**Target:** `RESTRICT` atau `SET NULL`; Work Type nonaktif.
**Langkah:**
1. Tambah `is_active`/soft delete.
2. Pastikan semua project punya `work_type_id` valid.
3. Drop FK lama, add FK baru.
4. Uji delete Work Type.

**Rollback:** drop FK baru, add FK CASCADE. **Jangan rollback setelah delete test di produksi**; restore backup.

### 4.2 Customer archive

**Saat ini:** `deleted_at` = Trash; force delete menghapus transaksi.
**Target:** arsip tidak menghapus; laporan historis tetap.
**Pilihan desain:**
- A: `archived_at` terpisah dari `deleted_at` (disarankan).
- B: gunakan `deleted_at` sebagai arsip dan `withTrashed` di laporan.

**Rekomendasi:** A, agar Trash dan arsip tidak bercampur. Jangan hapus `deleted_at`.
**Backfill:** customer lama dengan `deleted_at` tetap di Trash; status arsip null.

### 4.3 BAST history

**Saat ini:** `projects.bast_*` single row; `BastController` update file yang sama dan menghapus file lama.
**Target:** setiap upload BAST menjadi version baru; metadata tersimpan.
**Langkah:**
1. Buat `project_bast_histories` (project_id, bast_number, bast_date, document_id, signed fields, created_by).
2. Backfill satu row dari data project saat ini.
3. Ubah `BastController` agar insert history dan version dokumen, bukan overwrite.
4. Pertahankan pointer `projects.bast_document_id` ke versi terbaru.

### 4.4 Payment terms

**Saat ini:** `payment_scheme` full/dp/tempo; `dp_percent`; `term_days`.
**Target:** `proposal_payment_terms` (proposal_id, sequence, type, percentage, amount, due_date, notes, is_legacy).
**Backfill:**
- `full`: satu term 100%, due date null.
- `dp`: DP term + sisa; due date null jika tidak ada data.
- `tempo`: satu term 100%, due date dihitung dari `term_days` bila tanggal proposal tersedia.
- Jangan mengarang tanggal; null diperbolehkan.
**Relasi invoice:** `invoices.payment_term_id` nullable.

### 4.5 Customer receipt

**Saat ini:** `goods_deliveries.status` prepared/shipped/delivered.
**Target:** `goods_delivery_receipts` + items (quantity received, condition, serial, proof).
**Backfill:** delivery `delivered` lama tidak otomatis menjadi customer received; tandai `legacy_delivered`/unknown. Jangan mengklaim customer menerima tanpa bukti.

## 5. Strategi Data Historis

| Data | Strategi |
|---|---|
| Customer | Arsip, bukan hapus; laporan memakai scope historis |
| Quotation | `proposal_versions` tetap; PDF per versi (BDA-026 unrelated) |
| BAST | History metadata + document version |
| Supplier | Mapping partner ke supplier; tabel lama tetap |
| Product | Nonaktifkan; SKU unik setelah dedupe |
| Cost | `product_price_logs` append-only; jangan update row lama |
| Invoice/payment | Jangan hapus; customer archive tidak cascade |
| Delivery | Receipt baru; data lama diberi status legacy |
| Audit | Mulai dari deployment; tidak backfill palsu |

## 6. Rollback Strategy

| Migration | Rollback schema | Risiko data | Catatan |
|---|---|---|---|
| M2.2-01 archive | Drop column | Kehilangan status arsip | Export dulu |
| M2.2-02 Work Type active | Drop column | Kehilangan status aktif | Aman |
| M2.2-03 FK | Recreate CASCADE | Bahaya jika delete terjadi | Hanya sebelum penggunaan |
| M2.2-04 audit | Drop table | Audit hilang | Export |
| M2.2-05 status code | Drop unique/column | Kode hilang | Backup |
| M2.2-06 BAST history | Drop table | History hilang | Export |
| M2.2-07 partner supplier | Drop column | Mapping hilang | Export |
| M2.2-08 unique SKU | Drop index | Tidak ada data hilang | Aman |
| M2.2-10 payment terms | Drop table | Jadwal hilang | Export |
| M2.2-12 receipt | Drop tables | Bukti hilang | Export file |
| M2.2-13 indexes | Drop index | Tidak ada data hilang | Aman |

**Aturan:** rollback produksi hanya untuk migration schema yang belum digunakan. Jika data sudah diisi, rollback harus restore backup, bukan drop kolom/tabel.

## 7. Pending dan Deferred

| Item | Perlakuan migrasi |
|---|---|
| Unique PO customer | Tidak ada migration/constraint sampai keputusan |
| Target margin minimum | Tidak ada kolom/constraint baru |
| SLA reminder | Tidak ada migration |
| End-user | Tidak ada migration sekarang |
| Retur barang | Tidak ada migration |

## 8. Validasi Sebelum Deploy

- `php artisan migrate --pretend` di staging.
- Backup dan restore test.
- Data migration dry-run + row count.
- Uji unique/check constraint pada salinan data.
- Uji rollback di staging.
- Review query plan untuk index baru.
- Persetujuan bisnis untuk arsip, BAST, payment terms, receipt.

## 9. Status

Belum ada migration yang dibuat atau dijalankan. Semua nama migration dan urutan bersifat rencana.
