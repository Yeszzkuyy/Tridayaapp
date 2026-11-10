# PLAN-SALES-INSIDE-DASHBOARD: Dashboard Kerja Inside Sales & Generate Quotation

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Inside Sales
**Branch:** `feature/sales-inside`
**Worktree:** `/home/yeski/3dyapp-inside`
**Terakhir diperbarui:** 2026-11-10

> Estimator memakai `PLAN-SALES-ESTIMATOR-DASHBOARD.md` dan
> `PLAN-SALES-ESTIMATOR-PRICE-REQUEST.md`. Plan ini fokus pada Inside Sales.

---

## 1. Tujuan & hasil akhir

- Inside Sales punya dashboard sendiri untuk melihat request dari Sales.
- Inside Sales mengisi item quotation dengan bantuan harga modal terbaru dari Estimator.
- Setelah item lengkap, Inside Sales langsung generate Quotation + PDF berkop PT.
- Sales menerima notifikasi, review, lalu mengirim quotation ke customer.

## 2. Ruang lingkup

Termasuk:

- Dashboard Inside Sales: antrean, status, aging, aksi cepat.
- Form item: produk, scope of supply, lead time, qty, harga jual, harga modal, margin.
- Form header quotation: no quotation, organization, address, contact, email, phone,
  project description, payment, date, kop PT.
- Generate Quotation: membuat `Proposal` + `ProposalItem` + PDF.
- Notifikasi ke Sales pemilik lead.
- Menu sidebar untuk Inside Sales (termasuk multi-role).

Tidak termasuk:

- Dashboard Estimator (plan terpisah).
- Perubahan alur PO/convert (plan terpisah).
- Edit quotation setelah dikirim (Sales tetap pemilik quotation).

## 3. Temuan kondisi sekarang

1. Inside Sales hanya punya daftar `lead-tasks`, belum ada dashboard.
2. Item Inside Sales (`lead_task_items`) sudah ada, tetapi belum punya produk,
   harga modal, scope of supply, lead time, atau margin.
3. `ProductPriceLog` sudah menyimpan harga estimator terbaru per produk/supplier.
4. Quotation baru dibuat saat Sales menekan Accept file; belum digenerate Inside Sales.
5. PDF quotation belum ada; halaman preview masih HTML dengan kop generik.

## 4. Langkah pengerjaan

### 4.1 Dashboard

1. Route `/inside-sales` dengan permission `manage-inside-sales`.
2. KPI: request baru, dikerjakan, menunggu harga, quotation selesai.
3. Daftar request: customer, Sales peminta, due date, status, aksi buka task.
4. Sidebar: menu Inside Sales muncul untuk semua pemegang `manage-inside-sales`
   (termasuk multi-role sales + inside sales).

### 4.2 Item & harga

5. Form item menampilkan pilihan produk aktif.
6. Saat produk dipilih:
   - isi `description`, `unit`, `scope_of_supply` dari produk,
   - isi `unit_cost` dari `ProductPriceLog` terbaru,
   - isi `unit_price` dari `default_price` produk (bisa diubah),
   - tampilkan supplier/tanggal harga terbaru dan margin.
7. Simpan `scope_of_supply`, `lead_time`, `unit_cost`, dan `section` di item task.

### 4.3 Generate quotation

8. Form header quotation: no quotation manual (unik), date, organization,
   address, contact, email, phone, project description, payment, kop PT.
9. Generate membuat `Proposal` draft + item + PDF, menyimpan `pdf_path`,
   menandai task selesai, dan mengirim notifikasi ke Sales.
10. Satu task hanya boleh punya satu quotation.

### 4.4 PDF

11. Template A4 mengikuti contoh WANI: kop PT, partner data, project description,
    payment, date, tabel item, total, VAT, terms, rekening, tanda tangan.
12. WANI memakai data lengkap; NTI/MGK/TPS memakai logo + data yang tersedia.

## 5. Kriteria selesai

- Inside Sales login langsung melihat dashboard dengan antrean nyata.
- Pilih produk -> harga modal estimator terbaru terisi otomatis.
- Margin terlihat di layar.
- Generate Quotation menghasilkan Quotation draft + PDF berkop PT.
- Sales menerima notifikasi dan bisa mengunduh PDF.
- `php artisan test --filter="InsideSales|LeadTaskQuotation|QuotationPdf"` lulus.
- Light/dark mode, mobile, empty state rapi.

## 6. Dependensi

- `PLAN-SALES-QUOTATION-PDF.md` untuk template PDF dan kop.
- `PLAN-SALES-ESTIMATOR-PRICE-REQUEST.md` untuk sumber harga estimator.
- `PLAN-SALES-QUOTATION-OTOMATIS.md` untuk pembuatan `Proposal` + item.
