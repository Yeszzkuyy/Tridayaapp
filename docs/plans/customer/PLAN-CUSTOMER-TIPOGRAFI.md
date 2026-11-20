# PLAN-CUSTOMER-TIPOGRAFI: Tipografi & Warna Modul Customer

**Status Pengerjaan:** Selesai
**Work:** CUS-TIPOGRAFI
**PIC:** Yeski
**Menu:** Customer
**Branch:** feature/div-cus
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Seluruh halaman Customer memakai skala teks minimum 12px dan token warna teks
primer/sekunder/tersier, tanpa mengubah data atau alur kerja.

## Ruang Lingkup

- Daftar customer, form tambah, form edit, dan detail customer.
- Kartu proyek, kartu dokumen, tab dokumen, dan timeline.
- Test yang menolak teks di bawah 12px dan warna netral mentah.

Tidak termasuk: perubahan data, route, filter, dan logika bisnis.

## Verifikasi

- [x] Tidak ada teks di bawah 12px di modul Customer.
- [x] Warna teks netral memakai token.
- [x] Test Customer lulus.
- [x] Build dari main dan pengecekan browser.
- [x] User menyatakan hasilnya sesuai.
