# Phase 2.1 - Final Report

**Audit:** Phase 2.1 - Business Decision Alignment & Database Gap Analysis
**Repository:** `/var/www/3dyapp`
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada source code, schema, migration, atau data yang diubah.

## 1. Executive Summary

Phase 2.1 membandingkan 27 keputusan bisnis terbaru (BDA-001 sampai BDA-027) dengan Business Discovery, Phase 1 architecture audit, dan Phase 2 database audit.

Hasil utama:

- **6 keputusan sudah didukung** implementasi saat ini.
- **10 keputusan baru sebagian didukung**.
- **7 keputusan berkonflik langsung** dengan implementasi.
- **2 keputusan belum ditemukan** dukungannya.
- **1 keputusan pending** (target margin).
- **1 keputusan deferred** (pengingat follow-up otomatis).

Konflik paling penting:

1. **BDA-005** - customer bertransaksi masih dapat dihapus permanen, dan FK CASCADE dapat menghapus invoice/payment.
2. **BDA-006** - Work Type masih dapat dihapus permanen, dan `projects.work_type_id` CASCADE dapat menghapus project.
3. **BDA-004** - progress project masih dapat diubah manual, padahal keputusan meminta sepenuhnya otomatis.
4. **BDA-003** - Sales boleh melihat harga modal (sesuai), tetapi Admin/Purchasing juga dapat mengubah harga modal, bukan hanya Estimator.
5. **BDA-008** - masih ada dua master supplier: `partners` dan `suppliers`.
6. **BDA-018** - kalender Sales masih memuat agenda non-meeting.
7. **BDA-020** - KPI omzet masih memakai closed paid deal, bukan PO terverifikasi.

## 2. Hasil per Kelompok

| Kelompok | Didukung | Sebagian | Konflik | Belum | Pending/Deferred |
|---|---:|---:|---:|---:|---:|
| Sales, customer, quotation (BDA-001..021) | 4 | 6 | 7 | 2 | 2 |
| Purchasing, admin, finance (BDA-022..027) | 2 | 4 | 0 | 0 | 0 |
| **Total 27** | **6** | **10** | **7** | **2** | **2** |

## 3. Keputusan yang Sudah Didukung

| BDA | Topik | Bukti ringkas |
|---|---|---|
| BDA-002 | DP fleksibel | `proposals.dp_percent`, `expectedDpAmount`, invoice DP prefill |
| BDA-014 | Follow-up fleksibel | `follow_ups`; loss reason/note wajib |
| BDA-017 | Riwayat komunikasi opsional | `FollowUp` dan `WhatsappMessage` tidak mewajibkan pencatatan |
| BDA-019 | Management menentukan KPI | `manage-sales-leads`, `sales_targets`, `ManageSalesController` |
| BDA-022 | Receipt tanpa invoice supplier | `goods_receipts` independen; laporan unbilled |
| BDA-023 | Partial goods receipt | `ProcurementService`, `quantity_received`, status partial |

## 4. Konflik Penting

### 4.1 Customer dan Work Type (Critical)

- **BDA-005** vs `DB-002`: `TrashController@destroyCustomer` memanggil `forceDelete`; FK `invoices.customer_id` dan `payments.invoice_id` CASCADE.
- **BDA-006** vs `DB-001`: `projects.work_type_id` CASCADE dan `WorkTypeController@destroy` hard delete.

Dampak: kehilangan riwayat transaksi/project secara permanen.

### 4.2 Progress dan Status Project (High)

- **BDA-004** vs `DB-004`/`ARC-03`: `ProjectController@update` masih menerima `progress`.
- **BDA-007** vs `DB-004`/`DB-013`: `project_statuses` tidak memiliki `code`/`is_system`; status inti dapat diubah/dihapus.

Dampak: progress dan closing dapat tidak konsisten.

### 4.3 Harga Modal dan Master Supplier (High)

- **BDA-003** vs `AUTH-06`: `ProductPolicy::manageCost` mengizinkan Admin/Purchasing, bukan hanya Estimator.
- **BDA-008** vs `DB-010`: `partners` bertipe supplier dan `suppliers` adalah dua master.

Dampak: kewenangan harga melebar dan data supplier ganda.

### 4.4 Kalender dan KPI (High)

- **BDA-018**: kalender menggabungkan FollowUp meeting dengan `SalesSchedule` non-meeting.
- **BDA-020**: `SalesKpiService` menghitung `verifiedPoRevenue`, tetapi achievement memakai closed paid deal.

Dampak: agenda Sales tercampur dan angka omzet berbeda dari definisi bisnis.

## 5. Pending dan Deferred

| ID | Status | Perlakuan |
|---|---|---|
| BDA-015 | Deferred | Jangan implementasi SLA 0/15/30; notifikasi yang ada dipertahankan |
| BDA-021 | Pending | Jangan jadikan margin minimum sebagai validasi final |

## 6. Dampak Database

Schema saat ini sudah mendukung banyak kebutuhan, tetapi perubahan berikut perlu dipertimbangkan:

