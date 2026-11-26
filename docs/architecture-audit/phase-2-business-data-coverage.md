# Phase 2 - Business Data Coverage

**Audit:** Phase 2 - Database Architecture
**Sumber bisnis:** `docs/reference/BUSINESS-DISCOVERY-3DY-V1.md`
**Tanggal:** 2026-11-24
**Mode:** Read-only

## 1. Ringkasan

| Kelompok | Didukung | Sebagian | Tidak ditemukan | Perlu keputusan |
|---|---:|---:|---:|---:|
| Business Rules BR-001..BR-023 | 8 | 13 | 2 | 0 |
| GAP-001..GAP-014 | 6 | 8 | 0 | 0 |
| DEC-001..DEC-020 | 1 | 1 | 5 | 13 |

**Catatan:** “Perlu keputusan” pada DEC bukan status teknis, melainkan penanda bahwa implementasi tidak boleh dianggap final sebelum bisnis memutuskan. BR-006 dan BR-019 berstatus “Tidak ditemukan” sekaligus membutuhkan keputusan bisnis.

**Koreksi dari alignment Phase 1:** audit database menemukan jalur `ProductController::logPriceChange` yang dapat menulis `product_price_logs.supplier_id` null, sehingga BR-011 diturunkan dari “Didukung” menjadi “Sebagian”. BR-021 juga diturunkan karena customer WhatsApp tidak memiliki owner/PT eksplisit dan `promoteCustomer` tidak memeriksa kepemilikan lead.

## 2. Business Rules

| ID | Aturan bisnis | Status | Bukti struktur / implementasi | Catatan |
|---|---|---|---|---|
| BR-001 | Lead terkunci sebelum di-assign | Sebagian | `leads.assigned_to`; `LeadPolicy.php:19-37` | Sales tidak melihat lead unassigned; tidak ada status “locked” eksplisit |
| BR-002 | Lead assigned hanya diproses Sales terkait | Sebagian | `LeadPolicy.php:44-51`; `LeadController.php:144-154` | Sales owner dibatasi; admin/teknisi masih bisa fallback |
| BR-003 | Sales mencatat DAR | Didukung | `follow_ups` (tanggal, tipe, outcome, next follow-up); `SalesService.php:123-175` | Data utama tersedia |
| BR-004 | Follow-up tanggal berbeda = baris baru | Sebagian | `SalesService.php:123-175`; `FollowUpController.php:203-228` | Create membuat baris baru; update masih boleh mengubah tanggal |
| BR-005 | DAR wajib sebelum tahap berikutnya | Sebagian | `LeadPipelineService.php:16-32`; `ProposalController.php:554-560, 978-984` | Gate ada di warm/hot/won, quotation, PO; tidak semua transisi |
| BR-006 | Hot = peluang >50% | Tidak ditemukan | Tidak ada kolom probability/persentase; `opportunity_value` dihapus di `2026_08_24_000000_rework_leads_for_marketing.php:16` | Hot masih status manual |
| BR-007 | Format nomor quotation PT/kode/initials/tahun | Sebagian | `QuotationNumberService.php:17-39` | Format `PT/SEQ/INITIALS/YEAR`; segmen “kode” belum ada; MGK vs MBK |
| BR-008 | Tambahan sebelum kirim tidak revisi | Sebagian | `ProposalController.php:694-775`; `QuotationVersionService.php:14-36` | Nomor tetap; setiap update tetap membuat versi `v2+` |
| BR-009 | Negosiasi setelah kirim = revisi | Sebagian | `ProposalController.php:912-922`; `proposal_versions` | Ada status `revision` dan versi; tidak ada nomor revisi/approval |
| BR-010 | Estimator memasukkan harga modal | Didukung | `estimator_requests`, `estimator_request_items`, `product_price_logs` | Histori harga append-only |
| BR-011 | Supplier wajib pada input harga modal baru | Sebagian | `ProductController.php:397-399`; `product_price_logs.supplier_id` nullable; `ProductController.php:507-524` | Jalur update harga wajib supplier; jalur `logPriceChange` bisa null |
| BR-012 | Harga jual diatur Sales/Inside Sales | Didukung | `proposal_items.unit_price`; `lead_task_items.unit_price` | Harga jual quotation fleksibel |
| BR-013 | Default selling price hanya Admin/Purchasing/Super Admin | Didukung | `ProductPolicy.php:33-36`; `ProductController.php:323-327` | Sesuai |
| BR-014 | Receipt tidak menunggu supplier invoice | Didukung | `goods_receipts` tidak wajib `supplier_invoice`; `GoodsReceiptController.php:65-110` | Invoice terpisah |
| BR-015 | Satu supplier PO dapat diterima bertahap | Didukung | `ProcurementService.php:54-101`; `goods_receipt_items` | Status `partially_received` |
| BR-016 | Barang diterima tanpa invoice dapat dipantau | Didukung | `PurchasingReportController.php:18-53` | Query `unbilled` |
| BR-017 | Nomor dokumen duplikat ditolak | Sebagian | Unique: proposal/invoice/PO/supplier PO/GR/DO/BAST; `proposals.po_number` tidak unique | Voucher tidak dimodelkan; customer PO number bisa ganda |
| BR-018 | Sales tidak boleh akses menu Admin berisi harga modal | Sebagian | Route admin `manage-admin/view-admin`; `ProposalPolicy.php:25-35`; `ProductPolicy.php:15-24` | Menu tertutup; harga modal tetap terlihat di quotation/picker |
| BR-019 | BAST prasyarat invoicing | Tidak ditemukan | `AdminService.php:66-116`; `AdminController.php:125-149` | Tidak ada pengecekan BAST saat invoice dibuat |
| BR-020 | Closing = instalasi + delivery + BAST + pelunasan | Sebagian | `Project.php:237-266`; `ProjectWorkflowService.php:36-160` | Pemeriksaan ada; workflow progress/BAST sync masih rapuh |
| BR-021 | Sales hanya melihat customer/performanya | Sebagian | `CustomerAccess.php:50-100`; `ProjectPolicy.php:20-37`; `LeadController.php:970-984` | Scope Sales ada; `promoteCustomer` tidak cek owner/PT |
| BR-022 | Sales Leader melihat tim | Didukung | `routes/web.php:813-825`; `ManageSalesController.php:195-231` | Dashboard/drill-down leader |
| BR-023 | Pembayaran lunas memperbarui target Sales | Sebagian | `SalesKpiService.php:167-241`; `AdminService.php:456-500` | KPI memakai closed/paid deal dan expected value; formula belum final |

