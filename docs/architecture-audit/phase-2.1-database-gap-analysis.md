# Phase 2.1 - Database Gap Analysis

**Audit:** Phase 2.1 - Business Decision Alignment
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada schema, migration, atau data yang diubah.

## 1. Ringkasan Gap Database

| Kategori | Jumlah BDA | Perlu schema change? |
|---|---:|---|
| Aplikasi saja (aturan/validasi) | 10 | Tidak |
| Perlu kolom/constraint/index | 8 | Ya, terbatas |
| Perlu tabel baru | 5 | Ya |
| Perlu migrasi data/legacy | 3 | Ya, setelah pemetaan |
| Pending/deferred | 2 | Tidak sekarang |

**Prinsip:** jangan menghapus tabel lama (`partners`, `purchase_orders`, `request_hargas`) sebelum pemetaan data dan persetujuan implementasi. Jangan mengubah migration yang sudah di-merge.

## 2. Gap per Keputusan

| BDA | Gap database | Tabel/kolom terdampak | Perubahan yang mungkin diperlukan | Prioritas |
|---|---|---|---|---|
| BDA-001 | Tidak ada penanda aturan DP vs invoice penuh sebelum BAST | `invoices.invoice_type`, `proposals.payment_scheme` | Aturan aplikasi; tidak perlu kolom baru | Medium |
| BDA-002 | DP fleksibel sudah didukung | `proposals.dp_percent`, `invoices.invoice_type` | Tidak ada | - |
| BDA-003 | Kewenangan ubah cost lebih luas dari keputusan | `product_price_logs`, policy | Perketat policy; tidak perlu schema | High |
| BDA-004 | Progress manual dan workflow sama-sama menulis `projects.progress` | `projects.progress`, `projects.workflow_state` | Hapus/guard input manual; pertahankan satu sumber | High |
| BDA-005 | FK customer CASCADE ke invoice/payment/lead/project | `customers`, `invoices`, `payments`, `leads`, `projects` | Ubah delete rule atau guard force delete; arsip | Critical |
| BDA-006 | Work Type CASCADE dan hard delete | `work_types`, `projects.work_type_id` | Ubah FK ke RESTRICT/SET NULL; tambah `is_active`/soft delete | Critical |
| BDA-007 | Tidak ada `code`/`is_system`; name/default tidak unik | `project_statuses` | Tambah `code`, `is_system`; unique name/default | High |
| BDA-008 | `partners` dan `suppliers` tidak terhubung | `partners`, `suppliers`, `leads.partner_id`, `product_suppliers` | Tambah pemetaan supplier atau `supplier_id` di partner | High |
| BDA-009 | `products.sku` tidak unique | `products` | Unique index sesuai scope | Medium |
| BDA-010 | `product_price_logs.supplier_id` nullable; previous price tidak eksplisit | `product_price_logs` | Wajibkan supplier untuk log cost baru; query previous price; index | Medium |
| BDA-011 | `proposals.po_number` tidak unique; generator non-retry | `proposals`, `invoices`, purchase/supplier/GR/DO | Unique constraint dan retry/sequence | High |
| BDA-012 | Tidak ada tabel audit global | Baru: `audit_logs` | Buat tabel audit immutable | High |
| BDA-013 | Customer WhatsApp tanpa `created_by`/`pt_group` | `customers` | Isi field; mungkin riwayat ownership | High |
| BDA-014 | Follow-up fleksibel sudah didukung | `follow_ups` | Tidak ada | - |
| BDA-015 | Ditunda | - | Tidak ada | Deferred |
| BDA-016 | Tidak ada kolom end-user | `leads` atau tabel end-user | Kolom nullable/tabel opsional | Low |
| BDA-017 | Komunikasi opsional sudah didukung | `follow_ups`, `whatsapp_messages` | Tidak ada | - |
| BDA-018 | Kalender memakai `SalesSchedule` non-meeting | `sales_schedules.type` | Filter aplikasi atau batasi tipe; tidak perlu schema | Medium |
| BDA-019 | KPI Management sudah didukung | `sales_targets` | Tidak ada | - |
| BDA-020 | KPI achievement memakai closed paid deal, bukan PO | `proposals.po_verified_at`, `invoices`, `payments` | Ubah query; tidak perlu schema | High |
| BDA-021 | Target margin pending | `sales_targets.target_margin` | Tidak ada; jangan jadikan validasi final | Pending |
| BDA-022 | Receipt tanpa invoice sudah didukung | `goods_receipts`, `supplier_invoices` | Tidak ada | - |
| BDA-023 | Partial receipt sudah didukung | `goods_receipts`, `goods_receipt_items` | Tidak ada | - |
| BDA-024 | Deadline ada, peringatan/overdue belum | `projects.delivery_deadline`, `proposals.delivery_deadline` | Tambah queue/reminder; tidak perlu schema | Medium |
| BDA-025 | Tidak ada status terima customer/partial/proof | `goods_deliveries`, `goods_delivery_items` | Tambah status/kolom receipt atau tabel receipt | High |
| BDA-026 | BAST single-row; history dan sync lemah | `projects.bast_*`, `project_activities` | Tabel history BAST atau audit; sync workflow | High |
| BDA-027 | Tidak ada jadwal termin bertingkat | `proposals`, baru: `proposal_payment_terms` | Tabel jadwal termin; relasi ke invoice | High |

