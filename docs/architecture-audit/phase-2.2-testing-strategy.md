# Phase 2.2 - Testing Strategy

**Audit:** Phase 2.2 - Implementation Planning
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada test yang dijalankan pada phase ini.

## 1. Tujuan Testing

Memastikan perubahan Phase 2.2:

1. Tidak menghilangkan data historis.
2. Menegakkan keputusan bisnis di backend, bukan hanya UI.
3. Tidak merusak alur invoice, BAST, delivery, quotation, dan KPI yang sudah berjalan.
4. Aman untuk multi-PT dan multi-role.
5. Dapat di-rollback di staging.

## 2. Lingkungan Test

| Lingkungan | Kegunaan | Catatan |
|---|---|---|
| SQLite in-memory | Unit/feature test cepat | Tidak menguji FK, partial unique, check constraint, DDL |
| PostgreSQL staging | Migration, constraint, concurrency, data backfill | Wajib untuk migration Phase 2.2 |
| Browser/UAT | UI dan peran | Tidak memakai data produksi |
| Production | Hanya verifikasi read-only setelah deploy | Tidak untuk uji destruktif |

**Penting:** `phpunit.xml` memakai SQLite `:memory:`. Migration yang mengubah FK/partial unique/check constraint harus diuji di PostgreSQL staging, bukan hanya SQLite.

## 3. Lapisan Test

1. **Unit** - policy, service, calculator, status transition.
2. **Feature** - route/controller, authorization, validation, database.
3. **Migration test** - up/down, data backfill, constraint.
4. **Integration** - PostgreSQL, storage, queue.
5. **Browser/UAT** - per role dan perangkat.
6. **Regression** - test lama yang sudah ada.

## 4. Test per BDA

| BDA | Test wajib | Bukti/regression |
|---|---|---|
| BDA-001 | Invoice DP/termin/pelunasan sebelum BAST berhasil; Closing tetap gagal tanpa BAST | `InvoiceHandoffTest`, `BastWorkflowTest` |
| BDA-002 | DP 10%, 30%, 50% menghasilkan nominal benar | `AdminInvoicePaymentTest` |
| BDA-003 | Sales view cost; Sales gagal update; Admin/Purchasing ditolak jika policy baru; Estimator berhasil | `ProposalCostMarginTest`, `ProductPricelistTest` |
| BDA-004 | Update progress manual ditolak; workflow menghitung progress | `ProjectWorkflowTest` |
| BDA-005 | Force delete customer dengan invoice/payment ditolak; arsip muncul di laporan historis; customer tanpa transaksi dapat diarsipkan | `FinanceIntegrityTest`, `TrashTest` |
| BDA-006 | Delete Work Type terpakai ditolak; nonaktif berhasil; project tetap ada | `ProjectCreateAccessTest` |
| BDA-007 | Status inti tidak bisa rename/delete; custom status bisa; default unik | `ProjectStatusListAuthzTest` |
| BDA-008 | Partner supplier terpetakan ke supplier; tidak ada duplikat; PO memakai suppliers | `SupplierPurchasingTest` |
| BDA-009 | SKU duplikat ditolak; produk terpakai tidak hilang | `ProductPricelistTest` |
| BDA-010 | Cost log baru wajib supplier; histori lama null tetap terbaca; previous price tampil | `EstimatorPriceHistoryTest` |
| BDA-011 | Nomor unik; retry tidak menghasilkan duplikat; nomor cancelled tetap ada; PO unique tidak diuji sebagai constraint | `ProposalNumberingRevisionTest` |
| BDA-012 | Audit tercatat untuk invoice/payment/PO/BAST/role; user biasa tidak bisa edit/hapus | Baru |
| BDA-013 | Sales A tidak lihat customer Sales B; `promoteCustomer` lintas owner ditolak; WhatsApp customer punya owner/PT | `SalesAreaPrivacyTest`, `RoleMatrixTest` |
| BDA-014 | Follow-up tanpa batas; loss wajib alasan/catatan; leader bisa melihat | `LeadOutcomeTest`, `FollowUpOwnershipTest` |
| BDA-015 | Tidak ada test implementasi; pastikan tidak ada gate baru | - |
| BDA-016 | End-user opsional; lead tetap valid tanpa end-user | `LeadFormTest` |
| BDA-017 | Komunikasi tidak wajib; follow-up opsional tetap valid | `FollowUpFormTest` |
| BDA-018 | Kalender hanya menampilkan meeting; agenda non-meeting tidak muncul | `SalesScheduleCalendarTest` |
| BDA-019 | Management set target; Sales tidak bisa set target Sales lain | `SalesKpiTargetTest`, `RoleMatrixTest` |
| BDA-020 | Omzet achievement dari PO verified; cash-in terpisah; cancel/return tidak dihitung | `SalesKpiTargetTest` |
| BDA-021 | Tidak ada validasi margin minimum | - |
| BDA-022 | Receipt tanpa invoice supplier berhasil; laporan unbilled | `ProcurementFlowTest` |
| BDA-023 | Partial receipt, sisa, status PO | `ProcurementFlowTest`, `GoodsDeliveryTrackingTest` |
| BDA-024 | Reminder deadline; overdue; tidak ada perhitungan denda | Baru |
| BDA-025 | Receipt customer partial/full, bukti, closing memakai received | Baru; `GoodsDeliveryTrackingTest` |
| BDA-026 | BAST version history; BAST sync; closing butuh BAST | `BastWorkflowTest`, `InstalasiBastButtonTest` |
| BDA-027 | Termin 2/3/4, nominal/persentase, invoice link, sisa | Baru; `AdminInvoicePaymentTest` |

