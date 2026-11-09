# PLAN-SALES-DASHBOARD-CUSTOMER-HUB: Privasi Sales, Drill-Down Bu Yanita & Customer-Centric Hub

**Status Pengerjaan:** Siap Dikerjakan  
**Menu:** Sales / Dashboard & Management  
**Branch Terkait:** `feature/sales-flow` & `feature/management`  
**Terakhir Diperbarui:** 2026-11-08  

---

## 1. Ringkasan & Tujuan Operasional
Berdasarkan arahan wawancara Pimpinan (Bapak) dan kebutuhan supervisi Lead of Sales (Bu Yanita):
1. **Isolasi Privasi Sesama Sales:** Sales (Irfan, Adi, Audi, Deby, dll) dilarang saling mengintip performa, omzet, dan daftar prospek sales lain. Komponen leaderboard tim dan dropdown nama sales lain disembunyikan dari dashboard sales murni.
2. **Hak Supervisi Drill-Down Bu Yanita (`manage-sales-leads`):** Bu Yanita sebagai pimpinan sales memiliki akses penuh untuk melihat rekapitulasi target seluruh sales, mengklik nama salah satu sales, dan langsung membuka tampilan dashboard serta daftar customer milik sales tersebut secara transparan.
3. **Customer-Centric Hub di Dashboard Sales:** Mengubah dashboard sales dari sekadar kumpulan angka metrik menjadi *Workspace Operasional Pelanggan*, menampilkan daftar customer yang dipegang sales bersangkutan, dilengkapi search bar cepat, update DAR terakhir, dan arsip dokumen.
4. **Aksi Cepat Harian (Quick Action Modal):** Dua modal Alpine.js di tiap baris customer: `+ Isi Update (DAR)` dan `+ Upload Dokumen / PO`.
5. **Anti-Ghosting Guard:** Sales diwajibkan mengisi minimal 1 catatan DAR sebelum diizinkan memajukan status ke pembuatan penawaran (Quotation) atau handoff PO.

---

## 2. Rincian Perubahan per Komponen

### A. Hak Akses & Privasi (Role & Permission)
* **File:** `database/seeders/RoleAndPermissionSeeder.php`
* **Perubahan:**
  * Cabut permission `view-admin` dari role `sales`, `sales-nti`, `sales-mgk`, `sales-tps`, dan `sales-wani`.
  * Sales hanya memegang `manage-sales`, `view-sales`, `view-customer`, dan `view-trash`.
  * Role `management` (Bu Yanita) tetap memegang `manage-sales-leads` dan seluruh `view-*` untuk keperluan supervisi lintas divisi.

### B. Dashboard Sales Pribadi
* **File:** `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ManageSalesController.php`, `resources/views/sales/dashboard.blade.php`
* **Logika & Tampilan:**
  * Cek otorisasi: Jika pengguna yang login adalah role `sales` murni (tanpa `manage-sales-leads`, `management`, `ceo`, atau `super-admin`):
    * Sembunyikan blok `salesLeaderboard` (Peringkat & Target Bulanan Sales).
    * Tampilkan **Kartu Target & Progress Bar Personal**: Target Omzet Rp (yang diatur Bu Yanita), Realisasi Masuk (Rp), dan persentase capaian.
    * Tampilkan **Tabel Customer-Centric Hub** (lihat poin C).

### C. Tabel Customer-Centric Hub di Dashboard
* **Query Data:**
  * Mengambil data `Customer` yang memiliki relasi aktif dengan sales bersangkutan (`assigned_to` pada lead, atau pemilik proposal/proyek).
  * Diurutkan berdasarkan aktivitas terbaru (`updated_at` / follow-up terakhir DESC).
  * Dilengkapi form pencarian `?q=NamaPT` dan filter status tahapan.
* **Kolom Tabel:**
  1. **Nama PT & Entitas:** Nama perusahaan customer + badge entitas (`NTI/MGK/TPS/WANI`).
  2. **Tahap Progres Terkini:** Badge tahapan (misal: *Lead Hot*, *Quotation Sent*, *PO Verified*, *Project Running*, *Invoice Paid*).
  3. **Catatan DAR Terakhir:** Menampilkan interaksi follow-up terakhir dan tanggalnya. Jika belum ada, tampilkan teks *muted italic* `"Belum ada catatan update"` (anti-hyphen `-` mentah).
  4. **Jumlah Dokumen:** Counter berkas terunggah (`customer_documents` + `proposals.po_document_path` + `project_documents`).
  5. **Aksi Cepat:** Dua tombol modal Alpine.js:
     * `+ Isi Update (DAR)`: Modal cepat mencatat interaksi harian (*Call/WA/Visit/Note*, catatan progres, tanggal follow-up berikutnya) yang langsung tersimpan ke `follow_ups` & `lead_activities`.
     * `+ Upload Dokumen / PO`: Modal cepat unggah berkas langsung ke profil customer via `POST /customers/{customer}/documents`.

### D. Fitur Drill-Down Supervisi Bu Yanita
* **File:** `app/Http/Controllers/ManageSalesController.php`, `routes/web.php`, `resources/views/manage-sales/*`
* **Rute Baru:** `GET /manage-sales/sales-rep/{user}` (`ManageSalesController@showSalesRep`)
* **Middleware:** `permission:manage-sales-leads|view-monitoring`
* **Alur Penggunaan:**
  * Di halaman `/manage-sales` atau `/manage-sales/team-activity`, Bu Yanita melihat daftar seluruh sales representative beserta progress bar targetnya.
  * Bu Yanita dapat mengklik nama salah satu sales (misal: Irfan).
  * Sistem membuka halaman snapshot dashboard dan tabel customer milik sales tersebut lengkap dengan histori DAR dan status penawarannya.
  * Jika user biasa (`sales`) mencoba membuka URL `/manage-sales/sales-rep/{user}`, sistem memblokir dengan `403 Forbidden`.

### E. Anti-Ghosting Guard
* **File:** `app/Http/Controllers/ProposalController.php`, `app/Models/Lead.php`
* **Aturan:**
  * Sebelum sales dapat memajukan tahapan lead ke pembuatan penawaran komersial (Quotation) atau mengunggah Customer PO, sistem memvalidasi bahwa lead/customer tersebut telah memiliki minimal 1 catatan log DAR / `follow_ups`.

---

## 3. Rencana Pengujian (Testing)
1. **Uji Privasi Sales:** Login sebagai sales A, pastikan nama sales B dan leaderboard tidak muncul di layar dashboard sales A.
2. **Uji Drill-Down Bu Yanita:** Login sebagai Bu Yanita (`management`), buka daftar sales, klik nama sales A, pastikan dashboard dan customer sales A tampil lengkap.
3. **Uji Keamanan Route:** Login sebagai sales A, coba akses `/manage-sales/sales-rep/{id_sales_B}`, pastikan muncul error 403 Forbidden.
4. **Uji Quick Modal DAR:** Isi DAR lewat modal di baris customer dashboard, pastikan langsung muncul di log aktivitas dan file export DAR.
5. **Uji Anti-Ghosting:** Coba buat proposal pada lead baru tanpa follow-up, pastikan sistem menolak dengan pesan peringatan yang jelas.

