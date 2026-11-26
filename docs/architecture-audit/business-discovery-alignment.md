# Business Discovery Alignment

**Audit:** Phase 1 lanjutan - Business Discovery vs Implementasi
**Sumber bisnis:** `docs/reference/BUSINESS-DISCOVERY-3DY-V1.md`
**Repository:** `/var/www/3dyapp`
**Branch:** `main` di `de8103b4`
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada kode, database, atau permission yang diubah.

## 1. Tujuan dan Batas

Dokumen ini membandingkan 23 aturan bisnis (BR-001 sampai BR-023), 20 keputusan terbuka (DEC-001 sampai DEC-020), dan 14 gap operasional (GAP-001 sampai GAP-014) dengan kondisi aplikasi saat ini.

Batas penting:

- Perbandingan ini melihat **kode dan struktur data**, bukan hasil wawancara ulang dengan pelaksana.
- Status "Sesuai" berarti aplikasi sudah memiliki mekanisme yang mendukung aturan tersebut, bukan berarti aturan bisnisnya sudah final.
- Status "Sebagian" berarti mekanismenya ada tetapi tidak lengkap, atau hanya berlaku pada sebagian alur.
- Status "Belum ada" berarti tidak ditemukan field, route, validasi, atau service yang menjalankan aturan tersebut.
- Status "Berbeda" berarti aplikasi menjalankan aturan yang berlawanan dengan dokumen bisnis.
- Tidak ada tes otomatis yang dijalankan pada phase ini.

## 2. Ringkasan Kesesuaian

| Status | Jumlah BR | Arti singkat |
|---|---:|---|
| Sesuai | 11 | Mekanisme utama sudah ada |
| Sebagian | 10 | Ada, tetapi belum lengkap atau belum konsisten |
| Belum ada | 2 | Belum ditemukan implementasi |
| Berbeda | 0 | Tidak ada konflik penuh; konflik harga modal dicatat sebagai "Sebagian" |

**Kesimpulan awal:** fondasi CRM sudah cukup lengkap. Kesenjangan terbesar bukan pada "apakah modulnya ada", tetapi pada **aturan yang belum ditegakkan**:

1. BAST belum menjadi syarat invoice.
2. Persentase peluang (Hot >50%) belum menjadi data.
3. Harga modal masih terlihat oleh Sales/Inside Sales di quotation.
4. Revisi quotation belum memiliki approval.
5. Retur barang dan approval jadwal teknisi belum ada.
6. SLA notifikasi lead 0/15/30 menit belum diimplementasikan.

## 3. Kesesuaian Business Rules

### BR-001 - Lead terkunci sebelum di-assign

**Status:** Sebagian

**Kondisi aplikasi:**
- Sales tidak dapat membuka lead yang belum di-assign karena `LeadPolicy::view` hanya mengizinkan sales untuk lead dengan `assigned_to = user` (`app/Policies/LeadPolicy.php:19-37`).
- Pipeline sales selalu dibatasi ke `assigned_to` user (`LeadController.php:88-93`).
- Namun tidak ada status "locked" eksplisit. Management dan Marketing tetap dapat melihat lead yang belum di-assign, dan Admin/Teknisi dapat membuka lead melalui fallback (`LeadPolicy.php:31-36`).

**Dampak:** aturan "terkunci" sudah berlaku untuk Sales, tetapi belum menjadi status formal yang dapat diaudit.

**Rekomendasi:** tambahkan status/flag assignment yang eksplisit atau dokumentasikan bahwa "terkunci" berarti "tidak terlihat oleh Sales", bukan status database.

### BR-002 - Lead yang sudah di-assign hanya diproses Sales terkait

**Status:** Sebagian

**Kondisi aplikasi:**
- Sales hanya dapat mengubah lead miliknya (`LeadPolicy.php:44-51`).
- Status lead hanya dapat diubah oleh Marketing atau Sales pemilik (`LeadController.php:144-154`).
- Namun Admin dan Teknisi dapat membuka dan mengonversi lead sebagai fallback (`LeadController.php:882-951`), dan Marketing dapat mengubah semua lead (`LeadPolicy.php:44-48`).