## 3. Gap Berdasarkan Objek Database

### 3.1 Tabel dan kolom yang perlu diubah

| Objek | Gap | BDA | Catatan |
|---|---|---|---|
| `work_types` | Tidak ada `is_active`/`deleted_at`; hard delete | BDA-006 | Bisa tambah `is_active` atau soft delete |
| `projects.work_type_id` | `ON DELETE CASCADE` | BDA-006 | Ubah ke `RESTRICT`/`SET NULL` |
| `projects.progress` | Dapat ditulis manual | BDA-004 | Jadikan derived/read-only di aplikasi |
| `project_statuses` | Tidak ada `code`/`is_system`; name/default tidak unik | BDA-007 | Tambah kolom dan constraint |
| `customers` | `created_by`/`pt_group` kosong dari WhatsApp | BDA-013 | Isi di application/service; tidak perlu kolom baru |
| `customers` FK | CASCADE ke invoice/payment/lead/project | BDA-005 | Ubah delete rule atau guard |
| `products.sku` | Hanya index, bukan unique | BDA-009 | Unique setelah scope disepakati |
| `product_price_logs.supplier_id` | Nullable | BDA-010 | Wajibkan untuk log cost baru; histori null dipertahankan |
| `proposals.po_number` | Tidak unique | BDA-011 | Unique setelah scope disepakati |
| `goods_deliveries` | Status hanya prepared/shipped/delivered; tidak ada customer receipt | BDA-025 | Tambah status/kolom receipt |
| `goods_delivery_items` | Tidak ada quantity received/proof per item | BDA-025 | Tambah kolom atau tabel receipt item |
| `projects.bast_*` | Single row; tidak ada history | BDA-026 | Tabel history atau audit log |

### 3.2 Tabel baru yang mungkin diperlukan

| Tabel baru | Tujuan | BDA | Dependensi |
|---|---|---|---|
| `audit_logs` | Audit global immutable | BDA-012 | Semua modul |
| `proposal_payment_terms` | Jadwal termin fleksibel | BDA-027 | `proposals`, `invoices` |
| `goods_delivery_receipts` | Bukti terima customer/partial | BDA-025 | `goods_deliveries`, `goods_delivery_items` |
| `project_bast_histories` | Riwayat BAST | BDA-026 | `projects`, `project_documents` |
| `customer_end_users` (opsional) | End-user reseller | BDA-016 | `customers`, `leads` |

**Catatan:** tabel baru belum disetujui. Ini kandidat, bukan keputusan implementasi.

### 3.3 Constraint dan index

