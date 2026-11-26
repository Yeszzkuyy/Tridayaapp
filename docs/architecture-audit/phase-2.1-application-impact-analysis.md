# Phase 2.1 - Application Impact Analysis

**Audit:** Phase 2.1 - Business Decision Alignment
**Tanggal:** 2026-11-24
**Mode:** Read-only

## 1. Ringkasan Dampak

| Lapisan | Terdampak | Contoh |
|---|---|---|
| Model | 12+ | `Project`, `ProjectStatus`, `WorkType`, `Customer`, `Product`, `Proposal`, `Invoice`, `GoodsDelivery`, `Lead` |
| Controller | 15+ | `ProjectController`, `BastController`, `TrashController`, `AdminController`, `ProductController`, `SalesScheduleController` |
| Service | 8+ | `ProjectService`, `ProjectWorkflowService`, `AdminService`, `ProcurementService`, `ProductPriceService`, `SalesKpiService` |
| Policy/Gate | 7 | `CustomerPolicy`, `ProjectPolicy`, `ProductPolicy`, `ProposalPolicy`, `ProjectStatusPolicy`, `LeadPolicy` |
| Observer | 3 | `ProjectObserver`, `CustomerObserver`, `InstalasiObserver` |
| Route | 10+ | invoice, project, admin panel, calendar, delivery, BAST, quotation |
| Frontend | 12+ | invoice form, project form, status list, product/supplier form, calendar, KPI card, delivery detail |

## 2. Dampak per BDA

| BDA | Model | Controller / Service | Policy / Observer | Route / View | Dampak utama |
|---|---|---|---|---|---|
| BDA-001 | `Invoice`, `Proposal`, `Project` | `AdminController@invoicesCreate/store`, `AdminService@createInvoice` | - | `admin.invoices.*`, `admin/invoices/create.blade.php` | Aturan DP/BAST harus eksplisit; status invoice/project tetap terpisah |
| BDA-002 | `Proposal`, `Invoice` | `AdminController`, `ProposalController` | - | quotation/invoice form | DP percent sudah ada; tidak perlu perubahan struktur |
| BDA-003 | `Product`, `ProductPriceLog`, `ProposalItem` | `ProductController`, `ProductPolicy` | `ProductPolicy::manageCost`, `ProposalPolicy::updateCost` | product price form, quotation form | Sales harus view-only; batasi ubah cost ke Estimator/Super Admin |
| BDA-004 | `Project` | `ProjectController@update`, `ProjectService@update`, `ProjectWorkflowService` | - | `projects/edit.blade.php`, `projects/show.blade.php` | Hapus/guard input progress manual; workflow jadi sumber utama |
| BDA-005 | `Customer`, `Invoice`, `Payment`, `Lead`, `Project` | `TrashController@destroyCustomer`, `CustomerObserver` | `CustomerPolicy::forceDelete` | `trash/index.blade.php` | Force delete harus diblokir jika ada transaksi; arsip |
| BDA-006 | `WorkType`, `Project` | `WorkTypeController@destroy`, `ProjectService` | - | `admin-panel/work-types/*` | FK cascade dan hard delete harus diubah |
| BDA-007 | `ProjectStatus`, `Project` | `ProjectStatusController`, `ProjectWorkflowService`, `DashboardController` | `ProjectStatusPolicy` | `admin-panel/project-statuses/*` | Tambah `code`/`is_system`; larang edit/delete status inti |
| BDA-008 | `Supplier`, `Partner`, `Product`, `SupplierPurchaseOrder` | `SupplierController`, `PartnerController`, `SupplierPurchaseOrderController` | - | supplier/partner/product forms | Satu master; mapping partner supplier |
| BDA-009 | `Product`, `ProposalItem`, `LeadTaskItem`, `SupplierPurchaseOrderItem` | `ProductController` | `ProductPolicy` | product form/picker | Identitas produk (SKU) harus unik; jangan hapus produk terpakai |
| BDA-010 | `ProductPriceLog`, `Product`, `Supplier` | `ProductPriceService`, `ProductController@logPriceChange` | - | price history | Supplier wajib untuk log cost baru; previous price harus bisa ditampilkan |
| BDA-011 | `Proposal`, `Invoice`, `PurchaseOrder`, `SupplierPurchaseOrder`, `GoodsReceipt`, `GoodsDelivery` | `AdminService`, `ProcurementService`, `QuotationNumberService` | - | invoice/PO/GR/DO form | Unique dan retry/sequence; canceled numbers tetap ada |
| BDA-012 | `LeadActivity`, `ProjectActivity`, `DocumentActivity`; baru `AuditLog` | `AdminPanelController@auditLog` | - | `admin-panel/audit-log` | Audit global; read-only; jangan mengandalkan activity per modul |
| BDA-013 | `Customer`, `Lead`, `Project` | `CustomerAccess`, `LeadController@promoteCustomer`, `WhatsAppCenterController@convert` | `CustomerPolicy`, `LeadPolicy`, `PtAccess` | customer/lead lists | Ownership/PT harus eksplisit di backend |
| BDA-014 | `FollowUp`, `Lead` | `SalesService`, `LeadPipelineService` | - | DAR/follow-up | Tidak ada batas jumlah; loss reason sudah wajib |
| BDA-015 | - | - | - | notification settings | Ditunda; jangan implementasi SLA |
| BDA-016 | `Lead`/`Customer` | `LeadController`, `CustomerController` | - | lead/customer form | Field end-user opsional; tidak wajib |
| BDA-017 | `FollowUp`, `WhatsappMessage` | `SalesService`, `WhatsAppCenterController` | - | follow-up/WhatsApp | Pencatatan opsional; jangan jadikan gate |
| BDA-018 | `SalesSchedule`, `FollowUp` | `SalesScheduleController@getSchedulesApi` | - | `sales/schedules/index.blade.php` | Kalender harus hanya meeting; filter tipe |
| BDA-019 | `SalesTarget`, `User` | `ManageSalesController@updateTarget`, `SalesKpiService` | - | KPI/target | Management tetap pemegang target; tidak perlu role baru |
| BDA-020 | `Invoice`, `Payment`, `Proposal` | `SalesKpiService@getUserKpi` | - | dashboard/KPI | Omzet dari PO, bukan closed paid deal |
| BDA-021 | `SalesTarget` | `SalesKpiService` | - | KPI/target | Pending; jangan jadikan validasi final |
| BDA-022 | `GoodsReceipt`, `SupplierInvoice` | `GoodsReceiptController`, `SupplierInvoiceController` | - | receipt/invoice supplier | Receipt tanpa invoice sudah didukung |
| BDA-023 | `GoodsReceipt`, `GoodsReceiptItem`, `SupplierPurchaseOrderItem` | `ProcurementService` | - | goods receipt | Partial receipt sudah didukung |
| BDA-024 | `Project`, `Proposal` | `AdminService`; notification/command | - | admin hub/procurement | Butuh reminder/overdue; denda pending |
| BDA-025 | `GoodsDelivery`, `GoodsDeliveryItem`, `Project` | `GoodsDeliveryController`, `Project::evaluateCommercialClosing` | - | delivery detail | Status terima customer dan bukti belum ada |
| BDA-026 | `Project`, `ProjectDocument`, `ProjectActivity` | `BastController`, `ProjectWorkflowService`, `ProjectObserver` | - | BAST admin/teknisi | History BAST dan sync workflow |
| BDA-027 | `Proposal`, `Invoice`, `Payment` | `AdminController`, `AdminService`, `ProposalController` | - | quotation/invoice | Jadwal termin bertingkat belum ada |

