# Phase 2.1 - Decision ID Mapping

**Audit:** Phase 2.1 - Business Decision Alignment
**Repository:** `/var/www/3dyapp`
**Tanggal:** 2026-11-24
**Mode:** Read-only

## 1. Aturan ID

Phase 2.1 memakai tiga namespace yang tidak boleh tercampur:

| Namespace | Sumber | Jumlah | Arti |
|---|---|---:|---|
| `DEC-001..DEC-020` | Business Discovery v1.0 | 20 | Pertanyaan bisnis awal, belum final |
| `BDA-001..BDA-027` | `business-decision-alignment.md` | 27 | Keputusan bisnis terbaru |
| `DB-001..DB-024` | Phase 2 database audit | 24 | Temuan teknis database |
| `AUTH-*`, `ARC-*`, `WF-*`, `CTL-*`, `FE-*`, `INT-*` | Phase 1 | - | Temuan arsitektur/aplikasi |

**Peringatan:** nomor `DEC-001` pada Business Discovery **bukan** `BDA-001`. Topiknya berbeda. Jangan memakai nomor yang sama untuk dua arti.

## 2. Peta DEC Lama ke BDA Baru

| ID lama | Topik lama | BDA terkait | Status hubungan | Catatan |
|---|---|---|---|---|
| DEC-001 | Kapan customer menjadi aktif? | BDA-005, BDA-013 | Tidak berkaitan langsung | BDA-005 mengatur arsip customer; BDA-013 mengatur akses. Titik “customer aktif” belum dijawab eksplisit |
| DEC-002 | Apakah Hot hanya >50%? | - | Tidak berkaitan | Tidak ada BDA yang menetapkan persentase peluang |
| DEC-003 | Bolehkah Sales melihat harga modal? | BDA-003 | Sesuai / memperjelas | BDA-003 menjawab: boleh lihat, tidak boleh ubah |
| DEC-004 | Siapa boleh ubah margin/default price? | BDA-003, BDA-021 | Memperjelas | Estimator mengubah harga modal; target margin masih pending |
| DEC-005 | Manual progress boleh override workflow? | BDA-004 | Mengubah arah | BDA-004 melarang progress manual |
| DEC-006 | Admin/Teknisi boleh buat project tanpa lead? | - | Tidak berkaitan | Belum ada keputusan baru |
| DEC-007 | Won/PO dan Closing berbeda? | BDA-001, BDA-020, BDA-026 | Memperjelas | Omzet dari PO; Closing tetap butuh BAST dan pelunasan |
| DEC-008 | Kapan Admin menarik quotation? | - | Tidak berkaitan | Belum ada keputusan baru |
| DEC-009 | Aturan cancel Supplier PO? | - | Tidak berkaitan | Belum ada keputusan baru |
| DEC-010 | Retur barang? | - | Tidak berkaitan | Belum ada keputusan baru |
| DEC-011 | Pembayaran sebagian? | BDA-002, BDA-027 | Sesuai / memperjelas | DP fleksibel dan termin bervariasi |
| DEC-012 | Closing wajib semua invoice lunas? | BDA-001, BDA-026 | Memperjelas | Invoice DP boleh sebelum BAST; Closing tetap butuh lunas |
| DEC-013 | Definisi nomor dokumen unik? | BDA-011 | Sesuai / memperjelas | Unik dan tidak boleh dipakai ulang |
| DEC-014 | Reseller dengan banyak end-user? | BDA-016 | Memperjelas | End-user opsional, bukan wajib |
| DEC-015 | Siapa menyetujui revisi quotation? | - | Tidak berkaitan | Belum ada keputusan baru |
| DEC-016 | SLA notifikasi lead 0/15/30? | BDA-015 | Ditunda | Keputusan baru menunda pengingat otomatis |
| DEC-017 | Rumus target omzet/margin? | BDA-019, BDA-020, BDA-021 | Mengubah arah | Omzet dari PO; target margin pending |
| DEC-018 | Jadwal teknisi perlu approval? | - | Tidak berkaitan | Belum ada keputusan baru |
| DEC-019 | Siapa bertanggung jawab jika BAST belum ada? | BDA-026 | Memperjelas | BAST wajib sebelum Closing; owner operasional belum eksplisit |
| DEC-020 | Denda keterlambatan SPK? | BDA-024 | Memperjelas sebagian | Fokus pada deadline dan peringatan; denda belum diputuskan |

## 3. Peta BDA ke Referensi Lama dan Temuan Audit

