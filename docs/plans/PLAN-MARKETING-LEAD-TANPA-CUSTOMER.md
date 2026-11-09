# PLAN-MARKETING-LEAD-TANPA-CUSTOMER: Lead Baru Tidak Otomatis Menjadi Customer

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Marketing / Lead
**Branch:** `feature/div-marketing`
**Worktree:** `/var/www/3dyapp-mkt`
**Terakhir diperbarui:** 2026-11-09
> Catatan: butuh keputusan bisnis di bagian 7.
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/div-marketing` saat ini 129 commit di belakang `main` dan 8 commit di depan.
> Sebelum mulai: `git pull origin feature/div-marketing`, `git merge main`, lalu `php artisan migrate`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Add Lead Marketing hanya menyimpan **data calon customer** (nama perusahaan + PIC), bukan membuat Customer resmi.
- Lead tetap punya data PIC yang lengkap dan tidak hilang walaupun belum jadi Customer.
- Customer resmi baru terbentuk ketika ada aksi eksplisit **"Jadikan Customer"**
  (misalnya setelah PO diterima atau disetujui Sales).
- Menu Customer hanya berisi perusahaan yang benar-benar sudah bertransaksi / disetujui.

## 2. Ruang lingkup

Termasuk:

- Skema `leads` (data perusahaan + PIC di level lead).
- Form Add/Edit Lead.
- Import lead dan konversi dari WhatsApp Center.
- Aksi eksplisit Lead -> Customer.

Tidak termasuk:

- Perubahan tampilan daftar/detail Customer (sudah di `PLAN-CUSTOMER-PIC.md`).
- Quotation dan Convert to Project (disesuaikan di branch `feature/sales-flow`).

## 3. Temuan kondisi sekarang

1. `leads.customer_id` bersifat **NOT NULL** (`database/migrations/2026_07_28_035513_create_leads_table.php:13`).
2. `LeadController@store` (baris 461-501) **selalu** membuat `Customer` saat mode `new`,
   lalu menyimpan PIC ke `customer_contacts` sebagai primary.
3. Marketing hanya punya izin `manage-marketing`, sedangkan membuat Customer butuh `manage-sales`.
   Artinya saat ini marketing bisa membuat Customer secara diam-diam lewat alur lead.
4. Import lead (baris 1192-1224) juga membuat Customer massal.
5. WhatsApp Center (baris 282-289 dan 467) langsung membuat Customer.
6. Form Add Lead memakai field `customer_phone` / `customer_whatsapp` / `customer_email` yang berlabel
   "Office Phone / WhatsApp / Email" perusahaan, tetapi nilainya disalin menjadi kontak PIC.
   Jadi data PIC dan data perusahaan tercampur.

## 4. Langkah pengerjaan (bertahap)

### Tahap 1 - Skema lead mandiri

1. Buat migrasi baru (dilarang mengedit migrasi lama, AGENTS.md §15):
   - `leads.customer_id` -> nullable, `nullOnDelete`.
   - Tambah kolom snapshot: `company_name`, `contact_name`, `contact_position`,
     `contact_phone`, `contact_whatsapp`, `contact_email`, `address`.
2. Backfill dari Customer yang sudah terhubung (command sekali jalan).
3. `Lead` model: tambah `$fillable`, helper `displayCompany()`, `displayPicName()`,
   `displayPicWhatsapp()`, `displayPicEmail()` dengan urutan: customer -> snapshot.
4. Relasi `customer()`, `projects()` dibuat null-safe.

### Tahap 2 - Form & controller

5. `leads/create.blade.php`: ganti bagian "Customer Data" menjadi "Data Calon Customer":
   - Nama perusahaan (wajib), alamat (opsional).
   - PIC: nama (wajib), jabatan, WhatsApp, email, telepon.
   - Nomor kantor dipisah dari nomor PIC.
6. `leads/edit.blade.php`: samakan.
7. `LeadController@store`: hapus `Customer::create`; simpan ke kolom snapshot.
   Mode `existing` tetap boleh memilih customer yang sudah ada.
8. `LeadController@update`: sama, dan pindahkan `syncPrimaryFromLead` **setelah** `authorize('update', $customer)`
   agar tidak ada efek samping saat request ditolak.
9. `LeadController@import`: berhenti membuat Customer massal; tulis ke snapshot lead.
10. `WhatsAppCenterController` (convert / simpan kontak): buat **Lead** dulu, bukan Customer.

### Tahap 3 - Aksi "Jadikan Customer"

11. Tambah route + action `POST /leads/{lead}/promote` (nama bebas, izin `manage-sales`).
12. Action ini membuat `Customer` + PIC utama dari snapshot lead, mengisi `lead.customer_id`,
    lalu mencatat aktivitas `lead_promoted`.
13. Tampilkan tombol "Jadikan Customer" di detail Lead (hanya untuk yang berizin).
14. Setelah customer terbentuk, tampilkan badge "Customer" di daftar Lead.

### Tahap 4 - Penyesuaian tampilan lead

15. `leads/index.blade.php` dan `leads/show.blade.php`: pakai helper snapshot,
    jangan `$lead->customer?->...` langsung.
16. Tampilkan status jelas: "Lead" vs "Sudah menjadi Customer".

## 5. Kriteria selesai

- Add Lead baru -> **tidak ada** baris baru di tabel `customers`.
- Data PIC tetap tampil di detail Lead (nama, WhatsApp, email, jabatan).
- Klik "Jadikan Customer" -> Customer + PIC utama terbentuk, lead tertaut.
- Import lead -> tidak membuat Customer.
- `php artisan test --filter="LeadForm|LeadDetail|LeadLifecycle|WhatsAppCenter"` lulus.

## 6. Dampak ke divisi lain (wajib dikoordinasikan)

- **Sales**: quotation harus menangani lead yang belum punya `customer_id`.
  Solusi: quotation tetap boleh dibuat, tetapi Convert to Project mewajibkan lead sudah jadi Customer.
- **Customer**: daftar Customer tidak lagi kebanjiran lead mentah.
- **Management**: laporan jumlah lead vs customer jadi lebih akurat.

## 7. Butuh keputusan User sebelum dikerjakan

1. Kapan tepatnya Lead berubah menjadi Customer?
   - **Opsi A (rekomendasi):** otomatis saat quotation `accepted` atau saat PO diunggah Sales.
   - **Opsi B:** manual lewat tombol "Jadikan Customer" oleh Sales/Admin.
   - **Opsi C:** keduanya (otomatis saat PO, tombol manual tetap ada).
2. Apakah data PIC di Lead boleh diedit Sales setelah lead di-assign?
   - Opsi A: ya, Sales boleh memperbarui.
   - Opsi B: hanya Marketing pemilik lead.