### 2.1 Rekonsiliasi status BR

- **Didukung (8):** BR-003, BR-010, BR-012, BR-013, BR-014, BR-015, BR-016, BR-022.
- **Sebagian (13):** BR-001, BR-002, BR-004, BR-005, BR-007, BR-008, BR-009, BR-011, BR-017, BR-018, BR-020, BR-021, BR-023.
- **Tidak ditemukan (2):** BR-006, BR-019.

## 3. Operational Gaps

| ID | Gap bisnis | Status | Bukti / kondisi database | Catatan |
|---|---|---|---|---|
| GAP-001 | Data tersebar | Sebagian | Database CRM terpusat; WhatsApp/Drive tetap dipakai | Tidak semua dokumen masuk registry |
| GAP-002 | Input berulang Admin | Sebagian | Handoff quotation ada; invoice item tidak tertaut ke quotation item | Masih ada pengetikan ulang item |
| GAP-003 | Histori harga tidak terpusat | Didukung | `product_price_logs`, `estimator_request_items` | Append-only |
| GAP-004 | Lead berpotensi terlewat | Sebagian | Assignment notification + overdue reminder per jam | Tidak ada SLA 0/15/30 menit |
| GAP-005 | Riwayat DAR tersebar | Didukung | `follow_ups` per lead/customer | Histori terpusat |
| GAP-006 | Dokumen proyek tersebar | Didukung | `project_documents`, `document_activities` | Registry + audit dokumen |
| GAP-007 | Nomor quotation ganda | Didukung | `proposals.proposal_number` unique; retry generator | Nomor utama aman |
| GAP-008 | Penerimaan parsial sulit dipantau | Didukung | `goods_receipts`, `goods_receipt_items`, `quantity_received` | Partial receipt didukung |
| GAP-009 | Invoice supplier terlambat | Didukung | `PurchasingReportController` `unbilled` | Laporan tersedia |
| GAP-010 | Deadline SPK terlewat | Sebagian | `AdminService.php:753-765` warning <=7 hari | Tidak ada denda/eskalasi |
| GAP-011 | Progres instalasi tidak terhubung | Sebagian | `ProjectWorkflowService`; `projects.workflow_state` JSON | Sync BAST/progress masih rapuh |
| GAP-012 | Dashboard membuka data sensitif | Sebagian | Policy/middleware ada; `viewCost` sales; WhatsApp customer unowned | Perlu keputusan akses harga |
| GAP-013 | Won vs Closing belum selaras | Sebagian | `leads.status=won` vs `projects.commercial_closed_at` | Dua konsep data berbeda; UI belum tentu konsisten |
| GAP-014 | Perhitungan target belum final | Sebagian | `SalesKpiService`; `sales_targets` | Rumus perlu keputusan |

### 3.1 Rekonsiliasi GAP

- **Didukung (6):** GAP-003, GAP-005, GAP-006, GAP-007, GAP-008, GAP-009.
- **Sebagian (8):** GAP-001, GAP-002, GAP-004, GAP-010, GAP-011, GAP-012, GAP-013, GAP-014.
- **Tidak ditemukan (0).**

## 4. Open Business Decisions

