# PLAN-SALES-QUOTATION-PDF: Template Quotation per PT

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Quotation
**Branch:** `feature/sales-inside`
**Worktree:** `/home/yeski/3dyapp-inside`
**Terakhir diperbarui:** 2026-11-10

---

## 1. Tujuan & hasil akhir

- Quotation bisa digenerate menjadi file PDF A4 yang bisa diunduh.
- Setiap PT punya kop sendiri: logo, alamat, telepon, sales, terms, rekening,
  dan penandatangan.
- Tampilan mengikuti contoh `ContohQuotationWani.pdf`.

## 2. Field quotation

- No. Quotation (manual, unik)
- Date
- Organization
- Address
- Contact
- E-mail
- Phone
- Project Description
- Payment
- Company / Kop PT

## 3. Tabel item

Kolom: No, Scope of Supply, Description, Estimated Lead Time, Qty, Unit,
Pricelist, Total Price. Total Unit, VAT, Total.

## 4. Kop per PT

| PT | Status data |
|---|---|
| WANI | Lengkap dari contoh PDF + logo |
| NTI | Logo + alamat/telepon dari header KOP; terms/rekening/penandatangan menunggu data |
| MGK | Logo + alamat/telepon dari header KOP; terms/rekening/penandatangan menunggu data |
| TPS | Logo + alamat/telepon dari header KOP; terms/rekening/penandatangan menunggu data |

Sumber logo: `storage/app/private/letterheads/<pt>/`. Saat implementasi,
logo disalin ke `public/images/letterheads/<pt>/` agar bisa dibaca Dompdf.

## 5. Langkah pengerjaan

1. Tambah kolom snapshot header di `proposals` dan `pdf_path`.
2. Tambah kolom `scope_of_supply`, `lead_time`, `section` di item.
3. Buat `config/companies.php` berisi data kop per PT.
4. Buat `QuotationPdfService` + view `sales/proposals/pdf.blade.php`.
5. Generate PDF A4, simpan di disk privat, tautkan ke proposal.
6. Tambah route download PDF untuk Sales dan Inside Sales.
7. Uji layout WANI, lalu placeholder NTI/MGK/TPS.

## 6. Kriteria selesai

- PDF WANI bisa diunduh dan tampilannya rapi.
- Nomor, tanggal, partner data, item, total, terms, dan tanda tangan benar.
- File tersimpan aman di disk privat.
- Hanya user berwenang yang bisa mengunduh.