| Kebutuhan | Dampak database | BDA |
|---|---|---|
| Hentikan kehilangan data | FK/delete rule customer dan Work Type | BDA-005/006 |
| Audit | Tabel `audit_logs` | BDA-012 |
| Status inti | `project_statuses.code/is_system` | BDA-007 |
| Progress otomatis | `projects.progress` jadi derived | BDA-004 |
| Supplier/product | Unique key dan mapping legacy | BDA-008/009 |
| Cost history | Supplier wajib untuk log baru | BDA-010 |
| Nomor dokumen | Unique/retry/sequence | BDA-011 |
| Delivery receipt | Status/kolom/tabel receipt customer | BDA-025 |
| BAST history | Tabel history atau audit | BDA-026 |
| Payment terms | Tabel jadwal termin | BDA-027 |

Tidak ada tabel lama yang boleh dihapus langsung. `partners`, `purchase_orders`, dan `request_hargas` harus dipetakan terlebih dahulu.

## 7. Dampak Aplikasi

Perubahan tidak hanya di database. Dampak utama:

- **Model:** `Project`, `ProjectStatus`, `WorkType`, `Customer`, `Product`, `Proposal`, `Invoice`, `GoodsDelivery`, `Lead`.
- **Controller:** `ProjectController`, `BastController`, `TrashController`, `AdminController`, `ProductController`, `SalesScheduleController`.
- **Service:** `ProjectService`, `ProjectWorkflowService`, `AdminService`, `ProcurementService`, `ProductPriceService`, `SalesKpiService`.
- **Policy:** `CustomerPolicy`, `ProductPolicy`, `ProposalPolicy`, `ProjectStatusPolicy`, `LeadPolicy`.
- **Frontend:** form invoice, project, status admin, product/supplier, kalender, KPI, delivery, quotation.

## 8. Rekomendasi Urutan

### Wave 1 - Safety

1. Blokir force delete customer bertransaksi.
2. Lindungi Work Type dari hard delete.
3. Audit log minimal.

### Wave 2 - Satu Sumber Kebenaran

4. Progress hanya dari workflow.
5. Status inti stabil.
6. BAST sync dan history.
7. Status terima customer.

### Wave 3 - Policy dan Master

8. Batasi ubah harga modal ke Estimator.
9. Tutup celah ownership Sales.
10. Satukan supplier.
11. Perkuat identitas produk.
12. Lengkapi riwayat harga.

### Wave 4 - Commercial

13. Omzet dari PO.
14. Jadwal termin.
15. Nomor dokumen.
16. Aturan DP/BAST.

### Wave 5 - UX dan Cleanup

17. Kalender meeting-only.
18. Reminder deadline.
19. Constraint/index.
20. Legacy mapping.
21. End-user/cleanup opsional.

## 9. Keputusan yang Masih Dibutuhkan Sebelum Implementasi

1. Apakah invoice penuh/pelunasan boleh sebelum BAST, atau hanya DP?
2. Apakah Admin/Purchasing boleh mengubah harga modal, atau hanya Estimator?
3. Apakah target margin minimum akan ditetapkan? (pending)
4. Apakah kalender Sales hanya meeting, atau agenda non-meeting dipindahkan ke menu lain?
5. Apakah status terima customer cukup status, atau perlu tabel receipt dan bukti?
6. Apakah termin pembayaran perlu tabel jadwal, atau cukup teks?
7. Apakah nomor PO customer unik global, per PT, atau per customer?
8. Apakah supplier master akan menggantikan `partners` atau hanya terhubung?
9. Apakah BAST perlu riwayat versi atau cukup audit log?
10. Apakah customer yang diarsipkan tetap muncul di laporan historis?

## 10. Batasan

- Tidak ada data produksi yang diperiksa untuk membuktikan duplikat/orphan.
- Tidak ada test aplikasi yang dijalankan pada Phase 2.1.
- Tidak ada query plan atau pengukuran performa.
- Beberapa keputusan BDA membutuhkan detail aturan yang belum tertulis, terutama DP/BAST, delivery receipt, dan payment terms.
- BDA-015 dan BDA-021 tidak dianggap sebagai kewajiban implementasi.

## 11. File Output

| File | Isi |
|---|---|
| `phase-2.1-decision-id-mapping.md` | Peta DEC lama ke BDA dan referensi audit |
| `phase-2.1-business-alignment-matrix.md` | Matriks 27 keputusan dan status |
| `phase-2.1-database-gap-analysis.md` | Gap tabel, kolom, relasi, constraint, index |
| `phase-2.1-application-impact-analysis.md` | Dampak model, controller, service, policy, UI |
| `phase-2.1-prioritized-recommendations.md` | Rekomendasi Critical/High/Medium/Low dan urutan |
| `phase-2.1-final-report.md` | Ringkasan eksekutif dan langkah berikutnya |

## 12. Status Akhir

Phase 2.1 selesai sebagai analisis dan rekomendasi. Tidak ada implementasi, migration, seeder, atau perubahan data yang dilakukan.

Langkah berikutnya adalah review manusia terhadap enam dokumen ini, khususnya 10 keputusan pada Bagian 9, sebelum menyusun rencana implementasi per divisi.
