# PLAN-ESTIMATOR-CORE-FIXES: Perbaikan Inti Alur Estimator

**Status Pengerjaan:** Siap Dikerjakan
**Work:** ESTIMATOR-CORE-FIXES
**PIC:** Yeski
**Menu:** Estimator (Queue, Detail Request, Riwayat Harga) & Pricelist
**Branch:** feature/estimator-hardening
**Terakhir diperbarui:** 2026-11-20

---

## 1. Tujuan & hasil akhir

- Semua permintaan harga baru bisa dijangkau, tidak berhenti di 12 kartu.
- Sales tidak perlu mengetik ulang item saat membuat request dari quotation.
- Riwayat harga menampilkan seluruh perubahan harga modal, termasuk dari
  Pricelist dan Bulk Price.
- Harga tidak terkirim tanpa konfirmasi ringkasan.
- Hanya ada satu pintu update harga massal (Bulk Price dengan PPN + review).

## 2. Ruang lingkup

Termasuk:

- Tautan "Lihat semua" pada antrean request baru dan filter status Submitted
  yang benar-benar menampilkan request baru.
- Prefill item dari quotation yang dipilih saat membuat request, dengan
  tombol **Request Product Price** yang jelas di atas tabel item quotation.
- Riwayat harga memasukkan log di luar request Estimator, dengan label sumber.
- Kartu konfirmasi sebelum tombol Send Prices.
- Pengalihan tautan lama `products?bulk=1` ke halaman Bulk Price dan
  penghapusan mode bulk lama dari halaman Pricelist.

Tidak termasuk:

- Dashboard Estimator, aging/SLA, dan pengingat (Paket B).
- Perapian tampilan detail request dan filter native (Paket C).
- Perubahan struktur database atau aturan harga baru.

## 3. Langkah pengerjaan

1. Tulis tes reproduksi untuk tiap masalah, pastikan gagal.
2. Perbaiki antrean dan filter status.
3. Tambah prefill item quotation di form request.
4. Buka riwayat harga untuk log Pricelist/Bulk dan beri label sumber.
5. Tambah konfirmasi Send Prices.
6. Satukan bulk price ke satu halaman dan arahkan tautan lama.
7. Jalankan seluruh test PHP dan test JS.

## 4. Kriteria selesai / verifikasi

- Lebih dari 12 request baru: tombol "Lihat semua" muncul dan halaman filter
  Submitted menampilkan seluruh request.
- Buka request dari quotation: item BOQ langsung terisi dan bisa dihapus.
- Riwayat harga menampilkan perubahan dari Bulk Price/Pricelist.
- Send Prices menampilkan ringkasan sebelum mengirim.
- Tautan lama `?bulk=1` membuka halaman Bulk Price; tidak ada lagi form bulk
  di halaman Pricelist.
- `php artisan test` dan `npm run test:js` lulus.

## 5. Dependensi

- Tidak ada migration baru.
- Perubahan menyentuh halaman Pricelist yang juga dipakai sesi Teknisi;
  merge ke `main` menunggu worktree terkait bersih.
