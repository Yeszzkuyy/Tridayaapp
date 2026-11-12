# PLAN-ESTIMATOR-INTERVIEW-USERSALES: Sumber Harga Modal & Riwayat Harga

**Status Pengerjaan:** Siap Dikerjakan
**Work:** INTERVIEW-USERSALES
**PIC:** Yeski
**Menu:** Estimator (Queue, Riwayat Harga, Products, Review Prices)
**Sumber:** Wawancara Sales User Adi Santosa
**Terakhir diperbarui:** 2026-11-12

---

## 1. Tujuan & hasil akhir

Estimator menjadi sumber **harga modal** yang bersih dan tidak mencampuri
harga jual:

- Sales mengajukan kebutuhan barang/BOQ.
- Estimator mengecek harga beli terbaru ke distributor.
- Harga modal disimpan dengan tanggal, supplier, dan riwayat.
- Harga jual tetap ditentukan Sales/Inside Sales sesuai strategi margin.
- Harga modal terbaru otomatis muncul saat Inside Sales memilih produk.

---

## 2. Ide pokok wawancara yang dilayani

Mengacu ke `docs/archive/WAWANCARA-USER-SALES.md` bagian 2A:

- Sales mengajukan kebutuhan barang ke Purchasing/Estimator (Vanesha).
- Estimator mengonfirmasi ketersediaan dan harga beli terkini ke distributor.
- Harga modal dikembalikan ke Sales untuk diformulasikan ke draf penawaran.
- Perubahan harga dari supplier diinput Estimator dan otomatis menjadi harga
  terbaru.
- Estimator mencatat tanggal update tanpa input tanggal manual.
- Report mingguan berisi Sales/AM yang meminta harga, project/PT, item, dan
  harga.

---

## 3. Temuan audit sistem sekarang

### Sudah sesuai

- Role Estimator dan menu Estimator sudah ada.
- Price Request sudah bisa dibuat Sales dan diproses Estimator.
- Estimator bisa mengisi harga item terstruktur: produk, supplier, harga,
  catatan.
- Setiap harga masuk ke riwayat produk dengan tanggal otomatis.
- Produk punya field `cost_price` dan `default_price` yang terpisah secara
  data.
- Report Estimator (.xlsx) sudah ada, dengan filter tanggal, status,
  requester, dan customer; default minggu berjalan.

### Belum sesuai

1. **Harga modal menimpa harga jual default.**
   - Saat Estimator mengisi harga item, sistem menyimpan riwayat harga
     sekaligus mengubah `default_price` produk menjadi harga tersebut.
   - Akibatnya harga jual default ikut berubah menjadi harga modal.
   - Saat Inside Sales memilih produk, harga jual dan harga modal bisa sama,
     sehingga margin menjadi 0.

2. **Balasan Estimator wajib file.**
   - Form balasan mewajibkan upload file perhitungan/quotation.
   - Input harga item justru opsional.
   - Wawancara menunjukkan inti pekerjaan Estimator adalah mengembalikan harga
     modal, bukan selalu membuat file baru.

3. **Permintaan harga dari Sales juga wajib file.**
   - Form Price Request mewajibkan file quotation.
   - Wawancara memungkinkan Sales mengirim daftar kebutuhan/BOQ, tidak selalu
     quotation jadi.

4. **Harga modal belum dipakai konsisten.**
   - Produk punya `cost_price`, tetapi alur harga terbaru masih berpusat pada
   `default_price`.
   - Belum ada aturan jelas mana yang menjadi sumber harga modal saat Inside
     Sales memilih produk.

5. **Repair data historis belum dilakukan.**
   - Produk yang harga jual default-nya sudah terlanjur tertimpa harga modal
     perlu ditinjau, jangan dibiarkan margin 0.

---

## 4. Keputusan final (12 November 2026)

1. **Price Request tetap khusus harga instalasi ke Teknisi.**
   - File quotation dari Sales tetap wajib.
   - Balasan Teknisi tetap berupa file perhitungan + catatan.
   - Tab di Sales diganti label menjadi **Installation Price** supaya tidak
     tertukar dengan permintaan harga produk.

2. **Estimator menjadi modul sendiri.**
   - Tabel baru: `estimator_requests` dan `estimator_request_items`.
   - Sales membuat permintaan dari Lead/Quotation (tombol Request Product
     Price) atau dari menu Estimator.
   - File permintaan opsional; minimal satu item.
   - Balasan Estimator wajib memuat supplier, harga modal, status stok
     (Ready/Indent + estimasi), dan garansi.
   - Harga modal disimpan ke `products.cost_price` dan
     `product_price_logs`; `products.default_price` tidak disentuh.
   - Harga jual default hanya boleh diubah Admin/Purchasing lewat Master
     Produk.

