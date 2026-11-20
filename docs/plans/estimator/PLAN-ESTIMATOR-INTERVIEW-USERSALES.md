# PLAN-ESTIMATOR-INTERVIEW-USERSALES: Sumber Harga Modal, Alur WhatsApp Supplier & Workspace Estimator

**Status Pengerjaan:** Siap Dikerjakan  
**Work:** INTERVIEW-USERSALES  
**PIC:** Yeski  
**Target Eksekutor:** Sesi Kerja Pengembang / AI Agent Berikutnya  
**Menu:** Estimator (Queue, Riwayat Harga, Pricelist, Suppliers) & Sales Request  
**Sumber:** Wawancara Sales User Adi Santosa, Audit Sistem & Feedback Evaluasi User (12–13 November 2026)  
**Terakhir diperbarui:** 2026-11-13  
**Baseline Git Commit:** `main` @ `cbdb4c2f`  

---

## 1. Ringkasan Eksekutif & Latar Belakang Masalah

Modul Estimator berfungsi sebagai sumber data **harga modal beli** (*cost price*) dari distributor/vendor resmi untuk kebutuhan penawaran (*quotation*) klien oleh Sales / Inside Sales. 

Berdasarkan hasil audit operasional dan feedback pengguna lapangan, saat ini terdapat **5 hambatan utama** yang membuat sistem terasa kaku, manual, dan membingungkan:

1. **Estimator "Kehilangan Kontak Supplier" Saat Ada Permintaan Baru:**
   - Ketika Sales membuat request baru (status `submitted`), item barang belum terikat ke supplier mana pun.
   - Komponen kontak supplier di halaman detail request saat ini hanya membaca supplier dari item yang sudah dijawab. Akibatnya, pada tiket baru kotak kontak supplier **hilang/kosong sama sekali**.
   - Estimator terpaksa berpindah-pindah menu, mencari nomor telepon di WhatsApp pribadi, lalu menyalin dan mengetik ulang daftar barang yang diminta Sales satu per satu.
2. **Pengisian Masa Berlaku (*Keep* Harga) Manual & Lambat:**
   - Distributor umumnya memberi kepastian masa tahan harga, misalnya: *"Harga ini kita keep 1 minggu ya"*.
   - Saat ini Estimator harus mengklik pemilih tanggal (*date picker*) HTML manual pada tiap-tiap baris item. Jika ada 10 barang, Estimator harus membuka kalender 10 kali. Belum ada tombol cepat `[+3 Hari]`, `[+1 Minggu]`, `[+2 Minggu]`.
3. **Data Harga di Pricelist Tulisannya Terlalu Kecil & Tidak Memiliki Status Keaktifan:**
   - Di menu Pricelist (`/products`), informasi harga modal bertumpuk rapat dengan ukuran teks sangat kecil (`text-[11px] text-slate-400`).
   - Tidak ada indikator visual yang membedakan apakah harga modal tersebut **masih berlaku (aktif)** atau **sudah kadaluarsa (lewat tanggal *valid until*)**, sehingga Estimator tidak tahu barang mana yang perlu ditanyakan ulang ke distributor.
4. **Navigasi Tab Sales Request Membingungkan ("Muter-Muter"):**
   - Di menu Sales Request (`/sales/requests`), tab ke-4 *Product Price* masih tampil.
   - Saat Sales membuka detail tiket dari tab tersebut lalu menekan tombol *Back*, sistem justru melempar Sales ke halaman antrean internal Estimator (`/estimator/requests`), bukan kembali ke menu Sales.
5. **Form Buat Request Masih Sempit (`max-w-4xl`) & Menggunakan Dropdown Statis 500 Barang:**
   - Halaman `estimator.requests.create` masih dibatasi lebar sempit.
   - Pilihan barang masih menggunakan elemen `<select>` dropdown konvensional yang memuat hingga 500 produk, bukan berupa kotak pencarian instan (*searchable picker*).
   - Tombol **Export Report Excel** mingguan belum dipasang di tampilan antrean (*Queue*).

---

## 2. Aturan & Konvensi Desain Website yang Wajib Dipatuhi (AGENTS.md)

Setiap implementasi kode wajib mematuhi standar berikut tanpa kompromi:

