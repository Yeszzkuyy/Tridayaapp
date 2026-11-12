# PLAN-SALES-WORKSPACE: My Leads, Follow Up & DAR

**Status Pengerjaan:** Siap Dikerjakan
**Work:** SALES-WORKSPACE
**PIC:** Raafi
**Menu:** Sales / My Leads, Follow Up, DAR
**Branch:** feature/sales-workspace
**Terakhir diperbarui:** 2026-11-12

## 1. Tujuan & hasil akhir

Sales bisa mengerjakan satu lead dari satu tempat: melihat data penting,
menghubungi PIC, menulis catatan, mengubah tahap, dan menentukan jadwal
berikutnya. Follow Up menjadi antrean pekerjaan (Due Today, Upcoming, Overdue,
Completed), dan DAR menjadi riwayat aktivitas yang tercatat otomatis.

## 2. Ruang lingkup

### Termasuk

- Tabel My Leads dengan kolom PIC, kontak, proyek, tahap, catatan terakhir,
  aktivitas terakhir, dan follow up berikutnya.
- Filter tahap, perusahaan, PIC, proyek, aktivitas terakhir, dan jadwal.
- Catatan inline (append-only), kontak WA/Email/Call dengan catatan
  `contact_initiated`, dan ubah tahap dari baris tabel.
- Antrean Follow Up dengan tab, status Pending/Process/Completed, dan form
  Complete yang menyimpan hasil + jadwal berikutnya dalam satu transaksi.
- Menu DAR dengan filter tanggal, Sales, customer, dan jenis aktivitas.
- Form Add Activity untuk meeting, kunjungan, demo, survey, dan telepon di luar
  aplikasi.

### Tidak termasuk

- Perubahan modul Estimator, Purchasing, Teknisi, dan Admin Invoice.
- Export PDF/Excel DAR (menyusul terpisah).
- Pembersihan data follow-up ganda lama (perlu persetujuan terpisah).
- Perubahan format nomor quotation atau alur PO.

## 3. Keputusan yang dipakai

1. Sumber aktivitas memakai `follow_ups` (interaksi) + `lead_activities`
   (kejadian sistem). Tidak ada tabel aktivitas baru.
2. Won hanya dari quotation Accepted. Perubahan ke Won di luar quotation
   ditolak di backend.
3. Satu follow-up aktif per kombinasi lead + owner. Data lama yang sudah
   ganda tidak dihapus; pencegahan berlaku untuk transaksi baru.

## 4. Langkah pengerjaan

1. Perluas follow-up dengan status, outcome, dan source; tambah index antrean.
2. Rapikan scoping Sales per PT dan pemilik record.
3. Bangun workspace My Leads (filter, catatan inline, kontak, tahap, jadwal).
4. Bangun antrean Follow Up (tab, status, Complete + next follow up).
5. Bangun DAR (gabungan aktivitas + Add Activity + filter + otorisasi).
6. Jalankan test, cek dark mode dan mobile, lalu minta verifikasi user.

## 5. Kriteria selesai & verifikasi

- Sales hanya melihat lead/aktivitas miliknya; Management/Admin melihat semua
  aktivitas di DAR.
- Catatan inline tidak menghapus riwayat.
- Klik WA/Email/Call tidak menandai pesan terkirim.
- Complete menyimpan outcome, menutup antrean lama, dan membuat maksimal satu
  antrean baru.
- Beberapa aktivitas untuk lead yang sama pada hari yang sama tetap tersimpan.
- Request berulang tidak menggandakan catatan/aktivitas.
- Dark mode, mobile, dan test terkait lulus.

## 6. Dependensi

- Role & permission Sales/Management/Admin yang sudah ada.
- Data customer, lead, follow-up, dan lead activity yang sudah ada.
- Tidak bergantung pada perubahan modul lain.
