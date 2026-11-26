# Phase 2.2 - Risk Register

**Audit:** Phase 2.2 - Implementation Planning
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada risiko yang diuji pada produksi.

## 1. Skala Risiko

| Dimensi | Skala |
|---|---|
| Likelihood | Low / Medium / High |
| Impact | Low / Medium / High / Critical |
| Severity | Critical / High / Medium / Low |
| Status | Open / Mitigated by plan / Monitor / Pending decision |

Severity bukan probabilitas. Severity menunjukkan dampak jika risiko terjadi.

## 2. Ringkasan

| Severity | Jumlah | Risiko utama |
|---|---:|---|
| Critical | 2 | Kehilangan data customer dan Work Type |
| High | 10 | Migration FK, supplier mapping, SKU, KPI, BAST, delivery, cost policy, archive, rollback data |
| Medium | 8 | Termin, audit, status, SQLite, worktree, ownership |
| Low | 0 | - |

## 3. Register Risiko

### RSK-001 - Customer force delete menghapus transaksi

| Field | Isi |
|---|---|
| Severity | Critical |
| Likelihood | Medium |
| BDA / DB | BDA-005 / DB-002 |
| Trigger | Admin/pemilik customer menekan hapus permanen |
| Impact | Invoice, payment, proposal, project hilang permanen |
| Mitigation | Guard force delete; archive; FK finance RESTRICT/SET NULL; backup |
| Owner | Admin + backend |
| Status | Open |

### RSK-002 - Work Type cascade menghapus project

| Field | Isi |
|---|---|
| Severity | Critical |
| Likelihood | Medium |
| BDA / DB | BDA-006 / DB-001 |
| Trigger | Super-admin menghapus Work Type |
| Impact | Semua project terkait hilang |
| Mitigation | FK RESTRICT/SET NULL; `is_active`; guard delete; test |
| Owner | Super-admin + backend |
| Status | Open |

### RSK-003 - Perubahan FK menyebabkan lock/downtime

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | Medium |
| BDA / DB | BDA-005/006 / DB-001/002 |
| Trigger | Drop/recreate FK pada tabel besar saat jam kerja |
| Impact | Lock tabel, request gagal |
| Mitigation | Maintenance window; staging; backup; additive deploy |
| Owner | DBA/backend |
| Status | Mitigated by plan |

### RSK-004 - Mapping partner ke supplier salah

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | High |
| BDA / DB | BDA-008 / DB-010 |
| Trigger | Nama/kontak partner dan supplier mirip atau duplikat |
| Impact | Supplier ganda atau harga/PO salah taut |
| Mitigation | Profiling, normalized match, manual review, mapping table, no delete |
| Owner | Purchasing + data |
| Status | Open |

### RSK-005 - SKU duplikat menghambat unique index

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | High |
| BDA / DB | BDA-009 / DB-013 |
| Trigger | Data lama punya SKU sama atau kosong |
| Impact | Migration unique gagal; produk tidak bisa disimpan |
| Mitigation | Profiling, merge/null, unique after cleanup, dry-run |
| Owner | Estimator/Purchasing + backend |
| Status | Open |

### RSK-006 - Backfill payment terms mengarang tanggal

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | Medium |
| BDA / DB | BDA-027 / DB-008 |
| Trigger | Data lama hanya punya `dp_percent`/`term_days` |
| Impact | Jadwal termin salah; invoice/due date keliru |
| Mitigation | Nullable due date; legacy flag; no fabricated dates; manual review |
| Owner | Finance + backend |
| Status | Open |

### RSK-007 - BAST lama tidak punya history

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | High |
| BDA / DB | BDA-026 / DB-004/DB-017 |
| Trigger | Backfill hanya dari `projects.bast_*` |
| Impact | Versi lama tidak dapat direkonstruksi |
| Mitigation | Backfill satu legacy row; tandai legacy; jangan klaim history lengkap |
| Owner | Admin/Teknisi + backend |
| Status | Open |

### RSK-008 - Delivery lama tidak punya bukti terima

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | High |
| BDA / DB | BDA-025 / DB-014 |
| Trigger | Status `delivered` lama dianggap customer received |
| Impact | Closing salah; bukti tidak ada |
| Mitigation | Status `legacy_delivered`/unknown; receipt manual; no auto-claim |
| Owner | Admin/Logistik + backend |
| Status | Open |

### RSK-009 - KPI omzet berubah historis

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | High |
| BDA / DB | BDA-020 / DB-008 |
| Trigger | Achievement berubah dari closed paid deal ke PO |
| Impact | Angka Sales bulan lalu berbeda; target terlihat tidak tercapai |
| Mitigation | Bandingkan periode; label metrik; dual view sementara |
| Owner | Management + Finance |
| Status | Open |

### RSK-010 - Pembatasan cost memblokir Admin/Purchasing

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | Medium |
| BDA / DB | BDA-003 / AUTH-06 |
| Trigger | `manageCost` hanya Estimator |
| Impact | Admin/Purchasing tidak bisa memperbaiki data cost |
| Mitigation | View-only untuk admin; pengecualian darurat; dokumentasi |
| Owner | Management + backend |
| Status | Open |

