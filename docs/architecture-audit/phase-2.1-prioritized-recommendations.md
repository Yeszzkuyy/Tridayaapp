# Phase 2.1 - Prioritized Recommendations

**Audit:** Phase 2.1 - Business Decision Alignment
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada implementasi yang dilakukan.

## 1. Prinsip Prioritas

1. **CRITICAL** - hentikan risiko kehilangan data sebelum perubahan lain.
2. **HIGH** - perbaiki konflik yang mengganggu proses bisnis atau integritas.
3. **MEDIUM** - satukan data/constraint dan rapikan alur.
4. **LOW** - penyempurnaan dan maintainability.
5. **PENDING/DEFERRED** - jangan dijadikan kewajiban implementasi.

## 2. Ringkasan Rekomendasi

| Prioritas | Jumlah | Fokus |
|---|---:|---|
| Critical | 2 | Customer force delete, Work Type cascade |
| High | 11 | Progress, audit, status inti, cost, supplier, KPI, delivery, payment terms, numbering, ownership, BAST |
| Medium | 7 | DP/BAST rule, product identity, price history, calendar, deadline, integrity bundle, legacy mapping |
| Low | 2 | End-user opsional, schema cleanup |
| Pending/Deferred | 2 | Target margin, reminder SLA |

## 3. Critical

### R-01 - Blokir force delete customer bertransaksi

| Field | Isi |
|---|---|
| BDA | BDA-005 |
| Audit terkait | DB-002; AUTH-01 |
| Kondisi saat ini | `TrashController@destroyCustomer` memanggil `forceDelete`; FK `invoices.customer_id` dan `payments.invoice_id` CASCADE |
| Kondisi diharapkan | Customer dengan invoice/payment/quotation/PO tidak dapat dihapus permanen; hanya arsip |
| Dampak bisnis | Mencegah hilangnya riwayat finansial dan bukti pembayaran |
| Komponen | `TrashController`, `CustomerPolicy`, `CustomerObserver`, `ProjectDocumentCleanup`, FK finance |
| Rekomendasi | Guard di policy/controller: hitung invoice/payment/proposal/PO; blokir atau arahkan ke arsip. Ubah FK finance menjadi RESTRICT/SET NULL setelah migrasi |
| Risiko | Admin kehilangan cara membersihkan data; perlu arsip/anonymization |
| Dependensi | Keputusan retensi finance; audit log minimal |
| Prioritas | CRITICAL |

### R-02 - Lindungi Work Type dari penghapusan

| Field | Isi |
|---|---|
| BDA | BDA-006 |
| Audit terkait | DB-001 |
| Kondisi saat ini | `projects.work_type_id` CASCADE; `WorkTypeController@destroy` hard delete |
| Kondisi diharapkan | Work Type terpakai tidak bisa dihapus; dapat dinonaktifkan; project lama tetap terhubung |
| Dampak bisnis | Mencegah kehilangan seluruh project dan riwayat pekerjaan |
| Komponen | `WorkTypeController`, `WorkType`, `projects` FK, `ProjectService` |
| Rekomendasi | Ubah FK ke RESTRICT/SET NULL; tambah `is_active` atau soft delete; guard delete; backfill status aktif |
| Risiko | Delete yang sudah berjalan bisa gagal; perlu uji staging |
| Dependensi | Keputusan apakah Work Type boleh SET NULL atau harus RESTRICT |
| Prioritas | CRITICAL |

## 4. High

### R-03 - Jadikan workflow satu-satunya sumber progress

| Field | Isi |
|---|---|
| BDA | BDA-004 |
| Audit terkait | DB-004, DB-008, ARC-03, WF-05 |
| Kondisi saat ini | `ProjectController@update` menerima `progress`; `ProjectService` menulis; workflow juga menghitung |
| Kondisi diharapkan | Progress hanya dari `ProjectWorkflowService`; user tidak dapat mengubah persentase |
| Dampak bisnis | Closing/KPI tidak bisa dimanipulasi dengan progress manual |
| Komponen | `ProjectController`, `ProjectService`, `ProjectWorkflowService`, `projects` view |
| Rekomendasi | Hapus input progress dari form; guard request; tambahkan test bahwa update progress manual ditolak |
| Risiko | User kehilangan kebiasaan input; perlu sosialisasi |
| Dependensi | R-04 audit; R-13 BAST sync |
| Prioritas | HIGH |

