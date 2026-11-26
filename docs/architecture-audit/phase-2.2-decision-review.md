# Phase 2.2 - Decision Review

**Audit:** Phase 2.2 - Implementation Planning
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada source code, schema, atau data yang diubah.

## 1. Sepuluh Keputusan Tambahan

| No | Keputusan | BDA terdampak | Dampak utama |
|---:|---|---|---|
| 1 | Semua invoice (DP, termin, pelunasan) boleh sebelum BAST | BDA-001, BDA-026 | BDA-001 menjadi IMPLEMENTED; BAST tetap syarat Closing, bukan syarat invoice |
| 2 | Hanya Estimator yang boleh mengubah harga modal | BDA-003 | Konflik policy: Admin/Purchasing masih bisa `manageCost` |
| 3 | Target margin minimum tetap pending | BDA-021 | Tetap PENDING; tidak menjadi validasi |
| 4 | Kalender hanya meeting Sales | BDA-018 | Konflik: kalender masih memuat agenda non-meeting |
| 5 | Penerimaan barang customer wajib status dan bukti | BDA-025 | Belum ada customer receipt status/proof |
| 6 | Jadwal termin memakai tabel khusus | BDA-027 | Perlu tabel payment terms; bukan hanya `dp_percent`/`term_days` |
| 7 | Keunikan nomor PO customer masih pending | BDA-011 | Jangan tambah unique constraint `proposals.po_number` |
| 8 | `suppliers` menjadi master supplier; `partners` dipetakan | BDA-008 | Perlu mapping data; jangan hapus `partners` |
| 9 | BAST wajib punya riwayat versi dokumen lengkap | BDA-026 | Perlu history BAST + versioned documents |
| 10 | Customer arsip tetap muncul di laporan historis | BDA-005 | Perlu archive semantics dan report scope |

## 2. Status BDA Terbaru

| Status | Jumlah | BDA |
|---|---:|---|
| IMPLEMENTED | 7 | BDA-001, BDA-002, BDA-014, BDA-017, BDA-019, BDA-022, BDA-023 |
| PARTIALLY IMPLEMENTED | 9 | BDA-007, BDA-009, BDA-010, BDA-011, BDA-013, BDA-024, BDA-025, BDA-026, BDA-027 |
| CONFLICT | 7 | BDA-003, BDA-004, BDA-005, BDA-006, BDA-008, BDA-018, BDA-020 |
| NOT IMPLEMENTED | 2 | BDA-012, BDA-016 |
| PENDING BUSINESS DECISION | 1 | BDA-021 |
| DEFERRED | 1 | BDA-015 |
| **Total** | **27** | |

**Perubahan dari Phase 2.1:**
- BDA-001: `PARTIALLY` menjadi `IMPLEMENTED` karena semua tipe invoice memang boleh sebelum BAST.
- BDA-011: konfirmasi bahwa keunikan PO customer tetap pending, sehingga `proposals.po_number` tidak boleh diberi unique constraint.
- BDA-025: kebutuhan customer receipt sekarang eksplisit; status masih `PARTIALLY` karena delivery module ada, tetapi receipt customer belum.
- BDA-026: kebutuhan riwayat BAST memperluas gap; tetap `PARTIALLY`.
- BDA-027: arah solusi sekarang jelas memakai tabel khusus; tetap `PARTIALLY`.
- BDA-005: tetap `CONFLICT`; tambahan syarat laporan historis membuat desain arsip harus eksplisit.

## 3. Matriks 27 BDA Terbaru

### 3.1 Sales, Customer, Quotation

