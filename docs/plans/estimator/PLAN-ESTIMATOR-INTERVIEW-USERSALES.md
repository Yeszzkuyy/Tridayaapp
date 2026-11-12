# PLAN-ESTIMATOR-INTERVIEW-USERSALES: Sumber Harga Modal & Workspace Estimator

**Status Pengerjaan:** Siap Dikerjakan (Revisi Final Paket Simplifikasi)
**Work:** INTERVIEW-USERSALES
**PIC:** Yeski
**Menu:** Estimator (Queue, Riwayat Harga, Pricelist, Suppliers) & Sales Request
**Sumber:** Wawancara Sales User Adi Santosa & Feedback Evaluasi Layar (12 November 2026)
**Terakhir diperbarui:** 2026-11-12

---

## 1. Tujuan & Hasil Akhir

Menjadikan modul Estimator sangat simpel, cepat, dan siap pakai untuk operasional harian:

1. **Komunikasi Cepat ke Supplier via WhatsApp:**
   - Estimator bisa langsung menanyakan ketersediaan stok & harga modal ke distributor lewat tombol WhatsApp otomatis sekali klik, dengan draf pesan yang sudah memuat daftar barang, jumlah, dan pertanyaan keep harga tanpa perlu ketik ulang.
2. **Input Cepat & Tidak Ribet:**
   - Mengisi masa berlaku harga (*keep* harga) cukup 1-klik lewat tombol cepat `[+3 Hari]`, `[+1 Minggu]`, `[+2 Minggu]`, `[+1 Bulan]` tanpa membuka kalender.
   - Jika produk sudah pernah dicek sebelumnya dan masih berlaku, tersedia tombol 1-klik `Pakai Data Terakhir`.
3. **Pricelist Jelas & Bisa Input Mandiri:**
   - Estimator bisa langsung menambahkan/memperbarui harga modal produk di Pricelist kapan saja tanpa menunggu tiket dari Sales.
   - Desain Pricelist diperbesar, tegas, kontras, dengan badge status masa berlaku yang jelas (🟢 Hijau = Masih Berlaku, 🟡 Kuning = Perlu Cek Ulang).
4. **Perapian Alur Sales & Tampilan Web:**
   - Tab *Product Price* tetap ada di menu Request Sales; halaman queue mandiri
     untuk Sales dialihkan ke tab tersebut agar tidak ada halaman ganda.
   - Halaman buat request menjadi *full-width* dengan pencarian produk (*searchable picker*, bukan dropdown panjang).
   - Setelah simpan, sistem menampilkan notifikasi *toast* standar pojok layar.
   - Tombol *Export Report Excel* mingguan dipasang di antrean Queue.

---

## 2. Rincian Perubahan per Fitur

### A. WhatsApp Generator & Form Jawab Harga Cepat (Detail Request)
- **Tombol "Tanya Supplier via WhatsApp":**
  - Estimator memilih supplier distributor.
  - Sistem membuat tautan WhatsApp ke nomor PIC supplier dengan pesan otomatis rapi:
    > *"Halo [Nama Supplier], saya [Nama Estimator] dari 3DY. Mau cek stok dan harga modal untuk barang berikut ya:*
    > *1. [Barang A] — [Qty] [Unit]*
    > *2. [Barang B] — [Qty] [Unit]*
    > *Apakah ready stok atau indent? Dan harga ini bisa di-keep sampai kapan ya? Terima kasih."*
- **Tombol Pintas Masa Berlaku (Keep Time):**
  - Di samping kolom tanggal *Valid Until*, dipasang tombol `+3 Hari`, `+1 Minggu`, `+2 Minggu`, `+1 Bulan` yang otomatis mengisi tanggal.
- **Tombol "Pakai Data Terakhir":**
  - Jika item katalog sudah memiliki log harga modal terakhir di sistem, muncul ringkasan harga terakhir dan tombol 1-klik untuk menyalin supplier, harga, dan garansi.

### B. Perapian Menu Sales & Navigasi Back
- **Tab Product Price tetap di Menu Sales:**
  - Tab ke-4 *Product Price* pada `sales.requests` dipertahankan agar permintaan
    harga produk Sales sejajar dengan Inside Sales, Installation Price, dan Engineer.
  - Halaman queue mandiri `estimator.requests.index` dialihkan ke tab tersebut
    untuk user Sales; Estimator tetap memakai Queue sebagai workspace.
