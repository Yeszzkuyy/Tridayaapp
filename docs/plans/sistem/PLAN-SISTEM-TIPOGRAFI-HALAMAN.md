# PLAN-SISTEM-TIPOGRAFI-HALAMAN: Tipografi Halaman Documents, Trash, dan Auth

**Status Pengerjaan:** Siap Dikerjakan
**Work:** SYS-TIPOGRAFI-HALAMAN
**PIC:** Yeski
**Menu:** Sistem / Documents, Trash, Auth
**Branch:** feature/sys-dropdown-standard
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Halaman Documents, Trash, dan pending approval memakai skala teks minimum 12px
serta token warna teks primer/sekunder/tersier, tanpa mengubah data atau alur.

## Ruang Lingkup

- Documents index, tab kategori, filter, tabel, dan panel detail.
- Trash index.
- Halaman pending approval.
- Test yang menolak teks di bawah 12px dan warna netral mentah.

Tidak termasuk: perubahan data, route, filter, dan logika bisnis.

## Verifikasi

- [x] Tidak ada teks di bawah 12px pada file sistem tersebut.
- [x] Warna teks netral memakai token.
- [x] Test sistem lulus.
- [ ] Build dari main dan pengecekan browser.
- [ ] User menyatakan hasilnya sesuai.
