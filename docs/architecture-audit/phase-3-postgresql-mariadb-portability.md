# Phase 3 - PostgreSQL vs MariaDB Portability Assessment

**Status:** Selesai (read-only assessment)
**Tanggal:** 2026-11-26
**Scope:** Wave 1 data-safety dan kesiapan aplikasi bila database dipindah dari PostgreSQL ke MariaDB.
**Kesimpulan:** PostgreSQL tetap menjadi database utama. MariaDB belum bisa menjadi pengganti langsung tanpa pekerjaan portability terpisah.

## 1. Ringkasan

Aplikasi saat ini memakai PostgreSQL dan beberapa fitur bergantung pada kemampuan khusus PostgreSQL:

- partial unique index;
- GIN/`to_tsvector` untuk pencarian knowledge base;
- PL/pgSQL trigger dan `FOR KEY SHARE`;
- `ALTER COLUMN ... TYPE ... USING`;
- konfigurasi `search_path`.

Sebagian migration sudah punya cabang `mysql`, tetapi belum semua. Wave 1 menambah trigger PostgreSQL untuk race guard dan audit append-only, sehingga jarak portability makin lebar.

## 2. Blocker Utama MariaDB

| Area | Bukti | Dampak di MariaDB | Rekomendasi |
|---|---|---|---|
| Partial unique index | `customer_contacts_one_primary`, `technician_schedules_google_event_id_active_unique` | MariaDB tidak mendukung `CREATE INDEX ... WHERE` | Ganti dengan generated column + unique index, trigger, atau guard aplikasi |
| Full-text search | `knowledge_chunks_content_fts` memakai GIN `to_tsvector` | GIN/tsvector tidak ada | Gunakan FULLTEXT InnoDB atau search engine terpisah |
| Trigger race guard | `guard_customer_transaction` memakai PL/pgSQL dan `FOR KEY SHARE` | Sintaks dan row lock berbeda | Tulis ulang trigger MariaDB atau andalkan transaksi aplikasi |
| Audit append-only | `guard_audit_logs_immutable` memakai PL/pgSQL | Perlu trigger MariaDB terpisah | Tulis ulang trigger; model guard tetap dipakai |
| Raw ALTER COLUMN | `customer_documents_nullable_project` memakai `ALTER COLUMN ... DROP NOT NULL` | MariaDB memakai `MODIFY COLUMN` | Tambah cabang driver `mysql` |
| Partial index migration | `add_single_primary_guard_to_customer_contacts` tidak punya guard driver | Migration gagal di MariaDB | Tambah cabang MariaDB |
| Partial index restore | `restore_partial_unique_google_event_id_index` sengaja skip MySQL | Tidak ada unique guard untuk jadwal aktif | Ganti dengan mekanisme MariaDB |
| JSON | Kolom JSON dipakai di beberapa tabel | MariaDB mendukung JSON, tetapi fungsi/index berbeda | Audit query JSON dan index |
| `search_path` | `config/database.php` memakai `search_path` PostgreSQL | Tidak berlaku di MariaDB | Pisahkan konfigurasi koneksi |

## 3. Yang Sudah Kompatibel

- `whereLike` memakai `LOWER(...) LIKE ?`, bukan `ILIKE`.
- `foreignId`, `restrictOnDelete`, `nullOnDelete`, `timestamps`, `softDeletes`, dan `json` umumnya didukung MariaDB.
- Beberapa migration sudah punya cabang `mysql`:
  - `add_finance_fields_to_invoices`;
  - `replace_lead_statuses_with_cool_warm_hot`;
  - `encrypt_whatsapp_gateway_tokens`.
- Sebagian besar query Eloquent tidak memakai SQL khusus PostgreSQL.

## 4. Dampak ke Wave 1

Wave 1 aman di PostgreSQL. Bila MariaDB dipakai:

- FK `RESTRICT` tetap bisa berjalan.
- Trigger race guard customer hilang kecuali ditulis ulang.
- Audit log tetap bisa dibatasi lewat model, tetapi proteksi database melemah.
- Partial unique index kontak dan jadwal perlu desain ulang.
- Migration `customer_documents_nullable_project` dan `add_single_primary_guard` perlu cabang driver.

## 5. Rekomendasi

1. **Pertahankan PostgreSQL** untuk Wave 1 dan merge saat ini.
2. Perlakukan dukungan MariaDB sebagai workstream terpisah, bukan bagian Wave 1.
3. Bila MariaDB wajib:
   - tambah cabang driver pada semua raw SQL;
   - ganti partial index dengan generated column/trigger;
   - tulis ulang trigger PL/pgSQL;
   - ganti GIN FTS dengan FULLTEXT;
   - siapkan staging MariaDB dan uji migration up/down, constraint, concurrency, dan full suite.
4. Jangan menyatakan MariaDB didukung sebelum ada test nyata di MariaDB 10.6+ atau 11.4.

## 6. Verifikasi

Assessment ini berbasis pembacaan migration, model, dan query aktual. Tidak ada migration atau data yang diubah.