| Kebutuhan | BDA | Catatan |
|---|---|---|
| Unique `products.sku` | BDA-009 | Tentukan scope global/per PT |
| Unique `proposals.po_number` | BDA-011 | Tentukan scope per customer/PT/global |
| Unique `project_statuses.code` | BDA-007 | Kode sistem |
| Partial unique `project_statuses.is_default` | BDA-007 | Hanya satu default |
| FK `work_types` dengan RESTRICT/SET NULL | BDA-006 | Cegah cascade |
| FK `technical_request_supports` | BDA-013? | Belum terkait langsung |
| Index `(customer_id, deleted_at, created_at)` | BDA-013 | Performa akses Sales |
| Index `(project_id, deleted_at)` | BDA-005/026 | Project dan BAST |
| Index `(supplier_purchase_order_id)` | BDA-022/023 | Receipt/PO |
| Index `(invoice_id, payment_date)` | BDA-027 | Payment history |

### 3.4 Data legacy yang tidak boleh langsung dihapus

| Objek | Alasan | BDA |
|---|---|---|
| `partners` | Data marketing/partner historis; belum dipetakan ke `suppliers` | BDA-008 |
| `purchase_orders` | Customer PO lama; masih dibaca Document Center/admin hub | BDA-011 |
| `request_hargas` | Permintaan harga proyek lama; masih dipakai teknisi/admin | BDA-003/010 |
| `sales_schedules` | Agenda non-meeting; perlu dipisahkan dari kalender | BDA-018 |
| `customers.contact_person` | Cache PIC legacy; masih dipakai backfill | BDA-013 |

## 4. Gap yang Tidak Memerlukan Schema Change

| BDA | Gap | Perbaikan |
|---|---|---|
| BDA-001 | Aturan DP vs invoice penuh sebelum BAST | Validasi/guard di controller/service |
| BDA-002 | DP fleksibel | Tidak ada |
| BDA-003 | Kewenangan ubah cost | Policy/gate |
| BDA-004 | Progress manual | Hapus input/guard |
| BDA-007 | Status inti | Kolom baru; sebagian schema |
| BDA-011 | Retry nomor | Service/transaction |
| BDA-017 | Komunikasi opsional | Tidak ada |
| BDA-018 | Kalender non-meeting | Filter UI/controller |
| BDA-019 | KPI Management | Tidak ada |
| BDA-020 | Omzet dari PO | Query/KPI service |
| BDA-024 | Peringatan deadline | Command/notification |
| BDA-026 | Sync BAST | Service/observer |

## 5. Urutan Migrasi yang Disarankan (belum dieksekusi)

1. **Tanpa schema:** perbaiki guard customer/work type/force delete dan progress manual di application layer.
2. **Constraint aman:** tambahkan unique/index yang tidak mengubah data lama.
3. **Kolom baru nullable:** `project_statuses.code/is_system`, `goods_deliveries.customer_received_*`, `leads.end_user_name` bila disetujui.
4. **Tabel baru:** `audit_logs`, `proposal_payment_terms`, `goods_delivery_receipts`, `project_bast_histories` setelah desain disetujui.
5. **Migrasi data legacy:** partners→suppliers, purchase_orders→proposals, request_hargas→price_requests setelah pemetaan.
6. **Hapus/arsipkan tabel lama:** hanya setelah verifikasi nol referensi dan backup.

## 6. Risiko Migrasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Mengubah FK CASCADE ke RESTRICT | Delete yang sudah berjalan bisa gagal | Backfill/guard dulu; uji di staging |
| Unique `products.sku` | Data duplikat lama membuat migration gagal | Deteksi duplikat dan keputusan merge |
| Unique `proposals.po_number` | PO yang sah bisa duplikat karena multi-quotation | Tentukan scope sebelum constraint |
| Tabel payment terms | Invoice lama tidak punya jadwal | Nullable/backfill; jangan ubah invoice lama |
| Audit log | Volume besar | Index actor/subject/created_at; retensi |
| Migrasi supplier | Data partner dan supplier bisa konflik | Mapping table + review manual |

## 7. Kesimpulan Database Gap

Gap database yang paling mendesak bukan penambahan tabel, melainkan **menghentikan penghapusan data** dan **menyatukan sumber kebenaran**:

1. Work Type cascade (DB-001).
2. Customer force delete (DB-002).
3. Progress manual (DB-004/DB-008).
4. Audit log (DB-003).
5. Supplier/product/number uniqueness (DB-010/DB-013/DB-019).
6. Delivery receipt dan BAST history (BDA-025/026).
7. Payment term schedule (BDA-027).
