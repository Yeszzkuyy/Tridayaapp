---
name: 3DY CRM Workflow
description: "Alur bisnis CRM 3DY dari Lead sampai Invoice. Gunakan saat mengerjakan lead, follow-up, meeting, quotation (Proposal), price request, verifikasi PO, convert to project, delivery, instalasi, BAST, invoice, atau payment; saat mengubah status, gate, handoff, atau siklus customer lead menjadi deal; atau saat user melaporkan alur macet. Termasuk pertanyaan alur lead, quotation, PO, project, invoice, dan status."
metadata:
  opencode/autoinvoke: true
---

# 3DY CRM Workflow

Skill ini menjelaskan **urutan bisnis** dan gate yang tidak boleh dilanggar.
Untuk penempatan kode, gabungkan dengan `3dy-architecture`; untuk hak akses,
gabungkan dengan `3dy-security`. `AGENTS.md` tetap menang bila benturan.

Semua path relatif terhadap root repo.

## Alur end-to-end

```text
Lead masuk (Marketing)
  -> kualifikasi: follow-up, meeting, task inside sales
  -> quotation / Proposal (Sales, dibantu Price Request + Estimator)
  -> quotation accepted = Lead WON + Customer naik ke deal
  -> upload PO customer
  -> Admin verifikasi PO
  -> Convert to Project (sales/admin, hanya dari quotation accepted + PO verified)
  -> delivery barang + instalasi + progres teknisi
  -> BAST siap dan ditandatangani
  -> invoice (DP/termin/pelunasan)
  -> pembayaran customer
  -> dokumen ter-mirror di Document Center
```

Satu pintu: **Lead tidak boleh langsung menjadi Project tanpa quotation
accepted dan PO yang sudah diverifikasi.** Pengecualian hanya untuk
Admin/Teknisi yang membuat project non-quotation sesuai alur lama.

## Tahap dan penanggung jawab

| Tahap | Aktor utama | Data/kode | Gate |
|---|---|---|---|
| Lead masuk | Marketing | `Lead`, `LeadActivity`, `Customer` status `lead` | `manage-marketing` |
| Follow-up | Sales | `FollowUp` | Hanya lead miliknya, kecuali management |
| Meeting/jadwal | Sales | `SalesSchedule`, `SalesMeetingController` | Pemilik/lead yang di-assign |
| Task dan diskusi | Inside sales, marketing | `LeadTask`, `LeadTaskItem`, `LeadTaskComment`, `LeadTaskChat` | `manage-inside-sales` atau divisi terkait |
| Request harga | Sales/teknisi ke estimator | `PriceRequest`, `PriceRequestComment`, `ProductPriceLog` | `manage-price-requests` |
| Quotation | Sales | `Proposal`, `ProposalItem`, `ProposalAttachment` | `manage-sales` dan pemilik lead |
| Verifikasi PO | Admin | `Proposal.po_*`, `PurchasingHandoffController` | `manage-admin` atau `ceo` |
| Convert project | Sales/Admin | `ProposalController@convertToProject` | Quotation accepted, PO verified, belum convert |
| Delivery | Admin/gudang | `GoodsDelivery`, `GoodsDeliveryItem` | `manage-admin` |
| Instalasi/survey | Teknisi | `Survey`, `SizingProject`, `Instalasi`, `ProjectTask` | `manage-technician` |
| BAST | Teknisi/Admin | Kolom `projects.bast_*`, `BastController` | Teknisi terkait atau admin |
| Invoice | Admin | `Invoice`, `InvoiceItem` | `manage-admin` |
| Payment | Admin/finance | `Payment` | `manage-admin`; bukti bayar `view-payment-proofs` |
| Procurement | Purchasing | `SupplierPurchaseOrder`, `GoodsReceipt`, `SupplierInvoice`, `SupplierPayment` | `manage-admin` atau `purchasing` |

## Status yang dipakai

Jangan mengarang status baru. Baca konstanta/enum sebelum mengubah alur.

