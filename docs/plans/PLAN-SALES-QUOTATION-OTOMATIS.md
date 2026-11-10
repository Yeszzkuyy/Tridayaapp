# PLAN-SALES-QUOTATION-OTOMATIS: Quotation Lahir dari Inside Sales

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Quotation + Inside Sales
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-10

> Implementasi inti sudah berjalan di `feature/sales-flow`; menunggu merge ke `main`
> dan pengecekan user. Sinkronisasi harga dari Price Request ditunda ke plan terpisah.

---

## 1. Tujuan & hasil akhir

- Inside Sales mengisi item quotation (deskripsi, qty, harga, diskon) dari halaman task.
- Saat file quotation di-Accept, sistem membuat Quotation beserta itemnya secara otomatis.
- Sales tidak perlu mengetik ulang item; cukup review, revisi bila perlu, lalu kirim.
- File quotation tetap tersimpan sebagai lampiran bukti.

## 2. Ruang lingkup

Termasuk:

- Form item quotation di halaman task Inside Sales.
- Pembuatan `proposals` + `proposal_items` saat file di-Accept.
- Tautan `proposals.lead_task_id` (satu task = satu quotation).
- Total, diskon, dan margin dihitung memakai logika `Proposal` yang sudah ada.

Tidak termasuk:

- Sinkronisasi otomatis harga dari Price Request (ditunda).
- Perubahan desain halaman Quotation Sales.
- Alur PO/verify/convert (ada di `PLAN-SALES-PO-DAN-CONVERT.md`).

## 3. Temuan kondisi sekarang

1. Inside Sales mengerjakan `lead_tasks`, lalu mengunggah lampiran.
2. Saat lampiran di-Accept, sistem membuat `Proposal` draft **tanpa item**.
3. Sales tetap harus mengisi item manual agar quotation bisa dikirim.
4. Guard `assertQuotationHasBillableContent` menolak quotation tanpa item.
5. Belum ada tabel `lead_task_items` dan kolom `proposals.lead_task_id`.

## 4. Langkah pengerjaan

### 4.1 Item di Inside Sales

1. Tambah tabel `lead_task_items`: `description`, `quantity`, `unit`,
   `unit_price`, `discount`, `subtotal`, `product_id` opsional.
2. Halaman task menampilkan daftar item + form tambah item.
3. Hanya assignee, pembuat task, atau management yang boleh menambah/menghapus item.
4. Subtotal dihitung dengan rumus yang sama seperti `ProposalItem`.

### 4.2 Quotation otomatis

5. Tambah kolom `proposals.lead_task_id` (unique) dan relasi ke `LeadTask`.
6. Saat file di-Accept:
   - minimal harus ada 1 item,
   - satu task hanya boleh punya satu quotation,
   - buat `Proposal` + `ProposalItem` dari item task,
   - `created_by` diisi Sales pemilik lead,
   - lampiran file tetap dipasang,
   - hitung ulang total/margin.
7. Jika belum ada item, Accept ditolak dengan pesan jelas.

### 4.3 Price Request (ditunda)

8. Harga dari balasan estimator terakhir belum otomatis mengisi item.
   Akan dikerjakan sebagai paket terpisah setelah Price Request distabilkan.

## 5. Kriteria selesai

- Inside Sales menambah 2 item + harga -> Accept file -> Quotation berisi 2 item
  dengan total dan margin benar.
- Sales bisa langsung review/kirim quotation tanpa mengetik ulang.
- Accept tanpa item ditolak 422 dengan pesan jelas.
- Satu task tidak bisa membuat dua quotation.
- `php artisan test --filter="LeadTaskQuotationItems|InsideSalesTask|ProposalModule"` lulus.

## 6. Dependensi

- `PLAN-SALES-PO-DAN-CONVERT.md` memakai quotation yang sudah berisi item.
- `PLAN-SALES-INSIDE-DASHBOARD.md` menampilkan hasil quotation dari alur ini.

## 7. Keputusan yang sudah diambil

1. Inside Sales yang mengisi item pertama.
2. Sales tetap bisa merevisi item setelah quotation dibuat.
3. Sinkronisasi harga Price Request ditunda.
