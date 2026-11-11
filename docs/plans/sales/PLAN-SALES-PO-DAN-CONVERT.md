# PLAN-SALES-PO-DAN-CONVERT: PO Number Selaras, Convert Aman, PIC Engineer Jelas

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sales / Quotation - PO - Convert to Project
**Branch:** `feature/sales-flow`
**Worktree:** `/var/www/3dyapp-sales`
**Terakhir diperbarui:** 2026-11-09
> Catatan: bagian 4.1-4.3), bagian 4.4 butuh keputusan.
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/sales-flow` 146 commit di belakang `main` dan masih memakai versi
> `convertToProject` **tanpa** gate verifikasi PO. Wajib `git merge main` sebelum mulai,
> kalau tidak perilakunya berbeda dari website live.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Nomor PO customer yang diinput Sales adalah **satu sumber data** yang dibaca Admin, invoice, dan project.
- Sales bisa Convert to Project setelah PO diverifikasi Admin **tanpa error 500**.
- Kalau convert gagal di tengah jalan, user mendapat pesan jelas (bukan halaman error).
- Saat convert, Sales bisa langsung menunjuk PIC Engineer; teknisi langsung tahu dan project
  muncul di daftar kerjanya. Kalau belum ditunjuk, project masuk antrean "belum ada engineer".

## 2. Ruang lingkup

Termasuk:

- Perbaikan error 500 Convert to Project.
- Validasi & snapshot PO.
- Penunjukan engineer saat/sesudah convert.
- Visibilitas project di sisi teknisi.

Tidak termasuk:

- Perubahan aturan verifikasi PO oleh Admin (dibahas di `../admin/PLAN-ADMIN-PO-VERIFIKASI.md`).
- Pembuatan Supplier PO / invoice (modul Admin, tidak diubah di sini).

## 3. Temuan kondisi sekarang

### 3.1 Penyebab 500 saat Sales convert (sudah terkonfirmasi)

`ProposalController@convertToProject` (baris 885-985) setelah project dibuat memanggil:

- `Notification::send(User::role('lead-technician')..., ProjectConvertedNotification)` (sync), lalu
- `notifyAdminHandoff()` (baris 856-870) yang mengirim `PurchasingHandoffNotification`
  dan **mengimplementasikan `ShouldQueue`**.

Penerima handoff adalah `User::permission('view-admin-hub')->where('id','!=',auth()->id())`.

- Kalau **Admin** yang convert: dirinya dikecualikan -> daftar penerima kosong -> tidak ada dispatch -> sukses.
- Kalau **Sales** yang convert: Admin menjadi penerima -> antrean (`jobs`) dipakai.

Log produksi 2026-11-09 mencatat **21.232 error** `SQLSTATE[42501] permission denied for table jobs`.
Jadi dispatch gagal tepat setelah project ter-commit -> 500 ke Sales.

Efek samping: karena project sudah terbuat, percobaan convert berikutnya ditolak
"already converted", sehingga user mengira datanya rusak.

### 3.2 Bug lain yang ikut memperbesar risiko

1. `abort_if($proposal->project()->exists(), 422)` (baris 896) tidak melihat project yang sudah soft-delete.
   Unique index `projects.proposal_id` tetap berlaku -> `QueryException` 500.
2. `isOwner()` (baris 77-80) langsung membaca `$proposal->lead->assigned_to`.
   Kalau lead sudah dihapus (soft delete), muncul "Attempt to read property on null" = 500.
3. `requires_delivery` dikirim saat `Project::create` (baris 934) tetapi **tidak ada di `Project::$fillable`**
   (`app/Models/Project.php:14-43`) -> nilainya dibuang diam-diam.
4. Modal convert di `sales/proposals/show.blade.php` (baris 375-383) tidak memeriksa `po_verified_at`,
   jadi tombol tetap aktif walau backend akan menolak.

### 3.3 PO number

- Nomor PO customer hanya kolom bebas di `proposals.po_number` (diisi Sales).
- Admin memverifikasi di `PurchasingHandoffController@verify` (115-147).
- Invoice menyalin nomor PO dari proposal (`AdminController:73`) tetapi masih bisa diketik ulang.
- `projects` **tidak punya kolom PO** -> teknisi/Purchasing harus membuka quotation lagi.
- Belum ada validasi nomor PO ganda per customer.

### 3.4 Engineer

- Convert **tidak mengisi** `teknisi_user_id`, `pic_engineer`, maupun pivot `project_supports`.
- Daftar project teknisi memakai filter `teknisi_user_id`
  (`ProjectController:44-48`), sehingga project baru **tidak muncul** untuk teknisi.
- `pic_engineer` (string) dan `support_technicians` (string) adalah sumber ganda di samping pivot
  `project_supports`.
- `technical_requests` tidak punya `project_id`, jadi request engineer tidak terhubung ke project.

## 4. Langkah pengerjaan

### 4.1 Berhenti error 500 (prioritas)

1. **Infrastruktur:** beri hak akses tabel `jobs`, `failed_jobs`, `job_batches` kepada user database aplikasi,
   lalu `php artisan queue:restart`. Ini akar masalahnya, bukan hanya gejala.
2. **Kode:** bungkus pengiriman notifikasi setelah project dibuat dengan `try/catch` + `report()`.
   Convert tetap dianggap sukses walau notifikasi gagal.
3. Ganti guard convert menjadi `$proposal->project()->withTrashed()->exists()`.
4. `isOwner()` jadi null-safe: `$proposal->lead?->assigned_to`.
   Di `convertToProject`, kalau `$proposal->lead` null, hentikan dengan 422 berpesan jelas.
5. Tambah `requires_delivery` ke `Project::$fillable`.
6. Modal convert: tambahkan status PO terverifikasi di daftar syarat.
7. Tangkap `QueryException` unique `projects.proposal_id` dan ubah menjadi 422 yang ramah.
8. Pastikan `sales_user_id` memakai Sales pemilik lead yang benar
   (bukan user Inside Sales yang membuat quotation).

### 4.2 Satu sumber PO

9. Normalisasi input `po_number` (trim + uppercase) dan validasi unik per `customer_id`
   (boleh per customer + tahun, sesuai keputusan).
10. Snapshot ke project: migrasi baru menambah `projects.po_number`, `projects.po_date`,
    `projects.po_verified_at`, `projects.po_verified_by`. Isi saat convert.
11. Invoice: ambil nomor PO dari proposal (read-only), jangan input bebas.
12. Halaman handoff Admin dan detail quotation Sales menampilkan nomor PO, status verifikasi,
    dan alasan pengembalian yang sama.

### 4.3 PIC Engineer saat convert

13. Modal convert: tambah pilihan **PIC Engineer** (user ber-role `technician`/`lead-technician`)
    dan **Support Technician** (opsional, multi-pilih).
14. Saat convert:
    - set `projects.teknisi_user_id` = PIC terpilih,
    - set `projects.pic_engineer` = nama PIC (sinkron untuk tampilan lama),
    - buat baris `project_supports` untuk support technician,
    - kirim `ProjectConvertedNotification` + notifikasi penugasan ke PIC.
15. Kalau PIC tidak dipilih: project tetap dibuat dengan `teknisi_user_id = null` dan masuk
    daftar **"Unassigned Projects"** yang bisa dilihat `lead-technician`.
16. Di halaman project, tambah aksi "Assign Engineer" untuk `lead-technician`/`manage-technician`
    (set `teknisi_user_id` + pivot + notifikasi). Tidak perlu request teknis dulu.
17. Perbaiki daftar project teknisi: tampilkan project di mana
    `teknisi_user_id = user` **atau** user ada di pivot `project_supports`.
18. Hapus sumber ganda: `support_technicians` (string) dihitung dari pivot saat ditampilkan,
    bukan disimpan terpisah.

### 4.4 Opsional (butuh keputusan)

19. Tambah `technical_requests.project_id` agar request engineer terhubung ke project.
20. Simpan riwayat penugasan engineer (siapa menunjuk, kapan) untuk audit.

## 5. Kriteria selesai

- Admin verifikasi PO -> Sales klik Convert -> project dibuat, **tanpa 500**, redirect ke detail project.
- Klik Convert dua kali -> pesan 422 "sudah dikonversi", bukan error.
- Project menampilkan nomor PO yang sama dengan quotation dan invoice.
- PIC Engineer yang dipilih menerima notifikasi dan project muncul di daftar kerjanya.
- Convert tanpa PIC -> project masuk daftar "Unassigned" dan `lead-technician` bisa assign.
- `php artisan test --filter="AdminHandoffPoVerification|ProjectWorkflow|ProposalConvertUnique|ProcurementProjectLink"` lulus.
- Test baru: "convert sukses walau notifikasi gagal" dan "convert mengisi teknisi_user_id + pivot".

## 6. Dependensi

- `PLAN-SALES-QUOTATION-OTOMATIS.md`: quotation harus punya item sebelum convert.
- `../admin/PLAN-ADMIN-PO-VERIFIKASI.md`: keputusan aturan verify PO dan tampilan Admin.
- Sisi teknisi (daftar project + assign engineer) menyentuh `ProjectController` dan view teknisi;
  kalau ingin dipisah, bagian 4.3 poin 17 boleh dikerjakan di branch `feature/div-teknisi`.

## 7. Butuh keputusan User

1. Format nomor PO: bebas (sesuai dokumen customer) atau wajib format tertentu?
   - **Opsi A (rekomendasi):** bebas, hanya divalidasi unik per customer.
   - **Opsi B:** format tetap, mis. `PO/CUSTOMER/TAHUN/NOMOR`.
2. Kalau Sales tidak memilih engineer saat convert:
   - **Opsi A (rekomendasi):** project masuk antrean Unassigned, `lead-technician` yang menunjuk.
   - **Opsi B:** convert diblokir sampai engineer dipilih.