| ID | Pertanyaan bisnis | Status data | Kondisi database | Keputusan yang diperlukan |
|---|---|---|---|---|
| DEC-001 | Kapan customer menjadi aktif? | Perlu keputusan | `customers.status` check `lead/deal/instalasi/selesai`; auto-promote warm | Tetapkan titik promosi |
| DEC-002 | Apakah >50% satu-satunya syarat Hot? | Perlu keputusan | Tidak ada field persentase | Tambah field atau ubah definisi |
| DEC-003 | Bolehkah Sales melihat harga modal? | Perlu keputusan | `proposal_items.unit_cost`; `viewCost` sales | Tetapkan cakupan |
| DEC-004 | Siapa boleh ubah margin/default price? | Perlu keputusan | `default_price` admin; margin quotation sales | Tetapkan pembagian |
| DEC-005 | Manual progress boleh override workflow? | Perlu keputusan | `projects.progress` + `workflow_state` | Tetapkan authority |
| DEC-006 | Admin/Teknisi boleh buat project tanpa lead? | Perlu keputusan | `projects.proposal_id` nullable; fallback route | Tetapkan pengecualian |
| DEC-007 | Won/PO dan Closing berbeda? | Perlu keputusan | `leads.status=won`; `projects.commercial_closed_at` | Tetapkan istilah dan laporan |
| DEC-008 | Kapan Admin menarik quotation? | Perlu keputusan | Handoff `accepted` + `po_document_path` | Tetapkan pemicu |
| DEC-009 | Aturan cancel Supplier PO? | Perlu keputusan | `supplier_purchase_orders.status`; cancel dari draft/ordered | Tetapkan alasan/approval |
| DEC-010 | Retur barang? | Tidak ditemukan | Tidak ada tabel/route retur | Rancang alur retur |
| DEC-011 | Pembayaran sebagian? | Didukung | `payments`; `invoices.status=partial`; `supplier_payments` | Konfirmasi termin/toleransi |
| DEC-012 | Closing wajib semua invoice lunas? | Perlu keputusan | `Project.php:255-257` semua invoice non-cancelled harus paid | Tetapkan pengecualian |
| DEC-013 | Definisi nomor dokumen unik? | Perlu keputusan | Unique global/per supplier; `proposals.po_number` tidak unique | Tetapkan scope |
| DEC-014 | Reseller dengan banyak end-user? | Tidak ditemukan | Hanya `leads.segment`; tidak ada relasi end-user | Rancang model |
| DEC-015 | Siapa menyetujui revisi quotation? | Perlu keputusan | `proposal_versions`; tidak ada approval | Tetapkan approval |
| DEC-016 | SLA notifikasi lead 0/15/30? | Tidak ditemukan | Assignment push + overdue reminder per jam | Tetapkan SLA |
| DEC-017 | Rumus target omzet/margin? | Perlu keputusan | `sales_targets`; `SalesKpiService` | Tetapkan rumus |
| DEC-018 | Jadwal teknisi perlu approval? | Tidak ditemukan | `technician_schedules` tanpa approval | Tetapkan approval |
| DEC-019 | Siapa bertanggung jawab jika BAST belum ada? | Sebagian | `AdminService.php:733-742` daftar BAST pending | Tetapkan owner/SLA |
| DEC-020 | Denda keterlambatan SPK? | Tidak ditemukan | Hanya `delivery_deadline` dan warning | Tetapkan rumus |

### 4.1 Rekonsiliasi DEC

- **Didukung (1):** DEC-011.
- **Sebagian (1):** DEC-019.
- **Tidak ditemukan (5):** DEC-010, DEC-014, DEC-016, DEC-018, DEC-020.
- **Perlu keputusan (13):** DEC-001, DEC-002, DEC-003, DEC-004, DEC-005, DEC-006, DEC-007, DEC-008, DEC-009, DEC-012, DEC-013, DEC-015, DEC-017.

## 5. Area yang Tidak Dapat Dipetakan ke Database

| Area | Alasan |
|---|---|
| Retur barang | Tidak ada tabel, kolom, route, atau model retur |
| Reseller/end-user | Tidak ada entitas atau foreign key end-user |
| SLA notifikasi 0/15/30 menit | Tidak ada tabel jadwal/SLA; hanya push dan reminder per jam |
| Denda keterlambatan SPK | Tidak ada kolom nilai denda atau perhitungan |
| Approval jadwal teknisi | Tidak ada status approval pada `technician_schedules` |
| Approval revisi quotation | Tidak ada status approval pada `proposal_versions` |
| Persentase peluang | Tidak ada kolom probability |
| Company/PT master | PT hanya string; tidak ada tabel `companies` |

## 6. Kesimpulan Coverage

Struktur database sudah mendukung fondasi CRM: customer, lead, DAR, quotation, harga modal, procurement parsial, project, BAST, invoice, dan payment. Yang belum didukung bukan terutama “tabel kurang”, tetapi **aturan bisnis yang belum memiliki data atau constraint**:

1. BAST belum menjadi syarat invoice.
2. Persentase peluang belum ada.
3. Approval revisi/jadwal belum ada.
4. Retur dan end-user reseller belum dimodelkan.
5. SLA dan denda belum dimodelkan.
6. Sebagian aturan hanya dijaga aplikasi, bukan database.