**Dampak:** pembatasan Sales sudah kuat, tetapi bukan pembatasan tunggal untuk seluruh role.

**Rekomendasi:** tegaskan pengecualian Admin/Teknisi dalam SOP, atau tambahkan audit reason saat fallback digunakan.

### BR-003 - Sales mencatat DAR untuk follow-up

**Status:** Sesuai

**Kondisi aplikasi:**
- `FollowUp` menyimpan customer, lead, tanggal, waktu, tipe aktivitas, deskripsi, outcome, dan rencana follow-up (`app/Models/FollowUp.php:58-90`).
- `SalesService::createFollowUp` membuat catatan, mengakui lead, dan menjadwalkan follow-up berikutnya (`SalesService.php:123-175`).
- Halaman DAR memakai `SalesDarController` dan `SalesActivityService`.

**Catatan:** field "persentase peluang" dan "nama end-user" belum ada di DAR; lihat BR-006 dan DEC-014.

### BR-004 - Follow-up pada tanggal berbeda dicatat sebagai baris baru

**Status:** Sebagian

**Kondisi aplikasi:**
- `createFollowUp` selalu membuat baris baru, kecuali submit identik dalam 10 detik (`SalesService.php:129-142`).
- Namun `FollowUpController@update` mengizinkan perubahan `follow_up_date` pada baris lama (`FollowUpController.php:203-228`).

**Dampak:** riwayat kronologis bisa berubah jika tanggal lama diedit.

**Rekomendasi:** larang perubahan tanggal pada follow-up yang sudah completed, atau paksa koreksi tanggal menjadi catatan baru.

### BR-005 - DAR wajib diperbarui sebelum tahap berikutnya

**Status:** Sebagian

**Kondisi aplikasi:**
- `LeadPipelineService` mewajibkan minimal satu follow-up sebelum masuk `warm`, `hot`, atau `won` (`LeadPipelineService.php:16-32`).
- Pembuatan quotation mewajibkan follow-up (`ProposalController.php:554-560`).
- Upload PO mewajibkan follow-up (`ProposalController.php:978-984`).

**Dampak:** aturan sudah kuat di tiga gate, tetapi tidak berlaku untuk semua perpindahan tahap (misalnya dari `cool` ke `lost`, atau perubahan status lain).

**Rekomendasi:** definisikan daftar tahap yang benar-benar memerlukan DAR, lalu pusatkan gate di satu service.

### BR-006 - Hot Prospect memiliki peluang di atas 50%

**Status:** Belum ada

**Kondisi aplikasi:**
- Tidak ada kolom `probability`, `percentage`, atau `peluang` pada `leads` (`Lead.php:155-180`).
- `opportunity_value` (nilai, bukan persentase) pernah ada di migrasi awal lalu dihapus pada `database/migrations/2026_08_24_000000_rework_leads_for_marketing.php:16`.
- Status `hot` hanya dipilih manual; tidak ada validasi angka peluang.

**Dampak:** aturan "di atas 50%" tidak dapat ditegakkan atau dilaporkan.

**Rekomendasi:** tambahkan field persentase peluang pada lead/DAR jika aturan ini final. Jika tidak, ubah definisi Hot menjadi kriteria kualitatif.

### BR-007 - Format nomor quotation

**Status:** Sebagian

**Kondisi aplikasi:**
- `QuotationNumberService` menghasilkan format `PT/SEQ/INITIALS/YEAR`, misalnya `MGK/015/AS/2026` (`QuotationNumberService.php:14-32`).
- Business Discovery menyebut `[Kode PT] / [Kode & Nomor Urut] / [Inisial Sales] / [Tahun]`. Aplikasi belum memiliki segmen "kode" terpisah.
- Kode PT yang dipakai adalah `MGK`, bukan `MBK` seperti contoh bisnis.

**Dampak:** format sudah mendekati, tetapi belum identik.

**Rekomendasi:** konfirmasi apakah "kode" adalah kode dokumen tetap atau bagian dari nomor urut. Jangan ubah generator sebelum format final disepakati.

### BR-008 - Tambahan item sebelum quotation dikirim tidak membuat revisi

**Status:** Sebagian