## 3. Dampak Model dan Schema

### 3.1 Project

- `Project` menyimpan `progress`, `workflow_state`, `workflow_synced_at`, `commercial_closed_at`, `bast_*`.
- BDA-004 mengharuskan `progress` menjadi derived; input manual harus dihapus/di-guard.
- BDA-026 mengharuskan BAST history; `projects.bast_*` hanya menyimpan kondisi terakhir.
- BDA-025 membutuhkan status terima customer; saat ini hanya `goods_deliveries.status`.
- BDA-007 membutuhkan status inti yang stabil; `project_status_id` menunjuk tabel dinamis.

### 3.2 Customer

- `customers.status` memiliki `lead/deal/instalasi/selesai`; BDA-005/013 tidak mengubah status, tetapi mengharuskan arsip dan ownership.
- `customers.created_by`/`pt_group` kosong pada WhatsApp; harus diisi di service.
- `CustomerObserver` hanya soft-delete project/contact/document; force delete customer masih menghapus invoice/payment via FK.

### 3.3 Invoice dan Payment

- `Invoice::TYPES` sudah mendukung `dp/termin/pelunasan/full`.
- `AdminController@invoicesCreate` sudah menghitung DP dari `payment_scheme='dp'`.
- Tidak ada pengecekan BAST; BDA-001/026 harus menambahkan aturan tanpa menyamakan invoice dan project status.
- BDA-027 membutuhkan jadwal termin; `proposals.dp_percent`/`term_days` tidak cukup untuk banyak termin.
- `paid_amount/status/paid_at` adalah derived; BDA-020/023/027 meningkatkan kebutuhan konsistensi.

### 3.4 Product dan Cost

- `ProductPolicy::manageCost` mengizinkan admin + estimator; BDA-003 membatasi ke Estimator.
- `ProductPolicy::viewCost` mengizinkan sales/inside sales; sesuai BDA-003 untuk melihat.
- `product_price_logs` sudah append-only, tetapi `supplier_id` nullable dan `logPriceChange` bisa null.
- `products.sku` belum unique; BDA-009 membutuhkan identitas.

### 3.5 Supplier dan Partner

- `partners.type=supplier` dan `suppliers` terpisah; BDA-008 membutuhkan satu master.
- `leads.partner_id` vs `product_suppliers`/PO tidak terhubung.
- Jangan hapus `partners` sebelum mapping; ini bukan perubahan schema langsung.

