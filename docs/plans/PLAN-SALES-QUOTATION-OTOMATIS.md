# PLAN-SALES-QUOTATION-OTOMATIS: Quotation Lahir dari Inside Sales, Bukan Input Ulang

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Sales / Quotation + Inside Sales
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
> Catatan: butuh keputusan di bagian 7.
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/sales-flow` 146 commit di belakang `main`.
> Sebelum mulai: `git merge main`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Inside Sales membuat quotation **dari dalam menu Inside Sales** (item + harga + qty), bukan hanya unggah file.
- Quotation di menu Sales otomatis terisi item, harga, dan total yang sama. Sales tidak mengetik ulang.
- Setelah Sales mengirim quotation dan customer menerima, data langsung tersedia untuk Admin
  tanpa Admin membuka dan menyalin isi quotation.

## 2. Ruang lingkup

Termasuk:

- Form item terstruktur di Inside Sales.
- Pembuatan `proposals` + `proposal_items` otomatis saat Inside Sales menyetujui hasil.
- Sinkronisasi harga dari Price Request (opsional, lihat keputusan).
- Alur serah terima ke Admin.

Tidak termasuk:

- Perubahan desain halaman Quotation Sales (kecuali penyesuaian data).
- Perubahan alur PO/verify/convert (dibahas di `PLAN-SALES-PO-DAN-CONVERT.md`).
- Dashboard khusus Inside Sales/Estimator (`PLAN-SALES-INSIDE-ESTIMATOR-DASHBOARD.md`).

## 3. Temuan kondisi sekarang

1. Inside Sales mengerjakan `lead_tasks`, lalu mengunggah lampiran
   (`LeadTaskController@storeAttachment`, baris 234-270).
2. Saat lampiran di-**Accept** (`LeadTaskController@acceptAttachment`, 278-346):
   - dibuat `Proposal` status `draft` **tanpa item**,
   - hanya `ProposalAttachment` yang disimpan,
   - `created_by` = user Inside Sales yang menekan Accept.
3. Sales juga bisa membuat quotation manual di `/sales/proposals`
   (`ProposalController` 386-525) dengan input item manual.
4. Guard `assertQuotationHasBillableContent` (`ProposalController:47-56`) mewajibkan minimal 1 item
   dan `grand_total > 0`. Akibatnya quotation hasil Inside Sales **tidak bisa lanjut**
   (tidak bisa `ready`/`sent`/`accepted`) sampai Sales mengisi item manual.
5. Price Request (`price_requests` + `price_request_replies`) **tidak** menyalin item/harga ke quotation.
   Hanya ada FK `proposals.price_request_id` yang diisi manual.
6. Audit resmi juga menyebut item quotation 100% manual:
   `docs/audit/AUDIT-QUOTATION.md` bagian 219-225 dan 266-268.

## 4. Langkah pengerjaan

### 4.1 Item terstruktur di Inside Sales

1. Di halaman task Inside Sales (`lead-tasks/show.blade.php`), tambahkan form item quotation
   memakai komponen yang sudah ada di quotation Sales (`_form.blade.php`, baris 79-119):
   `product_id` (opsional), `description`, `quantity`, `unit`, `unit_price`, `discount`.
2. Simpan item ke tabel baru `lead_task_items` (migrasi baru) - jangan menumpuk di `lead_task_attachments`.
3. Hitung otomatis `subtotal`, `grand_total`, dan margin (pakai logika `ProposalItem` yang sudah ada).

### 4.2 Quotation otomatis

4. Saat Accept (`LeadTaskController@acceptAttachment`), buat `Proposal` **beserta** `ProposalItem`
   dari `lead_task_items`. Lampiran tetap disimpan sebagai bukti.
5. Tambah kolom `proposals.lead_task_id` (migrasi baru) agar quotation tahu asal task.
   Aturan: satu task hanya boleh punya satu quotation aktif.
6. Perbaiki kepemilikan: `proposals.created_by` diisi **Sales pemilik lead** (`lead.assigned_to`),
   sedangkan Inside Sales tetap tercatat di `lead_tasks`/`lead_task_attachments.accepted_by`.
   Tujuannya agar Sales bisa mengedit dan mengirim quotation.
   Alternatif: biarkan `created_by` Inside Sales tetapi pastikan `isOwner()` sudah mencakup
   `lead.assigned_to` (saat ini sudah, di `ProposalController:77-80`).
7. Setelah item terisi, status quotation langsung `draft` dengan total benar.
   Sales tinggal cek, revisi bila perlu, lalu kirim.

### 4.3 Dari Price Request (opsional)

8. Saat Sales membuat quotation dari Price Request, ambil harga dari balasan estimator terakhir
   sebagai nilai awal `unit_price`. Jangan otomatis membuat item dari file.
9. Kalau disetujui: tambah `proposal_items.price_request_reply_id` untuk jejak audit harga.

### 4.4 Serah terima ke Admin

10. Setelah quotation `accepted` dan Sales mengunggah PO, alur handoff Admin yang sudah ada dipakai
    (tidak membuat alur baru).
11. Admin melihat poin-poin quotation (item, qty, harga, total, margin) langsung di halaman handoff,
    tanpa perlu membuka file quotation.
12. Pastikan `proposal_number` tetap dibuat di satu tempat (`LeadTaskController` 349-363 dan
    `ProposalController`) agar tidak dobel format.

## 5. Kriteria selesai

- Inside Sales isi 3 item + harga -> Accept -> menu Quotation Sales menampilkan 3 item yang sama
  dengan total dan margin benar.
- Sales bisa langsung kirim quotation tanpa mengetik ulang item.
- Customer PO diunggah -> Admin melihat rincian yang sama di antrean handoff.
- Tidak ada quotation `draft` dengan 0 item dari alur Inside Sales.
- `php artisan test --filter="InsideSalesTask|ProposalModule|ProposalInsideSales|PriceRequestQuotation"` lulus.

## 6. Dependensi

- `PLAN-SALES-PO-DAN-CONVERT.md` (snapshot PO ke project) memakai quotation yang sudah berisi item.
- Kalau `feature/sales-price-sync` di-merge lebih dulu, selesaikan konflik di `PriceRequest*`
  sebelum mengerjakan poin 4.3.
- Dashboard Inside Sales/Estimator dibangun terpisah (`PLAN-SALES-INSIDE-ESTIMATOR-DASHBOARD.md`).

## 7. Butuh keputusan User sebelum dikerjakan

1. Siapa yang berhak mengisi item quotation pertama kali?
   - **Opsi A (rekomendasi):** Inside Sales mengisi item + harga, Sales hanya mereview/revisi.
   - **Opsi B:** Inside Sales hanya unggah file, Sales tetap input item.
2. Apakah harga dari Price Request otomatis dipakai sebagai harga awal quotation?
   - **Opsi A (rekomendasi):** ya, sebagai nilai awal yang masih bisa diubah Sales.
   - **Opsi B:** tidak otomatis, Sales memilih manual.