| Entitas | Status |
|---|---|
| `Lead` | `cool` (tampil "New"), `warm`, `hot`, `won`, `lost` |
| `Proposal` | `draft`, `pending_technical`, `ready`, `sent`, `viewed`, `revision`, `accepted`, `rejected`, `expired`, `cancelled` |
| `Project` | tabel `project_statuses`: minimal Open, On Progress, Done, Cancelled; enum `App\Enums\ProjectStatus` untuk label/warna |
| `Invoice` | `unpaid`, `partial`, `paid`, `cancelled` |
| `PriceRequest` | `waiting_lead`, `accepted`, `assigned`, `in_progress`, `waiting_sales_review`, `revision`, `completed`, `rejected`, `cancelled` |
| `TechnicalRequest` | `waiting_lead`, `accepted`, `rejected`, `assigned`, `in_progress`, `completed`, `cancelled` |
| `GoodsDelivery` | `prepared`, `shipped`, `delivered` |
| `GoodsReceipt` | `draft`, `verified` |
| `LeadTask` | `todo`, `in_progress`, `review`, `done` |

## Aturan bisnis yang tidak boleh dilanggar

1. **Quotation adalah sumber data proyek.** Item, nilai, nomor quotation, PO,
   dan deadline disalin dari `Proposal` saat convert.
2. **Lead WON saat quotation accepted.** `ProposalController@respond`
   mengubah lead menjadi `won`, mengisi `closed_at`, dan menaikkan customer
   dari `lead` ke `deal` lewat `CustomerLifecycleService`.
3. **Customer `lead` tidak muncul di menu Customer.** Jangan mengubah ini
   tanpa keputusan user.
4. **PO wajib lengkap sebelum convert.** Minimal ada file PO, nomor PO,
   `payment_scheme`, dan `po_verified_at`. PO yang dikembalikan
   (`isPoReturned`) memblokir convert sampai Sales memperbaiki.
5. **Convert idempoten.** Proposal yang sudah punya project tidak boleh
   dikonversi ulang, termasuk project yang sudah soft delete. Gunakan
   transaksi database dan tangani kegagalan notifikasi tanpa membatalkan
   project yang sudah sah.
6. **Project membawa data PT dan sales owner.** Jangan membuat project tanpa
   customer dan sales user yang jelas.
7. **Pelanggaran aturan bisnis memakai 422 dengan pesan jelas**, bukan error
   500. Log 422 sudah dicatat middleware exception.
8. **Semua perubahan status penting menulis jejak** (`LeadActivity`,
   `ProjectActivity`) dan notifikasi ke pihak terkait.
9. **Soft delete dan restore konsisten.** Customer restore ikut merestore
   project, kontak, dan dokumen. Project restore ikut merestore dokumen, task,
   dan support.
10. **Dokumen transaksi bukan dokumen teknis.** Invoice/PO/payment dikelola
    di modul Admin, bukan lewat route dokumen proyek.

## Detail tahap penting

### 1. Lead dan customer

- Lead baru menyimpan customer berstatus `lead` agar tidak mengotori menu
  Customer.
- Lead punya `assigned_to`, `pt_group`, `status`, `kebutuhan`, dan
  `incoming_date`.
- Sales biasa hanya melihat lead miliknya. Inside sales melihat lead yang
  punya task untuknya.
- Lead yang sudah `won`/`lost` tidak boleh dikonversi ulang.

### 2. Follow-up dan meeting

- Follow-up punya tanggal, catatan, dan status selesai.
- Meeting adalah follow-up bertipe meeting; punya mode Online/Offline dan
  bukti foto sesuai fitur terbaru.
- Pengingat dikirim lewat command terjadwal
  (`SendFollowUpReminders`, `SendMeetingReminders`).
- Jangan menambah perubahan status lead di form follow-up tanpa aturan jelas.

### 3. Quotation

- Model `Proposal` adalah quotation. Istilah UI memakai "Quotation".
- Status berubah dari draft ke ready/sent/viewed, lalu accepted/rejected.
- Revisi hanya dari status `sent`/`viewed`; cancel hanya dari `draft`/`ready`.
- Penawaran accepted wajib punya item yang bisa ditagih.
- Harga modal/HPP dan margin dihitung di lapisan komersial; jangan
  mencampur perhitungan teknis estimator ke tampilan sales tanpa aturan.

### 4. PO dan verifikasi