### R-04 - Bangun audit log global

| Field | Isi |
|---|---|
| BDA | BDA-012 |
| Audit terkait | DB-003 |
| Kondisi saat ini | `AdminPanelController@auditLog` mengembalikan paginator kosong; hanya activity per modul |
| Kondisi diharapkan | Audit log mencatat user, waktu, modul, aksi, old/new values; read-only |
| Dampak bisnis | Investigasi perubahan nominal/status/dokumen dapat dipertanggungjawabkan |
| Komponen | Tabel `audit_logs`, model, service/event, `AdminPanelController`, view |
| Rekomendasi | Desain tabel audit immutable; instrumentasi invoice/payment/PO/receipt/delivery/BAST/role/permission; UI read-only |
| Risiko | Volume besar, performa, dan data sensitif |
| Dependensi | Keputusan retensi dan redaksi data |
| Prioritas | HIGH |

### R-05 - Pisahkan status inti dan status custom

| Field | Isi |
|---|---|
| BDA | BDA-007 |
| Audit terkait | DB-004, DB-013 |
| Kondisi saat ini | `project_statuses` hanya name/color/default; semua status dapat diedit/dihapus super-admin |
| Kondisi diharapkan | Status inti stabil, tidak dapat di-rename/delete; status custom tetap bisa |
| Dampak bisnis | Workflow tidak rusak karena perubahan label |
| Komponen | `project_statuses`, `ProjectStatusController`, `ProjectWorkflowService`, `ProjectStatusPolicy` |
| Rekomendasi | Tambah `code` unik dan `is_system`; larang edit/delete status inti; unique name/default |
| Risiko | Migrasi nama status lama; cache `master:project-statuses` |
| Dependensi | R-03 progress |
| Prioritas | HIGH |

### R-06 - Batasi ubah harga modal ke Estimator

| Field | Isi |
|---|---|
| BDA | BDA-003 |
| Audit terkait | AUTH-06, DB-008 |
| Kondisi saat ini | `ProductPolicy::manageCost` mengizinkan `manage-admin` dan `manage-price-requests` |
| Kondisi diharapkan | Sales view-only; Estimator mengubah; Admin/Purchasing hanya jika disetujui sebagai pengecualian |
| Dampak bisnis | Kewenangan harga sesuai tanggung jawab |
| Komponen | `ProductPolicy`, `ProposalPolicy`, `ProductController`, `ProposalController` |
| Rekomendasi | Pisahkan permission `view_cost` dan `manage_cost`; batasi manage ke Estimator/Super Admin; dokumentasikan pengecualian |
| Risiko | Admin/Purchasing kehilangan akses yang sudah dipakai |
| Dependensi | Keputusan Management |
| Prioritas | HIGH |

### R-07 - Satukan master supplier

| Field | Isi |
|---|---|
| BDA | BDA-008 |
| Audit terkait | DB-010 |
| Kondisi saat ini | `partners.type=supplier` dan `suppliers` terpisah |
| Kondisi diharapkan | Satu master supplier; semua modul memakai data yang sama |
| Dampak bisnis | Tidak ada supplier ganda dan kontak yang berbeda |
| Komponen | `Partner`, `Supplier`, `Product`, `SupplierPurchaseOrder`, `ProductPriceLog` |
| Rekomendasi | Petakan partner supplier ke suppliers; tambahkan link atau migrasi; jangan hapus `partners` sebelum verifikasi |
| Risiko | Kehilangan data partner marketing; mapping manual |
| Dependensi | Data cleansing |
| Prioritas | HIGH |

### R-08 - Ubah KPI omzet ke PO terverifikasi