**Kondisi aplikasi:**
- Nomor quotation tidak berubah saat draft diedit.
- Namun setiap update tetap mencatat baris `proposal_versions` dengan label `v2`, `v3`, dan seterusnya (`QuotationVersionService.php:14-36`), dan UI menampilkannya sebagai "Revision History" (`resources/views/sales/proposals/show.blade.php:161-176`).

**Dampak:** secara administrasi nomor tetap, tetapi secara tampilan riwayat bisa disalahartikan sebagai revisi.

**Rekomendasi:** bedakan "version history internal" dan "revision issued to customer"; atau hanya tampilkan revisi setelah status `revision`.

### BR-009 - Negosiasi setelah quotation dikirim menghasilkan revisi

**Status:** Sebagian

**Kondisi aplikasi:**
- `ProposalController@revise` mengubah status menjadi `revision`, mencatat aktivitas, dan menyimpan versi (`ProposalController.php:912-922`).
- Nomor quotation induk tetap.
- Tidak ada nomor revisi formal, tidak ada approval, dan tidak ada field revisi terbit.

**Dampak:** revisi tercatat sebagai versi internal, bukan dokumen revisi resmi.

**Rekomendasi:** jika bisnis membutuhkan dokumen revisi resmi, tambahkan `revision_number`, alasan revisi, dan approval.

### BR-010 - Estimator memasukkan harga modal

**Status:** Sesuai

**Kondisi aplikasi:**
- `EstimatorRequest` dan `ProductPriceLog` menyimpan permintaan dan harga modal.
- `ProductPriceService` mencatat harga, supplier, stok, garansi, dan masa berlaku (`ProductPriceService.php:21-30`).

### BR-011 - Supplier wajib pada input harga modal baru

**Status:** Sesuai

**Kondisi aplikasi:**
- Validasi `supplier_id` wajib pada input harga (`ProductController.php:397-399`).
- `ProductPriceLog` menyimpan `supplier_id` (`ProductPriceService.php:21-27`).

**Catatan:** harga modal lama di `products.cost_price` tetap bisa ada tanpa supplier; aturan ini berlaku untuk input harga baru, bukan data historis.

### BR-012 - Harga jual diatur Sales/Inside Sales

**Status:** Sesuai

**Kondisi aplikasi:**
- `ProposalItem.unit_price` diisi dari form quotation (`ProposalController.php:336-398`).
- Inside Sales mengisi item dan harga pada `LeadTaskItem`, lalu quotation dibuat dari item tersebut (`InsideSalesQuotationController.php:174-240`).

### BR-013 - Master harga jual default hanya Admin/Purchasing/Super Admin

**Status:** Sesuai

**Kondisi aplikasi:**
- `ProductPolicy::manageSellingPrice` hanya mengizinkan `manage-admin` (`ProductPolicy.php:33-36`).
- Admin dan Purchasing memegang `manage-admin`; Super Admin melewati `Gate::before`.
- `ProductController@update` menolak perubahan `default_price` tanpa izin (`ProductController.php:323-327`).

### BR-014 - Penerimaan barang tidak menunggu invoice supplier

**Status:** Sesuai

**Kondisi aplikasi:**
- `GoodsReceipt` tidak memiliki foreign key wajib ke `SupplierInvoice`.
- Receipt dapat dibuat dari Supplier PO yang berstatus `ordered`, `partially_received`, atau `received` (`GoodsReceiptController.php:51, 78`).
- Invoice supplier dicatat terpisah (`SupplierInvoiceController.php`).

### BR-015 - Satu PO supplier dapat diterima bertahap

**Status:** Sesuai

**Kondisi aplikasi:**
- `ProcurementService::remainingQuantity` menghitung sisa dari receipt yang sudah verified (`ProcurementService.php:89-101`).
- `recalculatePurchaseOrderReceipts` mengubah status Supplier PO menjadi `partially_received` atau `received` (`ProcurementService.php:54-86`).

### BR-016 - Barang diterima tanpa invoice supplier dapat dipantau

**Status:** Sesuai

