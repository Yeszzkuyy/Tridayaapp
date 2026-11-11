# PLAN-ESTIMATOR-INTERVIEW-USERSALES: Sumber Harga Modal & Riwayat Harga

**Status Pengerjaan:** Belum Dikerjakan
**Work:** INTERVIEW-USERSALES
**PIC:** Yeski
**Menu:** Estimator (Price Request, Products, Riwayat Harga)
**Sumber:** Wawancara Sales User Adi Santosa
**Terakhir diperbarui:** 2026-11-11

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

## 4. Ruang lingkup

### Termasuk

- Memisahkan harga modal dan harga jual.
- File Price Request/balasan menjadi opsional.
- Validasi harga item dan supplier.
- Riwayat harga terbaru dan tampilan tanggal.
- Report mingguan Estimator.
- Peninjauan data produk yang terdampak.

### Tidak termasuk

- Perhitungan margin dan harga jual di quotation (Inside Sales/Sales).
- Nomor quotation.
- Pembelian ke supplier dan purchasing saving (Admin/Purchasing).

---

## 5. Langkah pengerjaan

### A. Pisahkan harga modal dan harga jual

1. Harga yang diinput Estimator disimpan ke:
   - riwayat harga produk (`ProductPriceLog`),
   - `products.cost_price` sebagai harga modal terbaru.
2. `products.default_price` **tidak boleh** diubah oleh input Estimator.
3. Harga jual default hanya boleh diubah lewat Master Produk oleh role yang
   berwenang (Sales/Admin/Estimator bila disetujui).
4. Inside Sales membaca:
   - harga modal dari riwayat Estimator terbaru,
   - harga jual dari `default_price` produk.
5. Tampilkan di Master Produk:
   - Harga Jual Default.
   - Harga Modal Terbaru.
   - Supplier dan tanggal update terakhir.

### B. Permintaan harga lebih fleksibel

1. Price Request dari Sales:
   - File quotation/BOQ menjadi opsional.
   - Minimal isi customer/PT, kebutuhan, dan daftar barang yang diminta.
2. Balasan Estimator:
   - File menjadi opsional.
   - Wajib isi minimal satu harga item ATAU satu file.
   - Validasi: tidak boleh mengirim balasan kosong.
   - Setelah balasan dikirim, Sales peminta mendapat notifikasi bahwa harga
     modal sudah tersedia.
3. Harga item:
   - Produk wajib dipilih.
   - Supplier dianjurkan wajib untuk harga baru.
   - Harga harus lebih dari 0.
   - Catatan vendor/MOQ opsional.

### C. Riwayat harga

1. Setiap input menghasilkan baris riwayat:
   - produk, supplier, harga, tanggal otomatis, user, catatan.
2. Riwayat ditampilkan dari terbaru ke terlama.
3. Tandai jelas “harga modal” agar tidak tertukar dengan harga jual.
4. Jika ada harga baru, harga terbaru menjadi acuan, tetapi riwayat lama tetap
   tersimpan.

### D. Report mingguan

1. Pertahankan export Excel yang sudah ada.
2. Pastikan kolom:
   - tanggal request,
   - Sales/AM peminta,
   - customer/PT,
   - project/kebutuhan,
   - item dan harga modal,
   - supplier,
   - Estimator,
   - tanggal selesai,
   - status.
3. Default periode minggu berjalan; bisa ganti rentang tanggal.

### E. Peninjauan data lama

1. Cari produk yang `default_price` sama dengan harga modal terbaru dan
   kemungkinan tertimpa.
2. Tampilkan daftar tinjauan ke Admin/Estimator; jangan reset otomatis.
3. Setelah disetujui, harga jual default diperbaiki manual atau lewat
   persetujuan.

---

## 6. Dependensi

- **Inside Sales:** memakai harga modal dan harga jual terpisah.
- **Sales:** memakai harga modal untuk menyusun margin quotation.
- **Admin/Purchasing:** supplier dan harga beli aktual.

---

## 7. Kriteria selesai & cara verifikasi

### Kriteria selesai

- Input harga Estimator tidak mengubah harga jual default.
- Harga modal terbaru muncul saat Inside Sales memilih produk.
- Price Request dan balasan bisa dikirim tanpa file, asalkan harga item diisi.
- Sales peminta mendapat notifikasi saat harga modal selesai diisi.
- Riwayat harga menampilkan supplier, tanggal, dan user.
- Report mingguan tetap bisa diexport.
- Tidak ada produk yang otomatis berubah harga jual karena input Estimator.

### Cara testing

1. Login Sales → buat Price Request tanpa file, isi customer dan kebutuhan.
   - **Hasil:** request tersimpan.
2. Login Estimator Vanesha → balas dengan 2 harga item tanpa file.
   - **Hasil:** balasan tersimpan; tidak ada error wajib file; Sales peminta
     mendapat notifikasi.
3. Buka Master Produk:
   - **Hasil:** harga modal terbaru berubah; harga jual default tetap.
4. Login Inside Sales → pilih produk tersebut.
   - **Hasil:** harga modal terisi dari Estimator; harga jual memakai default
     produk.
5. Isi harga jual lebih tinggi:
   - **Hasil:** margin muncul, bukan 0.
6. Buka riwayat produk:
   - **Hasil:** harga lama dan baru tampil dari terbaru ke terlama.
7. Export Report Estimator:
   - **Hasil:** berisi peminta, PT/customer, item, harga, dan tanggal.

---

## 8. Keputusan yang perlu dijawab

1. Apakah supplier wajib diisi untuk setiap harga modal baru?
2. Siapa saja yang boleh mengubah harga jual default produk: Sales, Admin,
   Estimator, atau hanya Super Admin?
3. Untuk produk lama yang harga jualnya sudah tertimpa, apakah harga jual
   dikembalikan manual satu per satu atau ada persetujuan khusus?
4. Apakah Price Request tetap perlu file untuk kasus tertentu, atau selalu
   opsional?