| Field | Isi |
|---|---|
| BDA | BDA-020 |
| Audit terkait | DB-008, CTL-04 |
| Kondisi saat ini | `SalesKpiService` menghitung `verifiedPoRevenue` tetapi `actualRevenue` dari closed paid deal |
| Kondisi diharapkan | Omzet Sales dihitung saat PO resmi diterima; cash-in dipantau terpisah |
| Dampak bisnis | Pencapaian Sales sesuai definisi Management |
| Komponen | `SalesKpiService`, `ManageSalesController`, `DashboardController`, KPI views |
| Rekomendasi | Ubah achievement ke `verifiedPoRevenue`; tampilkan cash-in sebagai metrik terpisah; sesuaikan target |
| Risiko | Angka historis berubah; perlu perbandingan periode |
| Dependensi | Keputusan tanggal pengakuan PO |
| Prioritas | HIGH |

### R-09 - Tambahkan status terima customer dan bukti

| Field | Isi |
|---|---|
| BDA | BDA-025 |
| Audit terkait | DB-014, DB-015; BDA-026 |
| Kondisi saat ini | `goods_deliveries.status` hanya prepared/shipped/delivered; tidak ada partial customer receipt/proof |
| Kondisi diharapkan | Status belum dikirim/dikirim/diterima sebagian/diterima penuh; bukti terlampir |
| Dampak bisnis | Closing tidak menganggap “delivered” sama dengan “customer received” |
| Komponen | `GoodsDelivery`, `GoodsDeliveryItem`, `GoodsDeliveryController`, `Project::evaluateCommercialClosing` |
| Rekomendasi | Tambah status/kolom receipt atau tabel `goods_delivery_receipts`; bukti file; update closing |
| Risiko | Data delivery lama tidak punya receipt |
| Dependensi | R-13 BAST/closing |
| Prioritas | HIGH |

### R-10 - Dukung jadwal termin pembayaran

| Field | Isi |
|---|---|
| BDA | BDA-027 |
| Audit terkait | DB-008 |
| Kondisi saat ini | `proposals.payment_scheme` full/dp/tempo; `dp_percent`, `term_days`; `Invoice::TYPES` |
| Kondisi diharapkan | Jumlah/termin fleksibel, nominal/persentase, jadwal, total dan sisa |
| Dampak bisnis | Project dengan termin berbeda dapat ditagih dan dipantau |
| Komponen | `Proposal`, `Invoice`, `AdminService`, invoice form |
| Rekomendasi | Tabel `proposal_payment_terms` (sequence, percent/amount, due date); invoice link; backfill opsional |
| Risiko | Invoice lama tanpa jadwal |
| Dependensi | Keputusan format termin |
| Prioritas | HIGH |

### R-11 - Sentralisasi nomor dokumen

| Field | Isi |
|---|---|
| BDA | BDA-011 |
| Audit terkait | DB-019 |
| Kondisi saat ini | Unique index ada, tetapi generator invoice/PO/GR/DO/SINV read-max tanpa retry |
| Kondisi diharapkan | Nomor unik, tidak dipakai ulang, aman concurrent |
| Dampak bisnis | Tidak ada dokumen gagal/duplikat saat ramai |
| Komponen | `AdminService`, `ProcurementService`, `QuotationNumberService`, unique index |
| Rekomendasi | Sequence/transaction retry; unique `proposals.po_number` setelah scope |
| Risiko | Perubahan format nomor |
| Dependensi | Keputusan scope unik |
| Prioritas | HIGH |

### R-12 - Tutup celah ownership Sales

| Field | Isi |
|---|---|
| BDA | BDA-013 |
| Audit terkait | AUTH-01, AUTH-03, DB-016 |
| Kondisi saat ini | `promoteCustomer` tanpa cek owner; customer WhatsApp tanpa `created_by`/`pt_group`; inside sales quotation access |
| Kondisi diharapkan | Sales hanya akses miliknya; lead harus assigned; backend check |
| Dampak bisnis | Isolasi data Sales dan PT |
| Komponen | `CustomerAccess`, `LeadPolicy`, `CustomerPolicy`, `LeadController`, `WhatsAppCenterController` |
| Rekomendasi | Tambah authorize/PT check; isi ownership; test negatif |
| Risiko | Sales kehilangan akses data lama yang belum terpetakan |
| Dependensi | Data cleansing |
| Prioritas | HIGH |

### R-13 - Sinkronkan BAST dan workflow

