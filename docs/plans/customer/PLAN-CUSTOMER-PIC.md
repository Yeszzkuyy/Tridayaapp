# PLAN-CUSTOMER-PIC: PIC dari Add Lead Harus Tampil di Data Customer

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Customer / Data PIC
**Branch:** `feature/div-cus`
**Worktree:** `/var/www/3dyapp-cus`
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/div-cus` saat ini **884 commit di belakang `main`**.
> Sebelum mulai wajib: `git pull origin feature/div-cus` lalu `git merge main` dan `php artisan migrate`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- PIC yang diisi di form Add Lead Marketing otomatis tampil di daftar Customer dan halaman detail Customer.
- PIC tampil lengkap: nama, nomor WhatsApp, email, dan jabatan (bila ada).
- PIC bisa dicari dari kolom pencarian Customer.
- Customer yang dibuat manual (bukan dari lead) juga punya kolom PIC di form.

## 2. Ruang lingkup

Termasuk:

- Aturan "PIC utama" di `CustomerContactService` dan helper `Customer::picName()`.
- Tampilan daftar + detail Customer.
- Form Create/Edit Customer.
- Pencarian dan filter PIC.

Tidak termasuk:

- Perubahan form Add Lead (dibahas di `../marketing/PLAN-MARKETING-LEAD-TANPA-CUSTOMER.md`).
- Customer Hub di dashboard Sales (dibahas di branch `feature/sales-flow`).
- Memisahkan Lead dari Customer (rencana terpisah).

## 3. Temuan kondisi sekarang

1. PIC disimpan di tabel **`customer_contacts`** (sumber kebenaran).
   Kolom `customers.contact_person` hanya **cache lama**.
2. Helper `Customer::picName()`/`picWhatsapp()`/`picPhone()`/`picEmail()` hanya membaca kontak dengan `is_primary = true`.
   Kalau PIC tersimpan sebagai kontak biasa (bukan primary), helper mengembalikan kosong ->
   daftar dan detail menampilkan **"No PIC yet"** padahal kontaknya ada.
3. `CustomerContactService::syncPicCache()` sengaja mengosongkan `contact_person` bila tidak ada primary.
4. Form Create/Edit Customer **tidak punya field PIC**; PIC hanya bisa ditambah dari tab Contacts setelah customer tersimpan.
5. Pencarian Customer hanya `name`, `company`, `email`. Filter `pic` sudah ada di view tetapi tidak diproses controller.
6. `CustomerController@show` memanggil `backfillLegacyPrimary()` saat halaman dibuka (menulis DB pada request GET).
7. Masih ada file sisa `resources/views/customers/.wip-show.tmp`.

## 4. Langkah pengerjaan

### 4.1 Satu aturan PIC

1. Di `CustomerContactService::create()`: kontak pertama untuk sebuah customer otomatis `is_primary = true`
   (tanpa perlu user mencentang).
2. Di `Customer`: `picName()`/`picWhatsapp()`/`picPhone()`/`picEmail()`/`picPosition()`
   fallback ke kontak pertama (`orderByDesc('is_primary')->orderBy('id')`) bila tidak ada primary.
3. `syncPicCache()` dipertahankan sebagai cache tampilan, tetapi tidak boleh mengosongkan nama PIC
   ketika masih ada kontak.

### 4.2 Sinkronisasi dari Lead

4. Pastikan PIC dari Add Lead selalu menjadi **primary** customer
   (fungsi `syncPrimaryFromLead()` sudah ada, tambahkan test eksplisit).
5. Kalau lead punya beberapa kontak, PIC lead tetap primary; kontak lama tetap tersimpan.

### 4.3 Tampilan

6. Daftar Customer (`customers/_list.blade.php`): pakai `picName()`/`picWhatsapp()`/`picEmail()` (sudah),
   pastikan tidak ada lagi pemakaian `contact_person` langsung.
7. Detail Customer (`customers/show.blade.php`): tampilkan nama + jabatan + WhatsApp + email PIC utama.
8. Form Create Customer: tambah blok PIC (Nama, Jabatan, WhatsApp, Email) yang menulis lewat `CustomerContactService`.
9. Form Edit Customer: tambah input PIC utama di form, bukan hanya di tab Contacts.

### 4.4 Pencarian & filter

10. `CustomerController@index`: tambah `orWhereHas('contacts', ...)` untuk `name`, `phone`, `whatsapp`, `email`.
11. Implementasikan filter `pic` (kontak ada / tidak ada) yang sudah dipanggil view.

### 4.5 Kebersihan

12. Hapus `backfillLegacyPrimary()` dari `CustomerController@show`; pindahkan ke command/observer
    (command `BackfillCustomerContacts` sudah ada).
13. Hapus file `resources/views/customers/.wip-show.tmp`.
14. Hapus kondisi mati `$customer->assigned_to` di `CustomerPolicy` (kolom tidak ada).

## 5. Kriteria selesai

- Add Lead dengan PIC "Budi - 0812xxx - budi@pt.com" -> Lead tersimpan ->
  daftar Customer menampilkan "Budi" + WhatsApp + email.
- Detail Customer menampilkan jabatan (bila diisi), bukan hanya nomor/email.
- Cari nama PIC di kolom pencarian Customer -> customer terkait muncul.
- Buat Customer manual -> field PIC ada di form dan tersimpan sebagai PIC utama.
- `php artisan test --filter="CustomerContact|CustomerList|CustomerSearch|LeadForm"` lulus.

## 6. Dependensi

- `../marketing/PLAN-MARKETING-LEAD-TANPA-CUSTOMER.md` (branch marketing) mengubah cara PIC lead disimpan.
  Urutan aman: kerjakan plan ini dulu (customer tetap menerima PIC), lalu plan marketing.
- Customer Hub di dashboard Sales memakai `contact_person` lama; perbaikannya ada di branch sales.
