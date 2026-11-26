# Phase 2.1 - Business Alignment Matrix

**Audit:** Phase 2.1 - Business Decision Alignment
**Tanggal:** 2026-11-24
**Mode:** Read-only

## 1. Ringkasan Status

| Status | Jumlah | BDA |
|---|---:|---|
| IMPLEMENTED | 6 | BDA-002, BDA-014, BDA-017, BDA-019, BDA-022, BDA-023 |
| PARTIALLY IMPLEMENTED | 10 | BDA-001, BDA-007, BDA-009, BDA-010, BDA-011, BDA-013, BDA-024, BDA-025, BDA-026, BDA-027 |
| CONFLICT | 7 | BDA-003, BDA-004, BDA-005, BDA-006, BDA-008, BDA-018, BDA-020 |
| NOT IMPLEMENTED | 2 | BDA-012, BDA-016 |
| PENDING BUSINESS DECISION | 1 | BDA-021 |
| DEFERRED | 1 | BDA-015 |
| **Total** | **27** | |

## 2. Matriks 27 Keputusan

| BDA | Keputusan | Status | Bukti utama | Gap / konflik | Rekomendasi |
|---|---|---|---|---|---|
| BDA-001 | Invoice DP boleh sebelum BAST; invoice dan project status dipisah | Sebagian | `AdminController.php:83-101` menyiapkan DP dari quotation; `invoicesStore` tidak memeriksa BAST (`:125-149`); `Project.php:237-266` closing | DP dan invoice umum dapat terbit sebelum BAST; tidak ada aturan yang membedakan DP vs invoice penuh sebelum BAST | Tambahkan aturan eksplisit: DP boleh sebelum BAST, invoice penuh/pelunasan mengikuti persyaratan yang disepakati. Jangan mengubah schema |
| BDA-002 | Persentase DP fleksibel | Sudah | `Proposal.php:16,426-433`; `ProposalController.php:278-288`; `AdminController.php:89-92` | `dp_percent` 1-99, nominal dihitung dari grand total | Tidak ada perubahan schema; pertahankan input persentase |
| BDA-003 | Sales boleh lihat harga modal, tidak boleh ubah; Estimator yang mengubah | Konflik | `ProposalPolicy.php:25-38`; `ProductPolicy.php:15-36`; `ProductController.php:397-399` | Sales dapat melihat (sesuai), tetapi Admin/Purchasing juga dapat mengubah `manageCost`; keputusan menyebut Estimator | Batasi policy ubah harga modal ke Estimator (dan Super Admin), atau dokumentasikan pengecualian Admin/Purchasing |
| BDA-004 | Progress project otomatis; tidak boleh manual | Konflik | `ProjectController.php:184-199` menerima `progress`; `ProjectService.php:72-104` menulis; `ProjectWorkflowService` menghitung | Dua sumber progress; user dapat mengubah persentase | Hapus/guard input manual; jadikan workflow satu-satunya sumber; tambahkan audit override jika tetap diizinkan |
| BDA-005 | Customer bertransaksi tidak boleh dihapus permanen | Konflik | `TrashController.php:131-139` `forceDelete`; FK `invoices.customer_id`/`payments` CASCADE; `CustomerPolicy.php:47-69` | Customer dapat dihapus permanen beserta invoice/payment | Blokir force delete jika ada transaksi; sediakan arsip; ubah FK finansial menjadi RESTRICT/SET NULL |
| BDA-006 | Work Type terpakai tidak boleh dihapus permanen | Konflik | `projects.work_type_id` CASCADE (`2026_06_12_090951_create_projects_table.php:26-28`); `WorkTypeController.php:52-54` | Hapus Work Type menghapus project | Ubah FK ke RESTRICT/SET NULL; tambahkan `is_active`/soft delete; blokir delete |
| BDA-007 | Status inti hanya Super Admin/developer | Sebagian | Route admin panel `manage-monitoring` (`routes/web.php:923-970`); `ProjectStatusController.php:66-76` | Hanya super-admin yang bisa akses, tetapi tidak ada pemisahan status inti vs custom; rename/delete tetap bisa | Tambahkan `code`/`is_system`; larang rename/delete status inti; unique name/default |
| BDA-008 | Satu master supplier | Konflik | `partners.type=supplier`; `suppliers` terpisah; `leads.partner_id` vs `product_suppliers`/PO | Dua sumber supplier; tidak ada relasi | Tetapkan `suppliers` sebagai master; petakan partner supplier; jangan hapus tabel lama sebelum migrasi |
| BDA-009 | Satu master produk dan identitas konsisten | Sebagian | `products` dipakai quotation, lead task, price log, PO; `products.sku` hanya index | Satu tabel, tetapi SKU tidak unique; produk nonaktif tidak dihapus | Tambahkan unique `sku` sesuai scope; pertahankan penonaktifan produk |
| BDA-010 | Riwayat harga modal: produk, supplier, harga lama/baru, waktu, user | Sebagian | `product_price_logs` (product, supplier, price, timestamps, updated_by); `ProductPriceService` append-only | `supplier_id` nullable pada `logPriceChange`; harga “sebelum” tidak eksplisit | Wajibkan supplier untuk log cost baru; sediakan previous price di query/laporan; jangan ubah histori lama |
| BDA-011 | Nomor dokumen unik, tidak dipakai ulang, aman concurrent | Sebagian | Unique proposal/invoice/PO/supplier PO/GR/DO/BAST; `QuotationNumberService` retry; `AdminService`/`ProcurementService` read-max | `proposals.po_number` tidak unique; generator invoice/GR/DO/SINV tanpa retry | Tambahkan unique PO customer setelah scope; sentralisasi generator/retry |
| BDA-012 | Audit log aktivitas penting | Belum | `AdminPanelController.php:259-263` paginator kosong; tidak ada tabel audit global | Hanya activity log per modul; invoice/payment/PO/role tidak tercatat | Buat tabel `audit_logs`; instrumentasi model/event; read-only UI |
| BDA-013 | Sales hanya customer miliknya; lead harus assigned | Sebagian | `CustomerAccess.php:50-100`; `LeadPolicy.php:19-37`; `LeadController.php:970-984`; WhatsApp customer no owner | `promoteCustomer` tanpa cek owner; customer WhatsApp tanpa `created_by`/`pt_group` | Perbaiki ownership/PT; test negatif lintas Sales/PT |
| BDA-014 | Follow-up fleksibel; loss reason dicatat | Sudah | `FollowUp`; `LeadPipelineService.php:34-42`; `SalesDarController` | Tidak ada batas jumlah follow-up; loss wajib alasan dan catatan | Pertahankan; pastikan monitoring leader |
| BDA-015 | Pengingat follow-up otomatis | Ditunda | Tidak ada SLA 0/15/30; assignment push + overdue hourly | Keputusan bisnis menunda | Jangan implementasi sebagai kewajiban Phase 2.1 |
| BDA-016 | End-user reseller opsional | Belum | Tidak ada kolom end-user; hanya `leads.segment` dan `project_name` | Informasi end-user tidak dapat disimpan terstruktur | Tambahkan kolom nullable `end_user_name`/catatan jika prioritas; tidak wajib |
| BDA-017 | Riwayat komunikasi tidak wajib | Sudah | `FollowUp` opsional; `whatsapp_messages`; activity log | Tidak ada kewajiban mencatat semua komunikasi | Pertahankan; jangan jadikan gate wajib |
| BDA-018 | Kalender hanya meeting | Konflik | `SalesScheduleController.php:88-92` filter meeting, tetapi `:165-218` menggabungkan `SalesSchedule` tipe call/visit/presentation/follow_up/quotation/internal | Kalender memuat agenda non-meeting | Pisahkan kalender meeting atau batasi `SalesSchedule::TYPES`; sesuai keputusan |
| BDA-019 | Management menentukan KPI/target | Sudah | `ManageSalesController.php:572-617`; route `manage-sales-leads`; `sales_targets` | Tidak ada role Sales Leader khusus; management memegang permission | Pertahankan; dokumentasikan ownership |
| BDA-020 | Omzet dihitung saat PO resmi diterima | Konflik | `SalesKpiService.php:41-74` menghitung verified PO revenue, tetapi `actualRevenue` memakai closed paid deal (`:59-68,167-241`) | Pencapaian target tidak memakai nilai PO | Ubah KPI achievement ke PO terverifikasi, atau pisahkan “omzet PO” dan “cash-in” secara eksplisit |
| BDA-021 | Target margin minimum | Pending | `sales_targets.target_margin` ada; tidak ada angka minimum | Belum final | Jangan jadikan validasi final; tunggu Management |
| BDA-022 | Receipt tanpa invoice supplier | Sudah | `GoodsReceiptController.php:65-110`; `PurchasingReportController` unbilled | Receipt tidak menunggu invoice | Pertahankan |
| BDA-023 | Partial goods receipt | Sudah | `ProcurementService.php:54-101`; `goods_receipt_items`; status partial | Didukung | Pertahankan |
| BDA-024 | Deadline pengiriman dan peringatan | Sebagian | `Project.delivery_deadline`; `AdminService.php:753-765` warning <=7 hari | Belum ada notifikasi/eskalasi otomatis; denda belum diputuskan | Tambahkan reminder/overdue queue; jangan hitung denda sebelum keputusan |
| BDA-025 | Status kirim dan terima customer berbeda | Sebagian | `goods_deliveries.status=prepared/shipped/delivered`; `recipient_name`; tidak ada status partial/received customer | “Delivered” tidak membuktikan customer menerima; tidak ada bukti penerimaan terstruktur | Tambahkan status/kolom receipt customer atau tabel receipt; bukti penerimaan |
| BDA-026 | BAST wajib sebelum Closing | Sebagian | `Project.php:207-266`; `BastController.php:100-149`; `projects.bast_*` | BAST ready/closing ada, tetapi workflow sync dan history BAST lemah | Tambahkan BAST history/audit; sync workflow setelah BAST; closing tetap butuh BAST |
| BDA-027 | Variasi termin pembayaran | Sebagian | `Proposal::PAYMENT_SCHEMES=['full','dp','tempo']`; `dp_percent`, `term_days`; `Invoice::TYPES`; `payments` | Tidak ada jadwal termin bertingkat; `term_days` tunggal | Tambahkan tabel jadwal termin jika variasi >1 termin disetujui; pertahankan payment partial |