| Field | Isi |
|---|---|
| BDA | BDA-026 |
| Audit terkait | DB-004, DB-017, WF-04 |
| Kondisi saat ini | BAST disimpan di `projects.bast_*`; `BastController` tidak memanggil workflow sync; history single-row |
| Kondisi diharapkan | BAST tercatat, punya history, dan memicu evaluasi progress/closing |
| Dampak bisnis | Closing tidak macet atau salah karena progress stale |
| Komponen | `BastController`, `ProjectWorkflowService`, `Project`, `ProjectDocument` |
| Rekomendasi | Panggil sync setelah BAST; tambah history/audit BAST; test closing |
| Risiko | Perubahan status otomatis |
| Dependensi | R-03/R-09 |
| Prioritas | HIGH |

## 5. Medium

### R-14 - Tegaskan aturan DP vs BAST

| Field | Isi |
|---|---|
| BDA | BDA-001 |
| Audit terkait | BR-019, DB-002 |
| Kondisi saat ini | Invoice DP didukung; tidak ada aturan BAST untuk invoice penuh |
| Kondisi diharapkan | DP boleh sebelum BAST; invoice penuh/pelunasan mengikuti persyaratan |
| Dampak bisnis | Menghindari invoice penuh sebelum pekerjaan selesai |
| Komponen | `AdminController`, `AdminService`, `Invoice` |
| Rekomendasi | Validasi berbasis `invoice_type` dan BAST; dokumentasikan pengecualian |
| Risiko | Menghambat penagihan yang sah |
| Dependensi | Keputusan Finance |
| Prioritas | MEDIUM |

### R-15 - Perkuat identitas produk

| Field | Isi |
|---|---|
| BDA | BDA-009 |
| Audit terkait | DB-013 |
| Kondisi saat ini | `products.sku` hanya index; produk dinonaktifkan, tidak dihapus |
| Kondisi diharapkan | Identitas produk konsisten |
| Dampak bisnis | Tidak ada produk ganda |
| Komponen | `products`, `ProductController`, `ProductPolicy` |
| Rekomendasi | Unique SKU setelah scope; validasi produk terpakai |
| Risiko | Duplikat lama |
| Dependensi | Data cleansing |
| Prioritas | MEDIUM |

### R-16 - Lengkapi riwayat harga modal

| Field | Isi |
|---|---|
| BDA | BDA-010 |
| Audit terkait | DB-008 |
| Kondisi saat ini | `product_price_logs` append-only; `supplier_id` nullable; previous price tidak eksplisit |
| Kondisi diharapkan | Produk, supplier, harga lama/baru, waktu, user |
| Dampak bisnis | Perbandingan harga dapat dipercaya |
| Komponen | `ProductPriceLog`, `ProductPriceService`, `ProductController` |
| Rekomendasi | Wajibkan supplier untuk log cost baru; query previous price; jangan ubah histori |
| Risiko | Data lama null |
| Dependensi | R-06 |
| Prioritas | MEDIUM |

### R-17 - Kalender hanya meeting

| Field | Isi |
|---|---|
| BDA | BDA-018 |
| Audit terkait | FE-01? |
| Kondisi saat ini | Kalender menggabungkan FollowUp meeting + SalesSchedule non-meeting |
| Kondisi diharapkan | Kalender hanya meeting |
| Dampak bisnis | Agenda tidak tercampur |
| Komponen | `SalesScheduleController`, `SalesSchedule`, `sales/schedules/index.blade.php` |
| Rekomendasi | Filter tipe atau pisahkan agenda |
| Risiko | Agenda non-meeting tidak terlihat |
| Dependensi | Keputusan UI |
| Prioritas | MEDIUM |

### R-18 - Peringatan deadline pengiriman

| Field | Isi |
|---|---|
| BDA | BDA-024 |
| Audit terkait | DB-007 |
| Kondisi saat ini | Warning <=7 hari di admin hub |
| Kondisi diharapkan | Peringatan sebelum/terlewat; Admin/Purchasing memantau |
| Dampak bisnis | Mengurangi keterlambatan |
| Komponen | `AdminService`, notification/command, admin hub |
| Rekomendasi | Queue reminder dan overdue; denda tetap pending |
| Risiko | Notifikasi berlebihan |
| Dependensi | SLA keputusan |
| Prioritas | MEDIUM |

### R-19 - Paket integritas constraint