**Kondisi aplikasi:**
- Halaman laporan purchasing memiliki query `unbilled`, yaitu goods receipt verified yang belum punya supplier invoice (`PurchasingReportController.php:12-53`).

### BR-017 - Nomor dokumen duplikat ditolak

**Status:** Sebagian

**Kondisi aplikasi:**
- Unique index ada pada `proposals.proposal_number` (`database/migrations/2026_10_06_153904_create_proposals_table.php:14`), `invoices.invoice_number` (`2026_07_28_000010_create_invoices_table.php:13`), `purchase_orders.po_number` (`2026_07_28_000011_create_purchase_orders_table.php:13`), `supplier_purchase_orders.po_number` (`2026_10_27_120003_create_supplier_purchase_orders_tables.php:24`), dan `projects.bast_number` (`2026_10_27_130004_add_bast_and_closing_fields_to_projects.php:16`).
- Supplier invoice unik per supplier (`2026_10_27_130003_create_supplier_invoices_and_payments_tables.php:43`), dengan nomor internal unik (`:21`).
- Belum ada model/route "voucher" di CRM, sehingga cakupan voucher belum dapat dinilai.
- Beberapa nomor dibuat oleh generator aplikasi, bukan input manual, sehingga validasi unique terjadi di database dan retry generator.

**Dampak:** dokumen utama aman, tetapi aturan "semua nomor dokumen" belum lengkap.

**Rekomendasi:** daftarkan semua jenis dokumen yang memiliki nomor, lalu tentukan scope unik (global/per PT/per supplier).

### BR-018 - Sales tidak boleh mengakses menu Admin berisi harga modal

**Status:** Sebagian

**Kondisi aplikasi:**
- Route admin dan supplier memakai `manage-admin`, `view-admin`, atau permission sensitif; role Sales tidak memilikinya (`routes/web.php:567-695`; `RoleAndPermissionSeeder.php:97-118`).
- Namun `ProposalPolicy::viewCost` dan `ProductPolicy::viewCost` mengizinkan `manage-sales` dan `manage-inside-sales` melihat harga modal di quotation/picker (`ProposalPolicy.php:25-35`; `ProductPolicy.php:15-24`).

**Dampak:** Sales tidak bisa membuka menu Admin, tetapi masih bisa melihat HPP pada quotation. Jika aturan bisnis berarti "Sales tidak boleh melihat harga modal sama sekali", ini berbeda.

**Rekomendasi:** putuskan apakah larangan hanya berlaku pada menu Admin atau pada semua tampilan. Sesuaikan policy dan UI setelah keputusan.

### BR-019 - BAST menjadi prasyarat proses invoicing

**Status:** Belum ada

**Kondisi aplikasi:**
- `AdminController@invoicesStore` dan `AdminService::createInvoice` hanya memeriksa customer, project, dan proposal; tidak ada pengecekan BAST (`AdminController.php:125-149`; `AdminService.php:66-116`).
- Invoice dapat dibuat untuk project yang belum punya `bast_number`, `bast_ready_at`, atau tanda tangan.

**Dampak:** invoice bisa terbit sebelum BAST, sehingga aturan bisnis utama tidak ditegakkan.

**Rekomendasi:** tambahkan gate BAST untuk invoice dengan tipe tertentu, atau sediakan pengecualian yang dicatat. Ini keputusan bisnis sebelum implementasi.

### BR-020 - Closing membutuhkan instalasi, pengiriman, BAST, dan pelunasan

**Status:** Sebagian

**Kondisi aplikasi:**
- `Project::evaluateCommercialClosing` memeriksa delivery delivered, kewajiban procurement, BAST + progress/Done, dan semua invoice non-cancelled lunas (`Project.php:237-266`).
- Namun workflow progress memiliki gap: survey/sizing wajib meski project tidak membutuhkannya, dan BAST tidak memicu sync workflow (`ProjectWorkflowService.php:36-160`; `BastController.php:100-149`).
- Invoice tidak mensyaratkan BAST (BR-019).

**Dampak:** aturan closing ada, tetapi dapat gagal menutup karena progress tidak sinkron, atau invoice terbit sebelum BAST.

**Rekomendasi:** perbaiki sinkronisasi workflow dan gate invoice sebelum mengandalkan closing otomatis untuk KPI.

