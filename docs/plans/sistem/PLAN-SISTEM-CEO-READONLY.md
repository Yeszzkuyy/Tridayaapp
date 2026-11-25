# PLAN-SISTEM-CEO-READONLY: Kesiapan Akun CEO untuk Pengecekan Website

**Status Pengerjaan:** Siap Dikerjakan
**Work:** CEO-READONLY
**PIC:** Yeski
**Menu:** Lintas sistem / Akun CEO
**Branch:** main
**Terakhir diperbarui:** 2026-11-25

## Tujuan & Hasil Akhir

- Akun CEO bisa login dan membuka seluruh menu baca tanpa error 500,
  halaman kosong, atau tombol aksi tulis yang berujung 403.
- Pengalaman cek website bersih: tidak ada error JavaScript di halaman utama,
  tidak ada tombol Add/Edit/Delete yang tidak boleh dipakai CEO.
- Credential login diberikan langsung ke pemilik produk, tidak disimpan di
  repository.

## Ruang Lingkup

Yang dikerjakan:

- Verifikasi akun `victor@tridayaapp.com` (role `ceo`) dan seluruh menu yang
  terlihat untuk role tersebut.
- Sweep 37 halaman menu sebagai CEO: status HTTP, shell/iframe, error
  JavaScript, dan overflow layout.
- Perbaikan error Alpine di WhatsApp Center saat belum ada percakapan aktif.
- Membuka menu Management read-only (Manage Sales, KPI, Activity Log,
  Team Activity, Manage Marketing/Technical/Admin) tanpa aksi assign atau
  ubah target.
- Membuka menu Estimator read-only (Queue, Price History, Pricelist,
  Suppliers) tanpa aksi proses harga.
- Penyembunyian aksi tulis di halaman yang dibuka CEO: My Leads, Meeting,
  Request teknisi, Sizing, BAST, Payment, Invoice, WhatsApp Center, dan
  Management.
- Test regresi `ReadOnlyUiTest` untuk memastikan role penulis tetap melihat
  aksinya.

Yang tidak dikerjakan:

- Menambah permission atau role baru.
- Mengubah aturan backend; backend tetap menjadi penentu akses.
- Menambah akses tulis untuk CEO.

## Langkah Pengerjaan

1. Cek akun, role, dan permission CEO.
2. Login sebagai CEO, petakan menu, lalu sweep seluruh halaman.
3. Perbaiki error JavaScript dan tombol aksi tulis yang terlihat.
4. Jalankan test UI read-only dan full test suite.
5. Berikan credential login dan minta CEO mencoba.

## Kriteria Selesai / Verifikasi

- Login CEO berhasil.
- 37 halaman menu mengembalikan 200 dan tidak ada error JavaScript.
- Management dan Estimator bisa dibuka read-only; Admin Panel tetap 403.
- Tombol aksi tulis tidak tampil untuk CEO, tetapi tetap tampil untuk role
  penulis (sales, teknisi, admin, marketing).
- `php artisan test tests/Feature/ReadOnlyUiTest.php` lulus.
- Full test suite lulus.
- CEO menyatakan hasil pengecekan sudah sesuai.

## Dependensi

- Menu dan permission mengikuti `RoleAndPermissionSeeder`.
- Perubahan layout shell mengikuti `PLAN-SISTEM-LAYOUT-APP-SHELL.md`.