| Field | Isi |
|---|---|
| BDA | BDA-007/011 |
| Audit terkait | DB-005, DB-006, DB-007, DB-013 |
| Kondisi saat ini | Status check sedikit; FK/index belum lengkap |
| Kondisi diharapkan | Constraint sesuai status final dan query |
| Dampak bisnis | Data invalid berkurang |
| Komponen | Migration constraint/index |
| Rekomendasi | Tambah check/unique/index setelah daftar status final |
| Risiko | Data lama gagal validasi |
| Dependensi | Keputusan status |
| Prioritas | MEDIUM |

### R-20 - Petakan data legacy sebelum migrasi

| Field | Isi |
|---|---|
| BDA | BDA-008/009/011 |
| Audit terkait | DB-009, DB-010, DB-011 |
| Kondisi saat ini | `partners`, `purchase_orders`, `request_hargas` masih ada |
| Kondisi diharapkan | Satu sumber data tanpa kehilangan histori |
| Dampak bisnis | Tidak ada data hilang saat konsolidasi |
| Komponen | Data migration scripts, mapping table |
| Rekomendasi | Buat mapping dan verifikasi nol referensi sebelum hapus/arsip |
| Risiko | Migrasi data salah |
| Dependensi | R-07/R-15 |
| Prioritas | MEDIUM |

## 6. Low

### R-21 - End-user reseller opsional

| Field | Isi |
|---|---|
| BDA | BDA-016 |
| Audit terkait | - |
| Kondisi saat ini | Tidak ada field end-user |
| Kondisi diharapkan | Opsional, dapat diisi kemudian |
| Komponen | `leads`/customer |
| Rekomendasi | Tambah kolom nullable jika dibutuhkan; jangan jadikan wajib |
| Risiko | Rendah |
| Dependensi | Keputusan Sales |
| Prioritas | LOW |

### R-22 - Bersihkan schema legacy

| Field | Isi |
|---|---|
| BDA | - |
| Audit terkait | DB-020, DB-022, DB-023 |
| Kondisi saat ini | `category_id` duplikat, customer status ganda, tabel lebar |
| Kondisi diharapkan | Schema lebih jelas |
| Komponen | `project_documents`, `customers`, `proposals`, `projects` |
| Rekomendasi | Hapus/arsipkan setelah verifikasi data; jangan refactor massal |
| Risiko | Data lama |
| Dependensi | Audit data |
| Prioritas | LOW |

## 7. Pending dan Deferred

| ID | Status | Rekomendasi |
|---|---|---|
| BDA-015 | Deferred | Jangan implementasi SLA 0/15/30; pertahankan notifikasi yang ada |
| BDA-021 | Pending | Jangan jadikan target margin validasi final; tunggu Management |

## 8. Urutan Implementasi yang Disarankan

### Wave 1 - Safety

1. R-01 customer delete guard.
2. R-02 Work Type protection.
3. R-04 audit log minimal.

### Wave 2 - Satu Sumber Kebenaran

4. R-03 progress workflow.
5. R-05 status inti.
6. R-13 BAST sync/history.
7. R-09 delivery receipt.

### Wave 3 - Policy dan Data Master

8. R-06 cost policy.
9. R-12 ownership.
10. R-07 supplier master.
11. R-15 product identity.
12. R-16 cost history.

### Wave 4 - Commercial

13. R-08 KPI omzet PO.
14. R-10 payment terms.
15. R-11 numbering.
16. R-14 DP/BAST rule.

### Wave 5 - UX dan Cleanup

17. R-17 calendar.
18. R-18 deadline reminder.
19. R-19 constraints.
20. R-20 legacy mapping.
21. R-21/R-22 optional.

## 9. Definisi Selesai per Rekomendasi

Setiap rekomendasi harus memiliki:

- Test positif dan negatif.
- Bukti UI light/dark dan mobile bila menyentuh tampilan.
- Data migration/backfill bila mengubah constraint.
- Audit trail untuk perubahan status/nominal.
- Dokumentasi SOP/role.
- Persetujuan bisnis sebelum perubahan final.

## 10. Catatan

Rekomendasi ini belum merupakan izin implementasi. Tidak ada source code, schema, atau data yang diubah pada Phase 2.1.