1. **Aturan Sudut & Bentuk Tombol (Wajib `rounded-xl`):**
   - Seluruh tombol wajib menggunakan `rounded-xl` (12px). Dilarang mencampur `rounded-lg` atau `rounded-md`.
   - Tombol simpan/submit utama: `<x-primary-button>` (`rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium`).
   - Tombol batal di form: `<x-secondary-button>`.
   - Tombol aksi baris/tabel/navigasi: `<x-icon-button>` (icon-only dengan `title`/`aria-label`).
   - Tombol kembali (Back): **Wajib** `<x-back-button>` (JANGAN membuat tombol kembali kustom atau icon-button panah sendiri).
2. **Lynk Dashboard Style & Zero Filler Text (§7.1 AGENTS.md):**
   - Dilarang menulis sub-judul basa-basi/dekoratif (*"Latest figures from..."*, *"Monitor project status..."*).
   - Open header style: Judul halaman menyatu langsung dengan background, sejajar dengan tombol aksi di sebelah kanan.
   - Tipografi angka utama tegas dan menggunakan kelas `tabular-nums`.
3. **Standar Notifikasi Pop-Up (Toast Standard):**
   - Gunakan mekanisme flash session standar web (`with('success', '...')`).
   - **Hapus parameter `->with('success_card', true)`** dari controller agar memicu pop-up toast bawaan aplikasi di pojok kanan bawah secara otomatis, bukan memunculkan kotak hijau/kartu besar statis.
4. **Pencarian Produk Interaktif (Searchable Combobox):**
   - Mengikuti pola snap-interaction `resources/views/sales/_customer-picker.blade.php`.

---

## 3. Rincian Teknis Perubahan per Berkas

### A. Tampilan Detail Request Estimator: `resources/views/estimator/requests/show.blade.php`
1. **Daftar Distributor & WhatsApp Generator Otomatis:**
   - Kirim seluruh daftar distributor aktif (`$allSuppliers = Supplier::active()->orderBy('name')->get()`) dari controller.
   - Sediakan panel cepat atau modal **"Tanya Supplier via WhatsApp"**:
     - Estimator memilih distributor target dari dropdown.
     - Sistem otomatis menyusun draf pesan WhatsApp via tautan `https://wa.me/{nomor}?text={pesan_url_encoded}`.
     - Format pesan otomatis:
       ```text
       Halo [Nama PIC / Supplier], saya [Nama Estimator] dari 3DY.
       Mau cek ketersediaan stok & harga modal untuk kebutuhan project kami:
       1. [Nama Barang A] - [Qty] [Unit]
       2. [Nama Barang B] - [Qty] [Unit]
       Apakah ready stock atau indent ya? Dan harga ini bisa di-keep sampai kapan? Terima kasih.
       ```
     - Tombol WhatsApp menggunakan `<x-icon-button icon="whatsapp">` yang langsung membuka tautan tersebut di tab baru.
2. **Tombol Pintas Masa Berlaku (*Keep Time*):**
   - Pada kolom `valid_until` di setiap baris item form balasan, sediakan tombol cepat:
     - `+3 Hari` (mengisi tanggal H+3)
     - `+1 Minggu` (mengisi tanggal H+7)
     - `+2 Minggu` (mengisi tanggal H+14)
   - Sediakan tombol **"Samakan Masa Berlaku Semua Item"** di header tabel agar 1 kali klik bisa mengisi tanggal semua baris sekaligus.
3. **Fitur "Pakai Data Terakhir" (Auto-Fill dari Pricelist):**
   - Jika item permintaan memiliki relasi `product_id` dan produk tersebut memiliki riwayat harga modal terakhir (`latestCostLog`):
     - Tampilkan ringkasan kecil di bawah nama barang: *"Terakhir: Rp [Harga] ([Nama Supplier], [Tanggal])"*.
     - Sediakan tombol pintas `Pakai Data Terakhir` yang langsung mengisi otomatis field `supplier_id`, `price`, `warranty_info`, dan `stock_status` baris tersebut.
4. **Perbaikan Navigasi Tombol Back:**
   - Jika user yang membuka halaman adalah Sales (`! $isEstimator`), tombol `<x-back-button>` mengarah ke menu asalnya (`route('sales.dashboard')` atau URL sebelumnya), bukan dipaksa ke antrean Estimator (`route('estimator.requests.index')`).