## 5. Migration Test

| Test | Tujuan |
|---|---|
| Fresh migrate PostgreSQL | Semua migration baru berjalan dari nol |
| Existing snapshot migrate | Migration berjalan di data existing |
| Rollback staging | Down migration tidak merusak schema |
| Data migration idempotent | Dijalankan dua kali tidak menggandakan |
| Backfill row count | Jumlah partner/BAST/term sesuai ekspektasi |
| Constraint negative | Duplikat/ invalid ditolak |
| Restore backup | Backup dapat dipulihkan |

**Data yang diuji:** salinan database staging, bukan produksi.

## 6. Authorization Test Matrix

| Peran | BDA-003 cost | BDA-005 delete | BDA-012 audit | BDA-013 customer | BDA-020 KPI |
|---|---|---|---|---|---|
| Sales | View | No force delete | No | Own only | Own only |
| Estimator | Edit | No | No | Sesuai akses | No |
| Admin/Purchasing | View only (target) | No | Read (target) | All/PT | No |
| Management | View | Archive | Read | All | Target & team |
| Super Admin | All | Archive/guard | Read | All | All |
| Technician | No cost edit | No | No | Project scope | No |

Test negatif wajib untuk setiap sel “No”.

## 7. UAT Scenario

### Sales
- Buat follow-up, lihat customer sendiri, tidak bisa lihat customer Sales lain.
- Lihat quotation dan harga modal, tetapi tidak bisa mengubah.
- Kalender hanya meeting.
- Customer arsip tidak muncul di daftar aktif, tetapi muncul di laporan historis.

### Estimator
- Input harga modal dengan supplier; lihat histori.
- Sales tidak bisa mengubah.

### Admin/Purchasing
- Buat invoice DP/termin/pelunasan sebelum BAST.
- Terima barang tanpa invoice supplier.
- Partial receipt.
- Catat customer receipt + bukti.
- BAST dan closing.

### Finance
- Payment partial, status invoice, sisa.
- KPI omzet dari PO vs cash-in.

### Teknisi
- Progress dari workflow, bukan input manual.
- BAST history.

### Management
- Target Sales, laporan historis, audit log.

## 8. Regression Suite

Wajib lulus sebelum merge:

- `php artisan test` full suite.
- `npm run test:js`.
- Test UI feedback standard.
- Test authorization role matrix.
- Test finance integrity, invoice, procurement, BAST.
- Test customer/document access.

## 9. Performance Test

- Query daftar customer/project/invoice setelah index baru.
- Audit log dengan volume besar.
- Payment terms lookup.
- Delivery receipt.
- Calendar query.
- Bandingkan `EXPLAIN` sebelum/sesudah.

## 10. Exit Criteria

1. Semua test BDA lulus di PostgreSQL staging.
2. Migration up/down lulus.
3. Data migration idempotent.
4. UAT per peran disetujui.
5. Tidak ada data loss pada salinan staging.
6. Audit log tercatat.
7. Regression suite lulus.
8. Dokumentasi dan rollback siap.

## 11. Yang Tidak Diuji Sekarang

- BDA-015 deferred.
- BDA-021 pending.
- Unique PO customer pending.
- Retur barang (belum ada keputusan).

## 12. Status

Testing strategy ini belum dieksekusi. Tidak ada test, migration, atau data yang dijalankan.
