# Database Restore Runbook

Panduan backup & memulihkan database aplikasi **`tridayadb`** (PostgreSQL) dari file
dump otomatis atau manual. Lihat juga AGENTS.md aturan 8 (Database Safety Rules).

> **Nama DB target: `tridayadb`** (bukan `db_teknisi` yang sudah tidak ada).
> **Folder backup: `/var/backups/dbteknisi/`** (nama folder legacy, tetap dipertahankan;
> isinya file `tridayadb-<stamp>.dump`).

## Prinsip keamanan (WAJIB)

1. **Jangan pernah menjalankan restore ke `tridayadb` (produksi) atau database kantor**
   kecuali ada perintah tertulis + jendela pemeliharaan resmi. Prosedur di bawah
   diarahkan ke **database uji terisolasi** (`tridayadb_restoretest`).
2. **Selalu verifikasi `current_database()` sebelum restore** (langkah wajib di bawah).
3. **Jangan pernah menaruh password / passphrase di dokumentasi, skrip, atau log.**
   Gunakan peer auth (sebagai `postgres`) atau sumber rahasia aman (`.pgpass` / env).
4. Backup berisi **data produksi** → izin ketat: direktori `700`, file dump `600`.
   Jangan longgarkan izin; jangan menyalin dump ke lokasi world-readable.

## Konfigurasi aktual

| Item | Nilai |
|---|---|
| Database | `tridayadb` |
| Host / Port | `127.0.0.1` : `5432` |
| User aplikasi | `devteam3dy` (tanpa hak CREATEDB/superuser) |
| User backup/restore | `postgres` (peer auth, superuser) |
| Backup harian | cron `30 2 * * * postgres /usr/local/bin/dbteknisi-backup.sh` |
| Lokasi backup | `/var/backups/dbteknisi/tridayadb-<stamp>.dump` |
| Retensi | 14 hari (hanya `tridayadb-*.dump`; backup lama tidak dihapus) |

## 1. Menjalankan backup

Backup harian berjalan otomatis via cron (sebagai `postgres`). Untuk backup manual
(sebagai `postgres`, peer auth — **tanpa password**):

```bash
sudo -u postgres /usr/local/bin/dbteknisi-backup.sh
```

Script melakukan: `pg_dump -Fc tridayadb` → file sementara `.part` → **validasi**
(file tidak kosong + `pg_restore --list` sukses) → pindah ke nama final `tridayadb-<stamp>.dump`.
Bila dump gagal/kosong/arsip rusak, script **exit != 0** dan **tidak** melaporkan sukses.

Backup mandiri tanpa script:

```bash
sudo -u postgres pg_dump -Fc tridayadb \
  -f /var/backups/dbteknisi/tridayadb-$(date +%Y%m%d-%H%M).dump
```

## 2. Memvalidasi arsip

```bash
DUMP=/var/backups/dbteknisi/<nama-file>.dump

# Integritas arsip (wajib lulus):
sudo -u postgres pg_restore --list "$DUMP" >/dev/null && echo "ARSIP VALID"

# Jumlah tabel & baris data di dalam arsip:
sudo -u postgres pg_restore --list "$DUMP" | grep -c 'TABLE DATA'

# Struktur & data tabel kunci (tanpa menyentuh DB):
sudo -u postgres pg_restore --schema-only -f - -t users "$DUMP" | grep 'CREATE TABLE'
sudo -u postgres pg_restore --data-only   -f - -t users "$DUMP" | awk '/^COPY /{f=1;next} /^\\\.$/{f=0} f' | wc -l
```

## 3. Restore ke database uji terisolasi (BARU & KOSONG)

**Target harus database kosong terisolasi yang dinamai jelas. Jangan `tridayadb`.**