### B. Form Buat Request Produk: `resources/views/estimator/requests/create.blade.php`
1. **Layout Lebar Penuh (Full-Width):**
   - Hapus pembungkus sempit `<div class="mx-auto max-w-4xl">`, ganti dengan `<div class="space-y-6">` lebar penuh sesuai standar halaman web lainnya.
2. **Pencarian Produk Interaktif (Searchable Combobox):**
   - Hapus elemen `<select>` dropdown statis 500 produk.
   - Buat komponen picker pencarian produk yang ringan berbasis Alpine.js:
     - Pengguna dapat mengetik nama barang atau SKU.
     - Hasil filter instan muncul ke bawah dengan teks nama, SKU, dan satuan.
     - Jika dipilih, otomatis mengisi kolom deskripsi dan satuan barang.
     - Tetap mendukung input teks manual bebas jika barang belum terdaftar di katalog master.
3. **Pemberitahuan Sukses Standar (Toast):**
   - Mengarahkan kembali ke detail request dengan flash session standar yang memicu toast pop-up.

### C. Halaman Antrean Estimator: `resources/views/estimator/requests/index.blade.php`
1. **Pemasangan Tombol Export Report Excel:**
   - Di baris filter/toolbar atau header antrean, pasangkan tombol unduh laporan Excel mingguan yang mengarah ke `route('estimator.requests.export-excel', request()->query())`.
   - Menggunakan komponen: `<x-icon-button as="a" icon="download" href="{{ route('estimator.requests.export-excel', request()->query()) }}" label="{{ __('Export Excel') }}" title="{{ __('Export Excel (.xlsx)') }}" />`.

### D. Pembersihan Menu Sales: `resources/views/sales/requests/index.blade.php`
1. **Hapus Tab "Product Price":**
   - Hapus entri `'product-price'` dari array `$tabs`.
   - Menu Sales Request hanya melayani 3 tab resmi:
     1. `inside-sales` (Permintaan draf penawaran formal)
     2. `price` (Permintaan harga jasa/instalasi teknisi)
     3. `engineer` (Permintaan survei/lapangan teknisi)
   - Permintaan harga produk diajukan Sales langsung melalui tombol aksi di halaman **Lead** atau **Quotation**.

### E. Redesain Menu Pricelist: `resources/views/sales/products/index.blade.php` & `price.blade.php`
1. **Peningkatan Keterbacaan & Tipografi:**
   - Ubah teks harga modal dan riwayat dari `text-[11px] text-slate-400` menjadi ukuran `text-sm font-semibold` dengan warna kontras yang jelas (`text-slate-800 dark:text-slate-100`).
2. **Badge Status Masa Berlaku (Aktif vs Kadaluarsa):**
   - Jika `valid_until >= now()`: Tampilkan badge hijau `🟢 Aktif (s/d d M Y)`.
   - Jika `valid_until < now()` atau `valid_until` kosong: Tampilkan badge kuning/abu-abu `🟡 Perlu Cek Ulang` / `Kadaluarsa`.
3. **Tombol Pintas di Form Edit Harga Modal (`sales/products/price.blade.php`):**
   - Pasangkan tombol cepat `+3 Hari`, `+1 Minggu`, `+2 Minggu` di samping input `valid_until`.
4. **Navigasi Tombol Back:**
   - Jika user yang membuka adalah Estimator (`manage-price-requests` tanpa `manage-sales`), tombol Back mengarah ke `route('estimator.dashboard')`.

### F. Controller & Backend: `app/Http/Controllers/EstimatorRequestController.php` & `SalesRequestHubController.php`
1. Di `EstimatorRequestController@show`:
   - Pass variabel `$allSuppliers` yang memuat seluruh supplier aktif agar WhatsApp generator selalu memiliki daftar kontak distributor meskipun tiket belum dijawab.
2. Di `EstimatorRequestController@store`, `answer`, `accept`, `revision`:
   - Hapus chaining `->with('success_card', true)`. Cukup `->with('success', __('...'))` agar memicu pop-up toast standar sistem.
3. Di `SalesRequestHubController@index`:
   - Bersihkan penanganan tab `product-price` agar tidak melakukan query data yang sudah tidak digunakan di layar.

---

