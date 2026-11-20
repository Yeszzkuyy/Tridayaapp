# PLAN-ADMIN-TIPOGRAFI: Tipografi & Warna Modul Admin

**Status Pengerjaan:** Siap Dikerjakan
**Work:** ADMIN-TIPOGRAFI
**PIC:** Yeski
**Menu:** Admin / Panel
**Branch:** feature/div-panel
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Halaman Admin dan Admin Panel memakai skala teks minimum 12px serta token warna
teks primer/sekunder/tersier, tanpa mengubah data, status, atau alur kerja.

## Ruang Lingkup

- Invoice, payment, purchase order, supplier, goods receipt, goods delivery.
- BAST, purchasing handoff, procurement, dan laporan.
- Admin Panel: user, role, status proyek, kategori dokumen, work type, pricing,
  dan audit log.
- Test yang menolak teks di bawah 12px dan warna netral mentah.

Tidak termasuk: status warna penuh, perubahan data, route, dan logika bisnis.

## Verifikasi

- [x] Tidak ada teks di bawah 12px di modul Admin.
- [x] Warna teks netral memakai token.
- [x] Test Admin lulus.
- [x] Build dari main dan pengecekan browser.
- [ ] User menyatakan hasilnya sesuai.