### BR-021 - Sales hanya melihat customer dan performanya sendiri

**Status:** Sesuai dengan catatan

**Kondisi aplikasi:**
- `CustomerAccess::queryFor` membatasi sales ke customer miliknya/lead/project/task-nya (`CustomerAccess.php:50-100`).
- `ProjectPolicy::view` membatasi sales ke project dengan `sales_user_id` sendiri (`ProjectPolicy.php:20-37`).
- `LeadPolicy::view` membatasi sales ke lead sendiri (`LeadPolicy.php:19-37`).
- Dashboard sales memakai data user sendiri (`ManageSalesController.php:177-189`).

**Catatan:** `LeadController@promoteCustomer` tidak memeriksa kepemilikan lead (`LeadController.php:970-984`), sehingga Sales dapat mengubah customer lead milik Sales lain jika mengetahui ID-nya.

**Rekomendasi:** perbaiki `promoteCustomer` sebelum menyatakan isolasi Sales selesai.

### BR-022 - Sales Leader dapat melihat performa dan detail tim

**Status:** Sesuai

**Kondisi aplikasi:**
- Route `manage-sales.*` memakai `manage-sales-leads` (`routes/web.php:813-825`).
- `ManageSalesController@showSalesRep` menampilkan dashboard dan customer milik sales tertentu (`ManageSalesController.php:195-231`).
- `teamActivity` dan `kpi` tersedia untuk leader.

### BR-023 - Pembayaran lunas memperbarui pencapaian target Sales

**Status:** Sesuai dengan catatan

**Kondisi aplikasi:**
- `SalesKpiService` menghitung revenue dari invoice yang sudah `paid` dan deal yang seluruh invoice-nya lunas (`SalesKpiService.php:167-241`).
- `AdminService::syncInvoiceStatus` memperbarui status invoice dari total pembayaran dan memanggil `evaluateCommercialClosing` (`AdminService.php:456-500`).

**Catatan:** pencapaian target memakai nilai quotation (`expectedValue`) untuk deal yang sudah lunas, bukan hanya jumlah uang masuk. Rumus ini perlu dikonfirmasi (DEC-017).

## 4. Keputusan Terbuka dan Kondisi Aplikasi

