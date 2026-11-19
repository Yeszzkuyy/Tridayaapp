# PLAN-ADMIN-SALES-TARGET-SYNC: Otomatisasi Realisasi Target Sales dari Pembayaran Finance

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Finance / Billing & Sales Target KPI
**Branch:** `feature/div-panel` & `feature/sales-flow`
**Terakhir diperbarui:** 2026-11-08

---

## 1. Ringkasan & Tujuan Operasional
Berdasarkan arahan wawancara Pimpinan (Bapak):
1. **Realisasi Berbasis Uang Masuk:** Target sales yang ditetapkan oleh Bu Yanita (`sales_targets`) harus otomatis bertambah dan menggerakkan progress bar persentase ketika Admin Keuangan (April) mencatat pelunasan tagihan pelanggan (status invoice menjadi **Paid / Lunas**).
2. **Dua Indikator Berdampingan:**
   * **Deal / PO Masuk:** Nilai total pesanan yang sudah berstatus Customer PO terverifikasi (`proposals.po_verified_at`).
   * **Realisasi Closing / Paid:** Nilai uang pembayaran yang sudah benar-benar masuk ke kas perseroan (menjadi indikator persentase capaian target utama).
3. **Pemberitahuan Otomatis Real-Time:** Begitu pembayaran lunas divalidasi oleh Finance, sistem mengirimkan notifikasi instan (`InvoicePaidSalesNotification`) ke Sales pemilik proyek dan Bu Yanita.

---

## 2. Rincian Perubahan per Komponen

### A. Penyempurnaan Kalkulasi Realisasi di `SalesKpiService`
* **File:** `app/Services/SalesKpiService.php`
* **Logika:**
  * Metode `getUserKpi()` disesuaikan agar mengambil data:
    1. `verified_po_revenue`: Akumulasi nilai `grand_total` dari proposal berstatus `accepted` yang telah memiliki `po_verified_at` dalam periode bulan berjalan.
    2. `paid_revenue`: Akumulasi pembayaran lunas dari invoice pelanggan (`invoices.status = 'paid'`) atau total pembayaran bertahap (`payments.amount`) pada invoice yang proyek/proposalnya dimiliki oleh sales bersangkutan.
  * Persentase capaian target omzet dihitung berbasis `paid_revenue / target_revenue * 100%`.
  * Kedua angka (Nilai PO Masuk dan Realisasi Lunas) dikembalikan dalam struktur KPI untuk ditampilkan berdampingan di dashboard.

### B. Otomatisasi Trigger saat Pembayaran Masuk (Admin Finance)
* **File:** `app/Services/AdminService.php`, `app/Http/Controllers/AdminController.php`
* **Logika:**
  * Di dalam `AdminService::createPayment()` atau saat status Invoice berubah menjadi `paid`:
    1. Cari sales penanggung jawab proyek/proposal terkait.
    2. Perbarui agregasi data realisasi pada `sales_targets`.
    3. Buat dan kirimkan notifikasi `InvoicePaidSalesNotification` (melalui database notification dan Reverb realtime broadcast).
    4. Pesan notifikasi: *"Tagihan Invoice [Nomor Invoice] untuk [Nama Customer] telah lunas (Close). Capaian target Anda otomatis bertambah menjadi [X]%!"*.

### C. Class Notifikasi Baru
* **File:** `app/Notifications/InvoicePaidSalesNotification.php`
* **Spesifikasi:**
  * Mengimplementasikan `ShouldQueue` agar proses pencatatan pembayaran di finance tetap instan tanpa terhambat pengiriman email/push.
  * Saluran notifikasi: `database`, `broadcast` (Reverb), dan opsional `mail` / WebPush.

---

## 3. Rencana Pengujian (Testing)
1. **Uji Transaksi Pembayaran:** Admin Finance menginput data pembayaran bertahap sampai invoice lunas (`paid`), pastikan status invoice berubah otomatis.
2. **Uji Kenaikan Target Sales:** Buka dashboard sales pemilik proyek, pastikan progress bar target persentase otomatis bergerak naik.
3. **Uji Pengiriman Notifikasi:** Pastikan lonceng notifikasi Sales dan Bu Yanita menyala dan menampilkan pesan pelunasan tagihan.