### 3.6 Delivery dan BAST

- `GoodsDelivery::STATUSES` hanya prepared/shipped/delivered.
- BDA-025 membutuhkan status terima customer, partial, dan bukti.
- `Project::evaluateCommercialClosing` memakai `goods_deliveries.status='delivered'`, bukan customer receipt.
- BAST project single-row; BDA-026 membutuhkan history dan audit.

### 3.7 Calendar

- `SalesScheduleController@getSchedulesApi` mengambil FollowUp meeting dan `SalesSchedule` semua tipe.
- `SalesSchedule::TYPES` mencakup call/visit/presentation/follow_up/quotation/internal.
- BDA-018 hanya meeting; filter harus diubah atau tipe dibatasi.

## 4. Dampak Authorization

| BDA | Perubahan otorisasi | Bukti |
|---|---|---|
| BDA-003 | `manageCost` hanya Estimator/Super Admin; Sales tetap `viewCost` | `ProductPolicy.php:15-36`; `ProposalPolicy.php:25-38` |
| BDA-005 | Force delete customer harus ditolak jika ada transaksi | `CustomerPolicy.php:47-69`; `TrashController.php:131-139` |
| BDA-007 | Hanya Super Admin/developer untuk status inti | `ProjectStatusPolicy`; route admin panel `manage-monitoring` |
| BDA-012 | Audit log read-only untuk user biasa | `AdminPanelController@auditLog` |
| BDA-013 | Ownership/PT check di backend, termasuk `promoteCustomer` dan WhatsApp | `CustomerAccess.php`; `LeadController.php:970-984` |
| BDA-019 | Management memegang `manage-sales-leads`; tidak perlu role Sales Leader | `routes/web.php:813-825` |

## 5. Dampak Workflow dan Observer

- `ProjectObserver::created` hanya menaikkan customer `lead` ke `deal`; tidak menangani customer lifecycle `instalasi/selesai`.
- `ProjectObserver::updated` mencatat perubahan status ke `project_activities`, tetapi tidak menyimpan old/new terstruktur.
- `InstalasiObserver`, `SurveyObserver`, `SizingProjectObserver` memanggil `ProjectWorkflowService::sync`.
- `BastController` tidak memanggil `ProjectWorkflowService::sync`; BDA-026 perlu memperbaikinya.
- `AdminService::syncInvoiceStatus` memanggil `evaluateCommercialClosing`, tetapi progress bisa stale.
- BDA-020 mengubah KPI, bukan workflow; jangan mencampur omzet PO dengan cash-in.

## 6. Dampak Frontend

| Halaman | Dampak |
|---|---|
| Admin invoice create | Label/tipe DP, aturan BAST, sisa tagihan, payment terms |
| Project edit | Hapus input progress manual atau jadikan read-only |
| Project show | Progress dari workflow; BAST history |
| Admin panel project status | Tandai status inti, larang edit/delete |
| Product/price form | Sales view-only cost; estimator edit; supplier wajib |
| Supplier/partner form | Pilih master supplier; mapping |
| Calendar sales | Hanya meeting; filter tipe |
| Dashboard/KPI | Omzet PO vs cash-in; target margin pending |
| Goods delivery | Status terima customer, partial, bukti |
| Quotation | Jadwal termin; DP fleksibel |
| Trash | Blokir force delete customer bertransaksi |

## 7. Dampak Route

Tidak perlu route baru untuk BDA-001/002/003/004/005/006/007/008/009/010/011/012/013/014/016/017/018/019/020/021/022/023/024/026.
Route/endpoint baru mungkin diperlukan untuk:

- BDA-025: receipt customer/proof.
- BDA-026: BAST history.
- BDA-027: payment term schedule.
- BDA-012: audit log query (route sudah ada, view kosong).

## 8. Risiko Implementasi Aplikasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Mengubah progress manual | User kehilangan kebiasaan input | Sosialisasi + read-only UI + audit override |
| Membatasi `manageCost` | Admin/Purchasing kehilangan akses | Keputusan user + role mapping |
| Force delete guard | Admin tidak bisa membersihkan data | Sediakan arsip/anonymization |
| Kalender meeting-only | Agenda non-meeting hilang dari kalender | Pindahkan ke menu lain |
| KPI omzet PO | Angka KPI berubah | Bandingkan periode dan definisi |
| Payment terms table | Invoice lama tanpa jadwal | Nullable/backfill bertahap |
| Audit log | Volume dan performa | Index dan retensi |

## 9. Kesimpulan Dampak

Perubahan Phase 2.1 tidak boleh dimulai dari schema. Urutan yang paling aman:

1. Perbaiki guard dan policy (customer delete, work type, progress, cost).
2. Tambahkan audit dan constraint yang tidak mengubah data.
3. Tambahkan field/tabel baru secara nullable.
4. Migrasikan data legacy setelah mapping.
5. Baru ubah UI/workflow.

Tanpa urutan ini, perubahan schema berisiko menghilangkan data atau memutus alur yang sedang berjalan.