| ID | Pertanyaan bisnis | Kondisi aplikasi saat ini | Keputusan yang diperlukan |
|---|---|---|---|
| DEC-001 | Kapan customer resmi menjadi customer aktif? | Customer otomatis menjadi `deal` saat lead `warm`, `hot`, atau `won` (`CustomerLifecycleService.php:22`; `LeadController.php:958-965`) | Tentukan apakah promosi hanya saat accepted/PO/convert |
| DEC-002 | Apakah >50% satu-satunya syarat Hot? | Tidak ada field persentase peluang | Tambah field atau ubah definisi |
| DEC-003 | Bolehkah Sales melihat harga modal supplier? | Boleh di quotation/picker melalui `viewCost`; menu Admin tidak boleh | Tetapkan cakupan larangan |
| DEC-004 | Siapa boleh mengubah margin/harga jual default? | Harga jual quotation oleh Sales/Inside Sales; default price oleh `manage-admin` | Konfirmasi pembagian |
| DEC-005 | Boleh manual progress mengesampingkan workflow? | Boleh; `ProjectController@update` menerima progress/status (`:184-199`) | Hapus, batasi, atau beri audit override |
| DEC-006 | Boleh Admin/Teknisi membuat project tanpa lead Sales? | Boleh melalui `LeadController@convert` (`:882-951`) | Pertahankan dengan alasan atau hapus |
| DEC-007 | Apakah Won/PO dan Closing berbeda? | Secara data berbeda: lead `won` vs `projects.commercial_closed_at` | Tetapkan istilah UI dan laporan |
| DEC-008 | Kapan Admin boleh menarik quotation? | Handoff terbuka saat quotation `accepted` + PO terunggah (`Proposal.php:234-237`) | Konfirmasi pemicu final |
| DEC-009 | Aturan pembatalan PO supplier? | Bisa cancel dari `draft`/`ordered`, tanpa alasan/approval (`SupplierPurchaseOrderController.php:215-217`) | Tetapkan alasan/approval |
| DEC-010 | Bagaimana retur barang? | Tidak ada route/model retur | Rancang alur retur |
| DEC-011 | Bagaimana pembayaran sebagian? | Didukung; status invoice `partial` (`AdminService.php:456-500`) | Konfirmasi toleransi/termin |
| DEC-012 | Closing wajib semua invoice lunas? | Ya, semua invoice non-cancelled harus `paid` (`Project.php:255-257`) | Konfirmasi pengecualian |
| DEC-013 | Definisi nomor dokumen unik? | Unique global untuk invoice/PO/proposal/BAST; supplier invoice per supplier | Tetapkan scope resmi |
| DEC-014 | Reseller dengan banyak end-user? | Hanya `segment` (end_user/vendor/distributor/...) dan `project_name`; tidak ada entitas end-user (`LeadController.php:34`) | Rancang relasi reseller/end-user |
| DEC-015 | Siapa menyetujui revisi quotation? | Tidak ada approval; owner dapat revise (`ProposalController.php:912-922`) | Tetapkan approval |
| DEC-016 | SLA notifikasi lead 0/15/30 menit? | Push saat assignment; pengingat overdue berjalan per jam, bukan 15/30 menit | Tetapkan SLA dan kanal |
| DEC-017 | Rumus target omzet/margin? | Revenue dari closed paid deals, margin dari `gross_profit` quotation (`SalesKpiService.php:167-241`) | Konfirmasi rumus |
| DEC-018 | Jadwal teknisi perlu approval? | Tidak ada approval; `manage-technician` langsung menyimpan (`TechnicianScheduleController.php`) | Tetapkan approval |
| DEC-019 | Siapa bertanggung jawab jika BAST belum ada? | Ada daftar BAST pending di admin hub, tanpa owner/escalation (`AdminService.php:733-742`) | Tetapkan owner dan SLA |
| DEC-020 | Aturan denda keterlambatan SPK? | Hanya warning deadline <=7 hari di admin hub (`AdminService.php:753-765`); tidak ada perhitungan denda | Tetapkan rumus dan penanggung jawab |

## 5. Gap Operasional dan Kondisi Aplikasi

| ID | Gap bisnis | Kondisi aplikasi | Status |
|---|---|---|---|
| GAP-001 | Data tersebar | Database terpusat, tetapi WhatsApp/Drive masih dipakai untuk komunikasi/dokumen tertentu | Sebagian |
| GAP-002 | Input berulang Admin | Handoff quotation → admin ada; invoice masih memerlukan input/verifikasi manual | Sebagian |
| GAP-003 | Histori harga tidak terpusat | `ProductPriceLog` + price history estimator | Sesuai |
| GAP-004 | Lead berpotensi terlewat | Assignment notification + overdue reminder per jam; belum ada SLA 0/15/30 menit | Sebagian |
| GAP-005 | Riwayat DAR tersebar | `FollowUp`/DAR terpusat per lead/customer | Sesuai |
| GAP-006 | Dokumen proyek tersebar | `Document Center` + `DocumentRegistry` | Sesuai |
| GAP-007 | Nomor quotation ganda | Unique index + retry generator | Sesuai |
| GAP-008 | Penerimaan parsial sulit dipantau | Receipt parsial + recalculate status Supplier PO | Sesuai |
| GAP-009 | Invoice supplier terlambat | Laporan unbilled receipt | Sesuai |
| GAP-010 | Deadline SPK terlewat | Warning <=7 hari; belum ada denda/eskalasi | Sebagian |
| GAP-011 | Progres instalasi tidak terhubung | `ProjectWorkflowService`, tetapi ada gap sync/step | Sebagian |
| GAP-012 | Dashboard membuka data sensitif | Permission/policy ada; harga modal masih terlihat Sales di quotation | Sebagian |
| GAP-013 | Won vs Closing belum selaras | Keduanya ada sebagai data berbeda; istilah UI belum tentu konsisten | Sebagian |
| GAP-014 | Perhitungan target belum final | KPI sudah dihitung, rumus perlu keputusan | Sebagian |