```bash
# 3a. Buat database uji kosong (sebagai postgres):
sudo -u postgres createdb -O devteam3dy tridayadb_restoretest

# 3b. VERIFIKASI target sebelum restore (harus tridayadb_restoretest, host 127.0.0.1):
sudo -u postgres psql -d tridayadb_restoretest -tAc "select current_database(), inet_server_addr(), current_user"
# -> pastikan baris berbunyi: tridayadb_restoretest | 127.0.0.1 | postgres

# 3c. Restore (sebagai postgres agar owner/grants/default privileges pulih penuh, exit 0):
sudo -u postgres pg_restore -d tridayadb_restoretest --clean --if-exists "$DUMP"
echo "exit code: $?"   # target: 0

# 3d. Verifikasi hasil restore (lihat bagian 4).
```

> Menggunakan akun **`postgres`** penting: sebagai `devteam3dy` (non-superuser) statement
> `ALTER DEFAULT PRIVILEGES`/grant akan gagal `permission denied` → **exit code 1** meski
> data/struktur sudah benar. Jangan mengabaikan error privilege; restore penuh harus exit 0.

## 4. Memverifikasi hasil restore

```bash
DB=tridayadb_restoretest

# Identitas + jumlah baris tabel penting (bandingkan dengan sumber tridayadb):
sudo -u postgres psql -d "$DB" -tAc "select current_database()"
for t in users technician_schedules google_app_credentials google_credentials; do
  echo -n "$t: "; sudo -u postgres psql -d "$DB" -tAc "select count(*) from $t"
done

# Struktur (jumlah tabel):
sudo -u postgres psql -d "$DB" -tAc "select count(*) from pg_tables where schemaname='public'"
```

Cocokkan jumlah baris dengan sumber (`tridayadb`). Struktur + data harus identik.

## 5. Menangani error permission

- **`permission denied to change default privileges` / grant gagal**: terjadi saat restore
  sebagai non-superuser (`devteam3dy`). Solusi: restore sebagai **`postgres`** (lihat 3c).
  **Jangan** menutup error dengan `--no-privileges` lalu mengklaim sukses — itu mengabaikan
  bagian privilege yang gagal dipulihkan.
- **`database ... does not exist` pada backup**: script lama mem-dump `db_teknisi` (sudah
  tidak ada). Pastikan script yang aktif mem-dump `tridayadb`.
- **`could not write to file` / permission**: proses backup harus berjalan sebagai `postgres`
  (pemilik `/var/backups/dbteknisi`).

## 6. Menjaga rahasia & permission file

- **Jangan** menaruh `PGPASSWORD`/passphrase di skrip, dokumentasi, atau log.
- Backup harian memakai **peer auth** (sebagai `postgres`) → tanpa password.
- Izin: direktori backup `700`, file dump `600` (diatur script via `umask 077` + `chmod 700`).
- Jika perlu password utk koneksi remote, pakai `~/.pgpass` ber-permission `600` (jangan
  di-commit, jangan dicetak).
- Log `/var/log/dbteknisi-backup.log` sebaiknya `600` (berisi output proses backup).

## 7. Checklist pasca-restore (uji)

- [ ] `current_database()` = `tridayadb_restoretest` (BUKAN `tridayadb`)
- [ ] Restore **exit code 0** (termasuk privilege), bukan hanya "data cocok"
- [ ] Jumlah baris tabel penting cocok dengan sumber
- [ ] Struktur (jumlah tabel) sesuai
- [ ] Database uji **tidak dihapus** tanpa izin; backup **tidak ditimpa/dihapus**

## 8. Batasan

- **Dilarang** menjalankan prosedur restore ke `tridayadb` / database produksi tanpa
  persetujuan tertulis + jendela pemeliharaan.
- **Dilarang** `migrate:fresh`, `db:wipe`, atau reset database produksi.
- **Dilarang** menghapus / menimpa backup yang sudah ada.
- Selalu buat database uji bernama jelas (`*_restoretest` / `*_shadow`) dan verifikasi
  identitasnya sebelum restore.