## 3. Konflik Utama

### 3.1 BDA-003 - Siapa yang boleh mengubah harga modal

Keputusan: Sales boleh melihat, tidak boleh mengubah; Estimator bertanggung jawab.
Implementasi: `ProductPolicy::manageCost` mengizinkan `manage-admin` **dan** `manage-price-requests` (`ProductPolicy.php:28-31`). Sales tidak mendapat `manageCost`, tetapi Admin/Purchasing juga bisa mengubah. Ini bukan kebocoran Sales, tetapi melebarkan kewenangan di luar keputusan.

**Dampak:** Admin/Purchasing dapat mengubah harga modal tanpa peran Estimator.

### 3.2 BDA-004 - Progress otomatis

Keputusan: progress hanya dari workflow.
Implementasi: `ProjectController@update` menerima `progress` manual (`:184-199`), `ProjectService` menulisnya (`:72-104`), sementara `ProjectWorkflowService` juga menghitung progress. Dua sumber.

**Dampak:** progress 100% manual dapat menyesatkan closing/KPI.

### 3.3 BDA-005 / BDA-006 - Data loss

Keputusan: customer bertransaksi dan Work Type terpakai tidak boleh dihapus permanen.
Implementasi: customer `forceDelete` mengalir ke invoice/payment; Work Type `delete` mengalir ke project karena CASCADE.