- Sales mengunggah PO setelah accepted dan setelah ada catatan follow-up.
- Admin memverifikasi lewat `PurchasingHandoffController@verifyPo`.
- Admin bisa mengembalikan PO dengan alasan; Sales wajib memperbaiki.
- Verifikasi gagal bila data PO tidak lengkap (`poCompletenessErrors`).
- Invoice menyalin nomor PO customer; jangan membuat sumber nomor kedua.

### 5. Convert to Project

- Hanya dari halaman quotation, bukan langsung dari lead (untuk sales).
- Isi minimal: work type, start date opsional, kebutuhan delivery/instalasi.
- Project membawa `proposal_id`, `quotation_number`, `delivery_deadline`,
  customer, sales owner, dan deskripsi kebutuhan.
- Setelah project dibuat, tautkan supplier PO, goods receipt, dan supplier
  invoice yang masih yatim ke project.
- Notifikasi ke lead technician dan admin handoff. Kegagalan notifikasi
  tidak boleh membatalkan project.

### 6. Delivery, instalasi, dan BAST

- Delivery punya status `prepared`, `shipped`, `delivered`.
- Goods receipt diverifikasi Admin sebelum masuk procurement.
- Survey dan sizing menghasilkan dokumen teknis.
- Instalasi dan work result memperbarui progres project.
- BAST siap setelah pekerjaan selesai; kolom `bast_ready_at`,
  `bast_signed_3dy_*`, `bast_signed_customer_*` di `projects`.
- Dokumen BAST masuk Document Center, bukan disimpan hanya di disk lokal.

### 7. Invoice dan payment

- Invoice bisa DP, termin, pelunasan, atau full.
- Status invoice berubah dari `unpaid` ke `partial` lalu `paid`; cancel
  tersedia sesuai aturan.
- Pembayaran customer mencatat nominal dan bukti; akses bukti dibatasi
  `view-payment-proofs`.
- Jangan mengubah nilai invoice yang sudah terbit tanpa jejak/atribusi.
- Sisi supplier (PO supplier, invoice supplier, payment supplier) terpisah
  dari invoice customer.

## Cara memverifikasi perubahan alur

1. Cek test yang sudah ada untuk tahap itu:
   `SalesFlowTest`, `InvoiceHandoffTest`, `InvoiceSnapshotTest`,
   `BastWorkflowTest`, `ProcurementFlowTest`, `PriceRequest*Test`.
2. Jalankan skenario manual sesuai peran:
   marketing buat lead, sales follow-up, quotation accepted, admin verifikasi
   PO, sales convert, teknisi lihat project, admin invoice, payment.
3. Pastikan customer berubah dari `lead` ke `deal` tepat setelah accepted
   atau convert, bukan lebih awal.
4. Pastikan project tidak bisa dibuat dari quotation yang belum accepted atau
   PO yang belum verified.
5. Pastikan dokumen muncul di tab Customer/Document Center yang benar.
6. Pastikan notifikasi sampai ke penerima yang tepat dan tidak membocorkan
   data ke role lain.

## Anti-pola

- Membuat project langsung dari lead untuk sales.
- Menganggap `accepted` sudah cukup tanpa verifikasi PO.
- Mengizinkan convert ulang setelah project di-soft-delete.
- Menyalin nomor PO ke tempat baru tanpa sinkron dengan `proposals.po_number`.
- Mengubah status lead di luar `ProposalController`/service resmi.
- Menghapus dokumen transaksi lewat route dokumen proyek.
- Menambahkan status string baru tanpa konstanta, label, badge, dan test.
- Membuat invoice baru tanpa memperhatikan idempotensi dan snapshot.

## File kunci

- `app/Http/Controllers/LeadController.php`
- `app/Http/Controllers/ProposalController.php`
- `app/Http/Controllers/PurchasingHandoffController.php`
- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/ProjectController.php`
- `app/Http/Controllers/BastController.php`
- `app/Services/CustomerLifecycleService.php`
- `app/Services/SalesService.php`
- `app/Services/ProcurementService.php`
- `app/Models/Lead.php`, `Proposal.php`, `Project.php`, `Invoice.php`
- `app/Observers/CustomerObserver.php`, `ProjectObserver.php`
- `docs/plans/sales/PLAN-SALES-QUOTATION.md`,
  `docs/plans/sales/PLAN-SALES-PO-DAN-CONVERT.md`,
  `docs/plans/admin/PLAN-ADMIN-PO-VERIFIKASI.md`
