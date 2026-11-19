# PLAN-MARKETING-BUTTON-STANDARD: Standardisasi Tombol Menu Marketing

**Status Pengerjaan:** Selesai
**Work:** MKT-BUTTON-STANDARD
**PIC:** Yeski
**Menu:** Marketing / Lead & Partner
**Branch:** feature/div-marketing
**Terakhir diperbarui:** 2026-11-19

## Tujuan & Hasil Akhir

Tombol di menu Marketing memakai komponen standar website: Add Lead memakai
flow button, navigasi kembali memakai back button, simpan/batal memakai
primary/secondary button, dan aksi baris memakai icon button. Pipeline tetap
milik Sales dan tidak diubah.

## Keputusan User

1. Add Lead memakai flow button.
2. Add Partner dibiarkan seperti sekarang.
3. Pipeline bukan bagian menu Marketing; halaman dan tombolnya tidak diubah.
4. Tab rentang tanggal dan legenda donut di Dashboard Marketing dibiarkan
   karena merupakan kontrol grafik, bukan tombol aksi.
5. Convert to Project tidak terlihat untuk role Marketing sehingga tidak
   termasuk lingkup pekerjaan ini.

## Ruang Lingkup

- Lead List: Add Lead flow button dan empty state standar.
- Form Lead (Add/Edit/Import): back button, primary/secondary button, dan
  hapus lampiran memakai icon button + konfirmasi standar.
- Lead Detail: satu back button standar untuk Marketing.
- Activity Log, Monitoring, Partner Add/Edit: back button standar.
- Partner List: Add Partner tetap memakai tombol yang sudah ada.
- Save/Cancel pada modal log follow up memakai komponen standar.

Tidak termasuk: Pipeline Sales, Convert to Project, tab/legenda Dashboard
Marketing, perubahan alur data, dan migrasi database.

## Verifikasi

- [x] Add Lead tampil sebagai flow button.
- [x] Add Partner tidak berubah.
- [x] Semua back button di halaman Marketing memakai back button standar.
- [x] Simpan/batal form dan modal memakai primary/secondary button.
- [x] Hapus lampiran memakai icon button dan kartu konfirmasi.
- [x] Tidak ada tombol konfirmasi bawaan browser di halaman Marketing.
- [x] Test terkait lulus dan halaman dicek di browser desktop/mobile.