### RSK-011 - Arsip dan Trash bercampur

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | Medium |
| BDA / DB | BDA-005 / DB-002 |
| Trigger | Memakai `deleted_at` sebagai arsip |
| Impact | Customer arsip hilang dari laporan atau muncul di Trash |
| Mitigation | Pisahkan `archived_at`; scope laporan `withArchived`; guard delete |
| Owner | Backend + Management |
| Status | Open |

### RSK-012 - Audit log membengkak

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | Medium |
| BDA / DB | BDA-012 / DB-003 |
| Trigger | Semua event ditulis tanpa retensi |
| Impact | Tabel besar, query lambat |
| Mitigation | Index, retensi, redaksi, sampling untuk event non-kritis |
| Owner | Backend |
| Status | Mitigated by plan |

### RSK-013 - SQLite test tidak menangkap constraint PostgreSQL

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | High |
| BDA / DB | Semua migrasi |
| Trigger | Test hanya SQLite |
| Impact | FK/partial unique/check gagal di produksi |
| Mitigation | PostgreSQL staging test; migration test khusus |
| Owner | Backend |
| Status | Open |

### RSK-014 - Konflik multi-worktree

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | High |
| BDA / DB | Phase 2.2 lintas file |
| Trigger | Banyak perubahan di worktree berbeda |
| Impact | Merge conflict dan overwrite |
| Mitigation | Satu workstream satu worktree; merge main rutin; koordinasi |
| Owner | Tim |
| Status | Open |

### RSK-015 - Backfill status code salah nama

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | Medium |
| BDA / DB | BDA-007 / DB-004 |
| Trigger | Status custom mirip status inti |
| Impact | Workflow salah memilih status |
| Mitigation | Mapping table, manual review, unique code, test |
| Owner | Backend + Super-admin |
| Status | Open |

### RSK-016 - Ownership customer WhatsApp salah PT

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | Medium |
| BDA / DB | BDA-013 / DB-016 |
| Trigger | Backfill `pt_group` dari account code |
| Impact | Customer terlihat/hilang dari Sales PT |
| Mitigation | Backfill dari account+lead; manual review; test |
| Owner | Marketing/Sales + backend |
| Status | Open |

### RSK-017 - Invoice sebelum BAST disalahartikan closing

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | Medium |
| BDA / DB | BDA-001/026 |
| Trigger | User menganggap invoice = closing |
| Impact | Closing dianggap selesai padahal BAST belum |
| Mitigation | Label UI, gate closing terpisah, test |
| Owner | Admin/Finance |
| Status | Open |

### RSK-018 - PO unique dipaksakan sebelum keputusan

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | Low |
| BDA / DB | BDA-011 |
| Trigger | Developer menambah unique index |
| Impact | PO multi-quotation gagal |
| Mitigation | Tandai pending; code review; jangan migration |
| Owner | Backend |
| Status | Pending decision |

### RSK-019 - Rollback data migration menghapus histori

| Field | Isi |
|---|---|
| Severity | High |
| Likelihood | Medium |
| BDA / DB | Semua data migration |
| Trigger | Down migration menjalankan delete/drop setelah data dipakai |
| Impact | Kehilangan arsip, BAST, receipt, audit |
| Mitigation | Rollback schema saja; restore backup; export sebelum down |
| Owner | DBA/backend |
| Status | Mitigated by plan |

### RSK-020 - UAT tidak melibatkan peran kunci

| Field | Isi |
|---|---|
| Severity | Medium |
| Likelihood | Medium |
| BDA / DB | Semua |
| Trigger | Hanya developer yang menguji |
| Impact | Aturan bisnis tidak terverifikasi |
| Mitigation | UAT Sales/Estimator/Admin/Finance/Teknisi/Management |
| Owner | Product owner |
| Status | Open |

## 4. Heat Map

| Impact \ Likelihood | Low | Medium | High |
|---|---|---|---|
| Critical | - | RSK-001, RSK-002 | - |
| High | - | RSK-003, RSK-006, RSK-010, RSK-011, RSK-019 | RSK-004, RSK-005, RSK-007, RSK-008, RSK-009 |
| Medium | - | RSK-012, RSK-015, RSK-016, RSK-017, RSK-020 | RSK-013, RSK-014 |
| Low | - | - | - |

## 5. Pending dan Deferred

| Item | Risiko | Status |
|---|---|---|
| BDA-021 target margin | Validasi margin dianggap final | Pending |
| BDA-011 PO unique | Constraint dipasang sebelum scope | Pending |
| BDA-015 SLA reminder | Implementasi dianggap wajib | Deferred |

## 6. Monitoring Setelah Implementasi

- Error rate migration dan query.
- Jumlah force delete yang ditolak.
- Jumlah customer arsip di laporan.
- Jumlah BAST version.
- Jumlah receipt customer.
- Perbedaan KPI omzet lama/baru.
- Volume audit log.
- Waktu respons daftar customer/project/invoice.

## 7. Kesimpulan

Risiko tertinggi bukan pada fitur baru, tetapi pada **penghapusan data** dan **perubahan FK** (RSK-001, RSK-002, RSK-003). Keduanya harus menjadi gerbang pertama sebelum pekerjaan lain.

Risiko data migration (supplier, SKU, BAST, delivery, payment terms) harus ditangani dengan profiling dan dry-run, bukan asumsi.