## 6. Kesesuaian dengan Temuan Phase 1

| Aturan bisnis | Temuan Phase 1 yang relevan | Catatan |
|---|---|---|
| BR-019 / BR-020 | `WF-04` (BAST tidak sync), `WF-03` (survey/sizing wajib) | Perbaiki workflow sebelum menegakkan gate invoice |
| BR-018 / DEC-003 | `AUTH-06` (harga modal) | Konflik kebijakan, bukan bug teknis murni |
| BR-021 | `AUTH-01` (`promoteCustomer`) | Celah isolasi Sales |
| BR-008 / BR-009 / DEC-015 | `ARC-04` (tiga jalur quotation) | Revisi/approval perlu satu alur |
| DEC-005 | `ARC-03` / `WF-05` | Progress manual vs workflow |
| DEC-006 | `WF-02` / `ARC-05` | Project langsung tanpa quotation |
| DEC-016 | `INT-03` / notification flow | SLA belum diimplementasikan |
| DEC-014 | Tidak ada temuan Phase 1 | Kebutuhan data baru |
| DEC-010 | Tidak ada temuan Phase 1 | Kebutuhan alur baru |
| DEC-018 | Tidak ada temuan Phase 1 | Kebutuhan approval baru |

## 7. Rekomendasi Urutan Keputusan

Jangan mengubah kode sebelum keputusan bisnis berikut diambil:

1. **Definisi Closing dan BAST** (BR-019, BR-020, DEC-007, DEC-012). Ini memengaruhi invoice, KPI, dan laporan.
2. **Akses harga modal** (BR-018, DEC-003, DEC-004). Ini memengaruhi policy, UI quotation, dan laporan margin.
3. **Kapan customer menjadi aktif** (DEC-001). Ini memengaruhi menu Customer, dashboard, dan laporan.
4. **Project langsung tanpa quotation** (DEC-006). Ini memengaruhi kontrol komersial.
5. **Manual progress** (DEC-005). Ini memengaruhi closing otomatis.
6. **Approval revisi quotation** (DEC-015). Ini memengaruhi status dan audit.
7. **SLA notifikasi lead** (DEC-016). Ini memengaruhi assignment dan eskalasi.
8. **Reseller dan end-user** (DEC-014). Ini memengaruhi struktur customer/lead.
9. **Retur barang** (DEC-010). Ini memengaruhi procurement dan finance.
10. **Scope nomor unik** (DEC-013). Ini memengaruhi validasi dan database.

## 8. Hal yang Sudah Kuat

- Lead ownership untuk Sales sudah berjalan.
- DAR/follow-up tersimpan per lead/customer.
- Harga modal memiliki histori dan supplier wajib.
- Harga jual default dibatasi ke Admin/Purchasing/Super Admin.
- Goods receipt parsial dan laporan unbilled sudah ada.
- Nomor dokumen utama sudah unique.
- Closing otomatis sudah memiliki pemeriksaan delivery, procurement, BAST, dan invoice.
- Sales Leader memiliki dashboard tim dan drill-down.
- Target Sales dihitung dari invoice lunas/closed deal.

## 9. Hal yang Belum Boleh Dianggap Selesai

- BAST belum menjadi syarat invoice.
- Persentase peluang belum ada.
- Harga modal masih dapat dilihat Sales di quotation.
- Revisi quotation belum memiliki approval.
- Retur barang belum ada.
- Approval jadwal teknisi belum ada.
- SLA notifikasi lead 0/15/30 menit belum ada.
- Reseller dengan banyak end-user belum dimodelkan.
- Denda keterlambatan SPK belum dihitung.
- Istilah Won/Closing belum dipastikan konsisten di UI dan laporan.

## 10. Penutup

Dokumen bisnis ini tidak otomatis mengubah aplikasi. Dokumen ini menjadi peta kesesuaian antara kebutuhan operasional dan implementasi saat ini.

Langkah berikutnya adalah meminta keputusan bisnis untuk 10 poin pada Bagian 7, lalu membuat rencana kerja per divisi di `docs/plans/`. Belum ada perubahan kode yang dilakukan pada tahap ini.
