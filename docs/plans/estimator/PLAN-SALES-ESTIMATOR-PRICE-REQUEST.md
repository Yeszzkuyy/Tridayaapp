# PLAN-SALES-ESTIMATOR-PRICE-REQUEST: Ekosistem Estimator, Histori Harga Real-Time & Export Excel

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Presales / Price Request & Master Produk
**Branch:** `feature/sales-price-sync` & `feature/sales-flow`
**Terakhir diperbarui:** 2026-11-08

---

## 1. Ringkasan & Tujuan Operasional
Berdasarkan arahan wawancara Pimpinan (Bapak):
1. **Pemisahan Peran Estimator & Sales:** Ketika AM/Sales meminta estimasi harga produk/jasa, permintaan diproses oleh **Estimator** yang mencari dan mengonfirmasi harga ke vendor/supplier.
2. **Histori Harga Real-Time Otomatis:** Setiap kali Estimator menginput atau memperbarui harga barang (baik lewat Master Produk maupun saat merespons Price Request), sistem **secara otomatis mencatat log waktu dan pengubah** (`created_at = now()` dan `updated_by = auth()->id()`) ke tabel `product_price_logs` tanpa perlu pengetikan manual tanggal.
3. **Multi-Version Price Picker di Form Penawaran (Inside Sales / Sales):** Saat menyusun BOQ penawaran (`proposals`), Inside Sales atau Sales dapat melihat riwayat harga barang dari supplier yang berbeda, memilih harga terbaru (default) atau harga riwayat terdahulu, dan otomatis mengisi harga modal (`unit_cost`) untuk mengunci akurasi margin keuntungan.
4. **Laporan & Export Excel Mingguan Estimator:** Estimator memiliki fitur unduh laporan mingguan (file `.xlsx`) yang merekap siapa saja AM/Sales yang meminta harga, untuk pelanggan PT apa, item barang apa saja, dan status penyelesaiannya.

---

## 2. Rincian Perubahan per Komponen

### A. Skema Basis Data (Migrasi Baru)
* **File Migrasi:** `database/migrations/2026_11_08_000001_create_product_price_logs_and_enhance_price_requests.php`
* **Struktur Tabel `product_price_logs`:**
  * `id`: Primary key.
  * `product_id`: Foreign key ke `products` (cascade).
  * `supplier_id`: Nullable foreign key ke `suppliers` (null on delete).
  * `price`: `decimal(15, 2)` (nominal harga acuan/beli).
  * `currency`: `string(10)`, default `'IDR'`.
  * `price_request_id`: Nullable foreign key ke `price_requests` (null on delete).
  * `updated_by`: Foreign key ke `users` (pencatat harga).
  * `notes`: `nullable string` (catatan vendor, MOQ, atau kondisi garansi).
  * `timestamps`: `created_at` berfungsi sebagai penanda waktu real-time pencatatan harga.
* **Penyempurnaan Tabel `price_requests`:**
  * Memastikan kolom relasi lengkap: `customer_id` (nullable), `lead_id` (nullable), `project_id` (nullable), `requested_by` (AM peminta), dan `handled_by` (Estimator penanggung jawab).

### B. Model & Role Akses
* **File:** `app/Models/ProductPriceLog.php`, `app/Models/Product.php`, `app/Models/PriceRequest.php`, `database/seeders/RoleAndPermissionSeeder.php`
* **Relasi:**
  * `Product` hasMany `priceLogs` (ordered by `created_at` DESC).
  * `ProductPriceLog` belongsTo `product`, `supplier`, `updater` (`User`), dan `priceRequest`.
* **Hak Akses:**
  * Role `estimator` (atau penambahan permission `manage-price-requests` dan `manage-products`) untuk memasukkan data harga.
  * Role `inside-sales` dan `sales` diberikan izin membaca riwayat harga untuk penyusunan penawaran (`proposals`).

### C. Auto-Log Pencatatan Harga Real-Time
* **File:** `app/Http/Controllers/ProductController.php`, `app/Http/Controllers/PriceRequestController.php`
* **Logika:**
  * Saat Estimator menambah/mengubah harga di form Master Produk atau menyelesaikan tiket `PriceRequest`, sistem otomatis:
    1. Membuat entri baru di `product_price_logs`.
    2. Memperbarui `default_price` pada master `products`.
  * Menampilkan badge ringkasan pada form: *"Terakhir di-update: [Tanggal & Jam] oleh [Nama] — Harga: Rp [Nominal] (Supplier: [Nama Supplier])"*.

### D. Multi-Version Price Picker pada Form Quotation
* **File:** `app/Http/Controllers/ProposalController.php`, `resources/views/sales/proposals/partials/items.blade.php` (atau file form terkait)
* **Tampilan & Interaksi:**
  * Saat memilih item barang dari katalog, sistem menampilkan riwayat harga:
    * `[TERBARU - Tanggal] Rp X.XXX.XXX (Supplier A)` *(Terpilih default)*
    * `[Riwayat - Tanggal Lama] Rp Y.YYY.YYY (Supplier B)`
  * Saat salah satu opsi diklik, nominal harga jual dan harga modal (`unit_cost`) langsung terisi otomatis ke baris item BOQ.

### E. Laporan & Export Excel Mingguan Estimator
* **File:** `app/Http/Controllers/PriceRequestController.php`, `routes/web.php`
* **Rute Baru:** `GET /sales/price-requests/export-excel` (`PriceRequestController@exportExcel`)
* **Spesifikasi:**
  * Filter pencarian: Rentang tanggal (default: Minggu Ini), AM/Sales Peminta (`requested_by`), Customer (`customer_id`), dan status.
  * Tombol **"Export Report Estimator (.xlsx)"** menggunakan pustaka `PhpOffice\PhpSpreadsheet`.
  * Kolom laporan: `No`, `Tanggal Request`, `AM / Sales Peminta`, `Customer / PT`, `Proyek / Kebutuhan`, `Item Barang & Harga Estimasi`, `Estimator Penanggung Jawab`, `Tanggal Selesai`, dan `Status`.

---

## 3. Rencana Pengujian (Testing)
1. **Uji Auto-Log:** Input perubahan harga pada produk, pastikan tabel `product_price_logs` otomatis bertambah baris baru dengan user dan timestamp yang tepat.
2. **Uji Price Picker Quotation:** Buat penawaran baru, pilih barang yang memiliki beberapa histori harga, pastikan harga terbaru otomatis terpilih dan riwayat harga sebelumnya bisa dipilih.
3. **Uji Export Excel:** Jalankan ekspor Excel pada Price Request, buka file unduhan, pastikan format data dan filter minggu berjalan sesuai.