| BDA | Keputusan | Status | Bukti | Konsekuensi |
|---|---|---|---|---|
| BDA-001 | Semua invoice DP/termin/pelunasan boleh sebelum BAST | IMPLEMENTED | `Invoice::TYPES`; `AdminController@invoicesStore` tidak memeriksa BAST (`:125-149`); `Project.php:237-266` | Tidak perlu gate BAST di invoice; BAST tetap syarat Closing |
| BDA-002 | DP fleksibel | IMPLEMENTED | `Proposal.php:16,426-433`; `ProposalController.php:278-288` | Pertahankan input persentase |
| BDA-003 | Sales lihat cost; hanya Estimator ubah | CONFLICT | `ProductPolicy.php:28-31` mengizinkan `manage-admin` + `manage-price-requests`; `ProposalPolicy.php:25-38` | Perketat policy; Sales tetap `viewCost` |
| BDA-004 | Progress otomatis | CONFLICT | `ProjectController.php:184-199`; `ProjectService.php:72-104`; `ProjectWorkflowService` | Hapus/guard progress manual |
| BDA-005 | Customer bertransaksi diarsipkan, tidak dihapus permanen; laporan historis tetap | CONFLICT | `TrashController.php:131-139`; FK finance CASCADE; laporan default exclude soft delete | Perlu archive semantics + report `withArchived`/`withTrashed`; blokir force delete |
| BDA-006 | Work Type terpakai tidak boleh dihapus | CONFLICT | `projects.work_type_id` CASCADE (`2026_06_12_090951_create_projects_table.php:26-28`); `WorkTypeController.php:52-54` | Ubah FK, tambah status aktif |
| BDA-007 | Status inti hanya Super Admin/developer | PARTIAL | `routes/web.php:923-970`; `ProjectStatusController.php:66-76` | Tambah `code`/`is_system`; larang edit/delete inti |
| BDA-008 | `suppliers` master; `partners` dipetakan | CONFLICT | `partners.type=supplier`; `suppliers`; `leads.partner_id` | Mapping data; jangan hapus partners |
| BDA-009 | Satu master produk | PARTIAL | `products`; `products.sku` hanya index | Unique SKU; produk terpakai dinonaktifkan |
| BDA-010 | Riwayat harga modal lengkap | PARTIAL | `product_price_logs`; `ProductController.php:507-524` | Supplier nullable; previous price perlu query/laporan |
| BDA-011 | Nomor dokumen unik; PO customer pending | PARTIAL | Unique dokumen utama; `proposals.po_number` tidak unique; generator read-max | Jangan unique PO sampai diputuskan; perbaiki retry/sequence |
| BDA-012 | Audit log | NOT IMPLEMENTED | `AdminPanelController.php:259-263` kosong; tidak ada tabel audit | Buat `audit_logs` |
| BDA-013 | Sales hanya data miliknya; lead assigned | PARTIAL | `CustomerAccess.php`; `LeadController.php:970-984`; WhatsApp customer tanpa owner | Perbaiki ownership dan test negatif |
| BDA-014 | Follow-up fleksibel; loss reason | IMPLEMENTED | `LeadPipelineService.php:34-42`; `FollowUp` | Pertahankan |
| BDA-015 | Pengingat follow-up otomatis | DEFERRED | Tidak ada SLA 0/15/30 | Jangan implementasi |
| BDA-016 | End-user reseller opsional | NOT IMPLEMENTED | Tidak ada field end-user | Opsional; tidak memblokir |
| BDA-017 | Riwayat komunikasi tidak wajib | IMPLEMENTED | `FollowUp` opsional; `whatsapp_messages` | Pertahankan |
| BDA-018 | Kalender hanya meeting | CONFLICT | `SalesScheduleController.php:88-92` vs `:165-218`; `SalesSchedule::TYPES` | Filter meeting-only |
| BDA-019 | Management menentukan KPI | IMPLEMENTED | `ManageSalesController.php:572-617`; `sales_targets` | Pertahankan |
| BDA-020 | Omzet dari PO | CONFLICT | `SalesKpiService.php:41-74,167-241` | Ubah achievement ke PO terverifikasi |
| BDA-021 | Target margin minimum | PENDING | `sales_targets.target_margin` ada | Jangan jadikan validasi |

### 3.2 Purchasing, Admin, Finance

| BDA | Keputusan | Status | Bukti | Konsekuensi |
|---|---|---|---|---|
| BDA-022 | Receipt tanpa invoice supplier | IMPLEMENTED | `GoodsReceiptController.php:65-110`; `PurchasingReportController` unbilled | Pertahankan |
| BDA-023 | Partial goods receipt | IMPLEMENTED | `ProcurementService.php:54-101`; `goods_receipt_items` | Pertahankan |
| BDA-024 | Deadline pengiriman dan peringatan | PARTIAL | `AdminService.php:753-765` warning <=7 hari | Tambah reminder/overdue; denda pending |
| BDA-025 | Status kirim dan terima customer + bukti | PARTIAL | `goods_deliveries.status=prepared/shipped/delivered`; `recipient_name`; tidak ada receipt customer | Tambah receipt status/proof; update closing |
| BDA-026 | BAST wajib sebelum Closing; riwayat versi lengkap | PARTIAL | `Project.php:207-266`; `BastController.php:100-149`; `project_documents` | Perlu history BAST + versioned file; sync workflow |
| BDA-027 | Variasi termin memakai tabel khusus | PARTIAL | `Proposal::PAYMENT_SCHEMES`; `dp_percent`; `term_days`; `Invoice::TYPES` | Buat `proposal_payment_terms`; relasi invoice |

## 4. Konflik yang Masih Terbuka

| BDA | Konflik | Keputusan yang sudah ada | Yang belum diputuskan |
|---|---|---|---|
| BDA-003 | Admin/Purchasing masih bisa ubah cost | Hanya Estimator | Apakah Admin/Purchasing boleh pengecualian? |
| BDA-004 | Progress manual | Harus otomatis | Apakah perlu override darurat? |
| BDA-005 | Force delete customer | Arsip + laporan historis | Representasi arsip: `archived_at` atau `deleted_at`? |
| BDA-006 | Work Type hard delete/CASCADE | Tidak boleh hapus jika terpakai | Nonaktifkan vs soft delete? |
| BDA-008 | Dua master supplier | `suppliers` utama | Atribut mapping dan kepemilikan data partner |
| BDA-018 | Kalender non-meeting | Hanya meeting | Agenda non-meeting dipindahkan ke mana? |
| BDA-020 | KPI closed paid deal | Omzet dari PO | Tanggal pengakuan PO dan perlakuan cancel/return |

## 5. Pending dan Deferred

| ID | Status | Larangan |
|---|---|---|
| BDA-015 | Deferred | Jangan membangun SLA 0/15/30 atau menganggap notifikasi berjenjang wajib |
| BDA-021 | Pending | Jangan memberi validasi/constraint margin minimum |
| BDA-011 (PO unik) | Pending | Jangan menambah unique index `proposals.po_number` sebelum keputusan scope |

## 6. Kesimpulan Decision Review

- 7 BDA sudah didukung, termasuk BDA-001 setelah keputusan invoice sebelum BAST.
- 9 BDA masih perlu pekerjaan sebagian.
- 7 BDA berkonflik dan harus diselesaikan sebelum implementasi penuh.
- 2 BDA belum ada dukungan.
- 2 BDA pending/deferred dan tidak boleh dijadikan kewajiban Phase 2.2.

Matriks ini menggantikan status Phase 2.1 untuk keperluan perencanaan. File `phase-2.1-business-alignment-matrix.md` tetap disimpan sebagai histori.