| BDA | Topik | DEC lama terkait | DB/Phase 1 terkait | Hubungan |
|---|---|---|---|---|
| BDA-001 | Invoice DP sebelum BAST | DEC-007, DEC-012, DEC-019 | DB-002, AUTH-06 | Memperjelas; invoice dan project status dipisah |
| BDA-002 | DP fleksibel | DEC-011, DEC-027 | DB-008 | Memperjelas skema DP |
| BDA-003 | Sales lihat harga modal, tidak boleh ubah | DEC-003, DEC-004 | AUTH-06, DB-014 | Sesuai akses lihat; konflik pada siapa yang boleh ubah |
| BDA-004 | Progress otomatis | DEC-005 | DB-004, ARC-03, WF-05 | Mengubah arah; implementasi manual masih ada |
| BDA-005 | Customer bertransaksi tidak boleh dihapus permanen | DEC-001 | DB-002, AUTH-01 | Mengubah arah; force delete masih ada |
| BDA-006 | Work Type terpakai tidak boleh dihapus | - | DB-001 | Konflik langsung |
| BDA-007 | Status inti hanya Super Admin/developer | DEC-007 | DB-004, DB-013 | Memperjelas; status masih bisa diedit admin |
| BDA-008 | Satu master supplier | DEC-009 | DB-010 | Konflik; dua master |
| BDA-009 | Satu master produk | - | DB-013 | Sebagian; tabel produk satu, identitas belum unik |
| BDA-010 | Riwayat harga modal lengkap | DEC-003 | DB-008, DB-014 | Sebagian; histori ada, harga sebelumnya tidak eksplisit |
| BDA-011 | Nomor dokumen unik dan aman concurrent | DEC-013 | DB-019 | Sebagian; unique ada, retry belum merata |
| BDA-012 | Audit log | - | DB-003 | Belum ada |
| BDA-013 | Isolasi data Sales | DEC-001 | AUTH-01, AUTH-03, DB-016 | Sebagian; ada celah ownership |
| BDA-014 | Follow-up fleksibel | DEC-002, DEC-005 | WF-08, BR-005 | Sesuai; loss reason wajib |
| BDA-015 | Pengingat follow-up | DEC-016 | INT-03 | Ditunda |
| BDA-016 | End-user reseller opsional | DEC-014 | DB-013 | Belum ada field; sifatnya opsional |
| BDA-017 | Riwayat komunikasi tidak wajib | - | WF? | Sesuai; pencatatan opsional |
| BDA-018 | Kalender hanya meeting | DEC-018 | FE-01? | Konflik; kalender memuat agenda non-meeting |
| BDA-019 | Management menentukan KPI | DEC-017 | CTL-04 | Sesuai; management memegang target |
| BDA-020 | Omzet dari PO | DEC-007, DEC-017 | DB-008, CTL-04 | Konflik; KPI memakai closed/paid deal |
| BDA-021 | Target margin minimum | DEC-017 | DB-008 | Pending |
| BDA-022 | Receipt tanpa invoice supplier | DEC-009 | DB-014 | Sesuai |
| BDA-023 | Partial goods receipt | DEC-011 | DB-014, DB-015 | Sesuai |
| BDA-024 | Deadline pengiriman | DEC-020 | DB-007 | Sebagian; warning, belum denda/eskalasi |
| BDA-025 | Status kirim/terima customer | - | DB-014, DB-015 | Sebagian; status customer receipt belum ada |
| BDA-026 | BAST sebelum Closing | DEC-019 | DB-004, WF-04 | Sebagian; BAST ada, sync/history lemah |
| BDA-027 | Variasi termin pembayaran | DEC-011, DEC-012 | DB-008 | Sebagian; belum ada tabel jadwal termin |

## 4. BDA yang Tidak Memiliki Padanan DEC Lama

Keputusan berikut adalah topik baru yang tidak memiliki DEC lama langsung:

- BDA-006 Work Type.
- BDA-009 master produk.
- BDA-012 audit log.
- BDA-017 riwayat komunikasi.
- BDA-025 status kirim/terima customer.

BDA lain memiliki setidaknya satu DEC lama yang terkait, meskipun hubungannya bisa hanya “memperjelas” atau “mengubah arah”.

## 5. Catatan Konflik ID

1. Dokumen `business-decision-alignment.md` memakai `DEC-001..DEC-027`. Dokumen ini menggantinya menjadi `BDA-001..BDA-027`.
2. Business Discovery tetap memakai `DEC-001..DEC-020`; rujukan lama tidak boleh dihapus.
3. Phase 2 findings tetap memakai `DB-001..DB-024`.
4. Jika ada dokumen baru yang merujuk `DEC-021..DEC-027`, itu hanya boleh merujuk BDA, bukan Business Discovery.
5. Jangan mengganti ID di dokumen lama; gunakan tabel pemetaan ini.

## 6. Kesimpulan Mapping

- 22 BDA memiliki setidaknya satu DEC lama yang terkait.
- 5 BDA adalah topik baru tanpa DEC lama: BDA-006, BDA-009, BDA-012, BDA-017, BDA-025.
- 7 BDA berkonflik langsung dengan implementasi saat ini: BDA-003, BDA-004, BDA-005, BDA-006, BDA-008, BDA-018, BDA-020.
- 10 BDA baru sebagian didukung.
- 6 BDA sudah didukung.
- 2 BDA belum ditemukan: BDA-012 dan BDA-016.
- 1 BDA pending (BDA-021) dan 1 BDA deferred (BDA-015).