## 4. Rencana Pengujian dengan Metode Test-Driven Development (TDD)

Sesuai panduan TDD (Red-Green-Refactor), sesi pengembang berikutnya **WAJIB** menuliskan/menyesuaikan test terlebih dahulu sebelum mengubah kode view/controller:

### Siklus TDD:

```
[LANGKAH 1: RED]
Tulis pengujian otomatis di tests/Feature/EstimatorRequestTest.php & SalesRequestHubTest.php
Jalankan: php artisan test --filter=EstimatorRequestTest
Pastikan test GAGAL karena fitur baru/perubahan belum ada di kode.

[LANGKAH 2: GREEN]
Terapkan perubahan kode pada Blade views dan Controller.
Jalankan: php artisan test --filter=EstimatorRequestTest
Pastikan test BERUBAH MENJADI LULUS (GREEN).

[LANGKAH 3: REFACTOR]
Rapikan kode, pastikan format sesuai standar Pint (vendor/bin/pint --test).
Jalankan seluruh suite test modul terkait untuk memastikan zero regression.
```

### Skenario Test yang Wajib Dibuat / Diperbarui:

1. `test_show_page_provides_active_suppliers_for_whatsapp_inquiry()`:
   - Request baru berstatus `submitted` tanpa supplier pada itemnya harus tetap menerima data `$allSuppliers` di view dan merender tautan WhatsApp.
2. `test_sales_requests_hub_no_longer_displays_product_price_tab()`:
   - Membuka `sales.requests.index` memastikan tab `product-price` tidak lagi ada di respon HTML.
3. `test_estimator_queue_renders_export_excel_button()`:
   - Login sebagai Estimator membuka `estimator.requests.index` harus melihat tombol dengan tautan `route('estimator.requests.export-excel')`.
4. `test_pricelist_displays_valid_and_expired_badges()`:
   - Produk dengan log harga yang masih dalam masa berlaku menampilkan indikator aktif, sedangkan log yang sudah melewati tanggal menampilkan indikator kadaluarsa.
5. `test_back_button_redirects_appropriately_for_sales_and_estimator()`:
   - Memastikan tombol kembali tidak mengarahkan Sales ke antrean Estimator.

---

## 5. Panduan Verifikasi Manual di Layar (UAT)

Setelah pengembang menyelesaikan kode, lakukan verifikasi layar berikut sebelum melaporkan selesai ke User:

1. **Skenario Estimator (Queue & WhatsApp Generator):**
   - Login sebagai Estimator.
   - Buka menu **Estimator → Queue**.
   - Periksa keberadaan tombol **Export Excel** di header halaman.
   - Buka salah satu request baru (status `Submitted`).
   - Periksa bagian **Tanya Supplier via WhatsApp**: Pilih supplier, klik tombol WA, pastikan tautan WA terbuka dengan teks rapi berisi daftar item.
   - Klik tombol pintas `+1 Minggu` pada kolom masa berlaku, pastikan tanggal terisi 7 hari ke depan.
   - Tekan tombol **Send Prices**, pastikan pop-up toast muncul di pojok kanan bawah layar.
2. **Skenario Sales (Request Hub & Buat Request):**
   - Login sebagai Sales.
   - Buka menu **Sales → Request**.
   - Pastikan tab hanya ada 3 (Inside Sales, Installation Price, Engineer). Tab *Product Price* sudah tidak ada.
   - Buka menu **Sales → Lead** → klik tombol **Request Product Price**.
   - Pastikan tampilan form terbuka lebar penuh (*full-width*).
   - Ketik nama produk pada kolom pencarian barang, pastikan daftar filter muncul instan.
3. **Skenario Pricelist:**
   - Login sebagai Estimator, buka menu **Estimator → Pricelist**.
   - Pastikan teks harga dan supplier terbaca jelas dan tegas (bukan ukuran 11px buram).
   - Periksa badge warna: Produk yang masa berlakunya masih aktif bertanda hijau, yang sudah lewat bertanda kuning/abu.
   - Klik tombol **Update Price**, gunakan tombol `+1 Minggu`, lalu simpan.

---

## 6. Status Akhir Dokumen

Dokumen plan ini sudah final, terperinci, dan siap dieksekusi oleh sesi kerja atau pengembang berikutnya pada worktree masing-masing.