3. **Antrean per PT dan riwayat harga.**
   - Antrean menampilkan status Menunggu Estimator / Harga Sudah Diinput,
     jumlah item terjawab, umur request, dan tanggal update otomatis.
   - Riwayat harga modal per produk, supplier, dan customer.
   - Report Excel Estimator tetap tersedia.

4. **Stok dan garansi terbawa ke quotation.**
   - Disimpan per item dan diringkas di header quotation
     (`delivery_terms`, `warranty_terms`).

## 5. Ruang lingkup

### Termasuk

- Modul Estimator terpisah dari Price Request instalasi.
- Pemisahan harga modal dan harga jual.
- Permintaan produk tanpa file, balasan tanpa file.
- Validasi supplier, stok, dan garansi pada balasan Estimator.
- Riwayat harga terbaru dan tampilan tanggal otomatis.
- Report mingguan Estimator.
- Peninjauan data produk yang terdampak lewat daftar Review Prices.

### Tidak termasuk

- Perhitungan margin dan harga jual di quotation (Inside Sales/Sales).
- Nomor quotation.
- Pembelian ke supplier dan purchasing saving (Admin/Purchasing).

## 6. Langkah pengerjaan

### A. Modul Estimator

1. Sales membuat permintaan berisi customer/PT, lead/project/quotation
   opsional, catatan, dan daftar item.
2. Estimator membalas per item dengan supplier, harga modal, stok, dan
   garansi.
3. Sales menerima atau meminta revisi; status dan tanggal update tercatat
   otomatis.
4. Notifikasi dikirim ke Estimator saat request baru dan ke Sales saat harga
   sudah diisi.

### B. Harga modal dan harga jual

1. Harga Estimator masuk ke riwayat harga produk dan `products.cost_price`.
2. `products.default_price` tidak boleh diubah oleh input Estimator.
3. Master Produk menampilkan harga jual default dan harga modal terbaru
   secara terpisah.

### C. Riwayat dan report

1. Setiap harga menghasilkan baris riwayat: produk, supplier, harga,
   tanggal otomatis, user, stok, garansi, dan catatan.
2. Riwayat ditampilkan dari terbaru ke terlama.
3. Export Excel memuat peminta, PT, item, harga, supplier, Estimator,
   tanggal selesai, dan status.

### D. Peninjauan data lama

1. Produk yang harga jualnya sama dengan harga modal terbaru masuk daftar
   Review Prices.
2. Perbaikan harga jual dilakukan manual, tidak ada reset otomatis.

## 7. Dependensi

- **Inside Sales:** menarik harga modal Estimator ke item quotation, lalu
  memakai harga jual default produk.
- **Sales:** memakai harga modal untuk menyusun margin quotation.
- **Admin/Purchasing:** mengubah harga jual default dan meninjau Review
  Prices.

## 8. Kriteria selesai & cara verifikasi

### Kriteria selesai

- Input harga Estimator tidak mengubah harga jual default.
- Harga modal terbaru muncul saat Inside Sales memilih produk.
- Permintaan dan balasan Estimator bisa dikirim tanpa file, asalkan item
  harga terisi.
- Sales peminta mendapat notifikasi saat harga modal selesai diisi.
- Riwayat harga menampilkan supplier, tanggal, stok, garansi, dan user.
- Report mingguan tetap bisa diexport.
- Price Request instalasi tetap berjalan seperti sebelumnya.

### Cara testing

1. Login Sales → Lead/Quotation → **Request Product Price**.
   - **Hasil:** form permintaan produk terbuka dan terkirim ke Estimator.
2. Login Estimator → **Estimator → Queue** → buka request.
   - **Hasil:** item bisa diisi supplier, harga modal, stok, dan garansi.
3. Login Sales → buka request yang sudah dijawab.
   - **Hasil:** harga tampil dan Sales bisa menerima atau minta revisi.
4. Login Inside Sales → buat quotation → pilih produk.
   - **Hasil:** harga modal terisi dari Estimator; harga jual memakai
     default produk; stok dan garansi ikut ke quotation.
5. Login Admin/Purchasing → Master Produk → Review Prices.
   - **Hasil:** harga jual default bisa diperbaiki tanpa mengubah harga
     modal.
6. Buka **Estimator → Price History** dan export report.
   - **Hasil:** riwayat dan report memuat data yang benar.

## 9. Catatan status

Implementasi modul sudah ada di branch `feature/sales-inside` dan sudah
melewati test terkait. Status plan menjadi **Selesai** setelah di-merge ke
`main` dan dicek user di layar.
