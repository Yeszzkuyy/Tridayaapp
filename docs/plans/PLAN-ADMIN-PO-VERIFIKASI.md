# PLAN-ADMIN-PO-VERIFIKASI: Verifikasi PO Customer oleh Admin

**Status Pengerjaan:** Belum Dikerjakan
**Menu:** Admin / Purchasing Handoff
**Branch:** `feature/div-panel`
**Worktree:** **belum ada** - perlu dibuat: `/home/raafi/3dyapp-panel` (branch `feature/div-panel`, 124 commit di belakang `main`, 1 commit di depan)
**Terakhir diperbarui:** 2026-11-09
> Catatan: butuh keputusan di bagian 7.
**Baseline kode:** `main` @ `d30b32d2`

---

## 1. Tujuan & hasil akhir

Menjawab pertanyaan: **kenapa PO customer harus diverifikasi Admin dulu sebelum bisa diubah menjadi project?**

Setelah selesai:

- Alasan verifikasi jelas dan tercatat.
- Admin bisa memverifikasi cepat (satu klik) dengan daftar kelengkapan otomatis.
- Sales melihat status PO (menunggu / terverifikasi / dikembalikan) beserta alasannya.
- Tidak ada input ulang nomor PO di invoice atau dokumen lain.

## 2. Ruang lingkup

Termasuk:

- Alur verifikasi / pengembalian PO.
- Kelengkapan data yang wajib sebelum verifikasi.
- Status PO yang terlihat oleh Sales dan Admin.

Tidak termasuk:

- Membuat project (ada di `PLAN-SALES-PO-DAN-CONVERT.md`).
- Membuat Supplier PO / invoice (tetap di modul Admin yang sudah ada).

## 3. Temuan kondisi sekarang

1. Sales mengunggah PO di `POST /sales/proposals/{proposal}/po`
   (`ProposalController@uploadPo`, 791-856): `po_file`, `po_number`, `po_date`, `delivery_deadline`,
   `delivery_address`, `payment_scheme`, `dp_percent`, `term_days`.
2. Admin memverifikasi di `PurchasingHandoffController@verify` (115-147) dan mengembalikan
   di `@return` (149-186).
3. Syarat antrean: `Proposal::isReadyForPurchasing()` = status `accepted` + file PO ada.
4. Syarat verifikasi: `poCompletenessErrors()` (`Proposal.php:223-247`) mengecek
   file PO, nomor PO, tanggal PO, deadline pengiriman, alamat pengiriman, dan cara pembayaran.
5. `convertToProject` **mewajibkan** `isPoVerified()` (baris 904).
6. Belum ada:
   - status PO yang menonjol di halaman Sales (hanya bisa dilihat dari tombol convert yang gagal),
   - validasi nomor PO ganda,
   - riwayat verifikasi yang tampil di kedua sisi.

## 4. Analisis: perlukah verifikasi?

Alasan verifikasi dipertahankan:

1. **Mencegah salah beli.** PO adalah dasar Purchasing memesan barang ke supplier.
   Kalau nomor/harga/alamat salah, Supplier PO dan invoice ikut salah.
2. **Menentukan nilai komersial.** Cara pembayaran (DP/termin) dan deadline memengaruhi
   jadwal produksi dan penagihan.
3. **Jejak audit.** Ada bukti kapan PO diterima dan siapa yang menyetujui.

Risiko kalau verifikasi dihapus:

- Project dan Supplier PO bisa lahir dari PO yang belum lengkap atau salah.
- Sales bisa mengubah PO setelah project berjalan tanpa kontrol.

**Rekomendasi:** verifikasi tetap ada, tetapi dibuat **lebih ringan**:
jika semua field wajib sudah lengkap dan file PO terbaca, Admin cukup satu klik verifikasi
(tidak perlu membuka dokumen di tab lain). Jangan menghapus gate tanpa keputusan User.

## 5. Langkah pengerjaan

1. Halaman handoff Admin (`admin/purchasing/handoff/show.blade.php`):
   - tampilkan checklist kelengkapan (dari `poCompletenessErrors()`),
   - tombol **Verify PO** aktif hanya jika checklist kosong,
   - tombol **Return to Sales** wajib alasan,
   - tampilkan nomor PO, tanggal, deadline, alamat, cara bayar dalam satu blok ringkas.
2. Halaman quotation Sales (`sales/proposals/show.blade.php`):
   - badge status PO: `Waiting for verification` / `Verified` / `Returned`,
   - alasan pengembalian ditampilkan jelas,
   - tombol convert baru aktif setelah `po_verified_at` terisi (selaraskan dengan backend).
3. Notifikasi:
   - Sales menerima notifikasi saat PO diverifikasi dan saat dikembalikan (beserta alasan).
   - Admin menerima notifikasi saat Sales mengunggah PO (sudah ada, pastikan tetap jalan
     setelah antrean `jobs` diperbaiki).
4. Nomor PO:
   - satu sumber dari `proposals.po_number` (lihat `PLAN-SALES-PO-DAN-CONVERT.md`),
   - invoice mengambil dari sana, tidak bisa diketik ulang,
   - validasi unik per customer.
5. Audit log: tampilkan riwayat `po_verified` / `po_returned` (sudah dicatat) di kedua halaman.
6. Permission: `manage-admin` (Admin/Purchasing) boleh verify/return; `view-admin-hub` hanya baca.

## 6. Kriteria selesai

- Sales unggah PO -> Admin melihat checklist lengkap -> satu klik Verify.
- Sales langsung melihat badge `Verified` tanpa reload manual berlebihan.
- Kalau ada field kurang, tombol Verify tidak aktif dan Admin tahu field mana yang kurang.
- Return to Sales wajib alasan; Sales melihat alasannya.
- Nomor PO di invoice sama persis dengan nomor PO di quotation.
- `php artisan test --filter="PurchasingHandoff|AdminHandoffPoVerification"` lulus.

## 7. Butuh keputusan User

1. Apakah verifikasi PO tetap wajib sebelum Convert to Project?
   - **Opsi A (rekomendasi):** tetap wajib, tapi Admin cukup satu klik.
   - **Opsi B:** boleh convert dulu, verifikasi menyusul (risiko Supplier PO dibuat dari data belum valid).
2. Apakah Admin boleh mengubah nomor PO hasil ketikan Sales?
   - **Opsi A (rekomendasi):** tidak, harus dikembalikan ke Sales agar jejak audit jelas.
   - **Opsi B:** Admin boleh koreksi langsung.