- **Perbaikan Tombol Back:**
  - Tombol kembali pada detail *price request* diarahkan tepat ke halaman induk asalnya tanpa looping ke halaman detail yang sama.

### C. Tampilan Request Price Full-Width & Searchable Item Picker
- **Desain Full-Width:**
  - Menghapus pembungkus sempit (`max-w-4xl`) pada halaman buat request produk (`estimator.requests.create`) agar selaras dengan standar tata letak aplikasi.
- **Pencarian Produk (Searchable Picker):**
  - Mengganti `<select>` statis dengan komponen input pencarian produk interaktif (ketik nama/SKU langsung muncul hasil filter instan) atau bisa ketik deskripsi bebas untuk barang custom.
- **Toast Notifikasi Standar:**
  - Menggunakan flash session standar web yang otomatis memicu pop-up toast di pojok layar (menggantikan modal checklist besar).

### D. Penyempurnaan Menu Pricelist untuk Estimator
- **Input Cepat Mandiri:**
  - Estimator dapat langsung memperbarui harga modal pada produk di menu Pricelist kapan saja dengan tombol aksi yang jelas.
- **Redesain Visual Lebih Jelas:**
  - Mengubah tampilan baris produk yang sebelumnya berukuran teks kecil (11px) menjadi kartu/tabel berukuran standar yang mudah dibaca, kontras, dan terstruktur.
  - Menampilkan badge status masa berlaku:
    - 🟢 **Aktif (Berlaku s/d [Tanggal])**: Harga masih aman dipakai.
    - 🟡 **Kadaluarsa / Perlu Cek Ulang**: Tanggal keep harga sudah lewat.
- **Tombol Export Excel di Queue:**
  - Memasang tombol unduh laporan Excel mingguan di halaman Queue Estimator (`estimator.requests.index`).

---

## 3. File yang Disentuh

1. `resources/views/estimator/requests/show.blade.php` (Tombol WhatsApp otomatis, tombol keep 1 minggu, tombol pakai harga terakhir, perbaikan back & feedback).
2. `resources/views/estimator/requests/create.blade.php` (Tampilan full-width, searchable product picker, perbaikan tombol back).
3. `resources/views/estimator/requests/index.blade.php` (Tombol Export Excel di header/toolbar).
4. `resources/views/sales/requests/index.blade.php` (Pertahankan tab Product Price).
5. `resources/views/sales/products/index.blade.php` (Redesain Pricelist: teks lebih jelas, badge aktif/kadaluarsa, tombol update cepat).
6. `resources/views/sales/products/price.blade.php` (Tombol pintas keep harga +3hr/+1mgg pada form update modal).
7. `app/Http/Controllers/EstimatorRequestController.php` (Penyesuaian flash toast standar, data backUrl).
8. `app/Http/Controllers/SalesRequestHubController.php` (Tab Product Price + redirect Sales dari queue mandiri).
9. `tests/Feature/EstimatorRequestTest.php` & `tests/Feature/SalesRequestHubTest.php` (Verifikasi pengujian otomatis).

---

## 4. Kriteria Selesai & Cara Verifikasi

1. **Tanya WA Distributor:** Buka detail request Estimator → klik tombol Tanya Supplier via WA → terbuka pesan rapi berisi daftar item.
2. **Keep 1 Minggu:** Klik tombol `+1 Minggu` pada tanggal berlaku → tanggal terisi otomatis 7 hari ke depan.
3. **Pakai Data Terakhir:** Klik `Pakai Data Terakhir` → kolom supplier, harga, dan garansi terisi otomatis dari log sebelumnya.
4. **Menu Sales Rapi:** Buka menu Sales → Request → tab Product Price ada dan berisi permintaan milik Sales; URL queue mandiri dialihkan ke tab ini.
5. **Buat Request Full-Width & Search:** Buka form buat request → tampilan lebar penuh, input barang berupa pencarian interaktif.
6. **Toast Muncul:** Simpan data → muncul pop-up toast di pojok layar.
7. **Pricelist Jelas & Export Excel Ada:** Buka Pricelist → teks terbaca jelas, badge aktif/kadaluarsa terlihat; buka Queue → tombol Export Excel tersedia.