**Dampak:** kehilangan riwayat transaksi/project. Ini prioritas Critical.

### 3.4 BDA-018 - Kalender

Keputusan: kalender hanya meeting.
Implementasi: kalender menggabungkan `FollowUp` meeting dengan `SalesSchedule` yang memiliki tipe call, visit, presentation, follow_up, quotation, internal (`SalesScheduleController.php:165-218`; `SalesSchedule.php:10-12`).

**Dampak:** kalender menampilkan agenda non-meeting.

### 3.5 BDA-020 - Omzet dari PO

Keputusan: omzet dihitung saat PO resmi diterima.
Implementasi: `SalesKpiService` menyimpan `verifiedPoRevenue`, tetapi `actualRevenue` untuk pencapaian target diambil dari closed paid deal (`SalesKpiService.php:41-74, 167-241`).

**Dampak:** pencapaian omzet Sales berbeda dari definisi bisnis.

## 4. Keputusan yang Sudah Didukung

- BDA-002 DP fleksibel.
- BDA-014 follow-up fleksibel dan loss reason.
- BDA-017 pencatatan komunikasi opsional.
- BDA-019 Management menentukan target.
- BDA-022 receipt tanpa invoice supplier.
- BDA-023 partial receipt.

## 5. Pending dan Deferred

| ID | Status | Perlakuan |
|---|---|---|
| BDA-015 | Ditunda | Jangan implementasi SLA 0/15/30; pertahankan notifikasi yang ada |
| BDA-021 | Pending | Jangan menetapkan margin minimum; `sales_targets.target_margin` tetap informatif |

## 6. Catatan

- Status “Sudah” berarti mekanisme utama ada, bukan berarti seluruh edge case tertutup.
- Status “Sebagian” berarti ada data/route tetapi alur belum lengkap.
- Status “Konflik” berarti implementasi saat ini berlawanan dengan keputusan bisnis.
- Status “Belum” berarti tidak ditemukan dukungan yang cukup.
- BDA-015 dan BDA-021 tidak dihitung sebagai gap implementasi saat ini.
