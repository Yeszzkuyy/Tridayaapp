# PLAN-SISTEM-TIPOGRAFI-PILOT: Pilot Tipografi & Warna

**Status Pengerjaan:** Selesai
**Work:** SYS-TIPOGRAFI-PILOT
**PIC:** Yeski
**Menu:** Sistem / Tipografi
**Branch:** feature/sys-dropdown-standard
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Menerapkan standar tipografi dan warna secara bertahap pada tiga halaman pilot:
Dashboard, My Leads, dan Detail Customer. Teks minimal 12px, warna teks
memakai token primer/sekunder/tersier, dan tidak ada overflow layout.

## Keputusan User

1. Mulai dari fondasi token dan tiga halaman pilot.
2. Ukuran teks dibuat konservatif sekitar 12px agar tidak terlalu besar.
3. Skala pilot: 12, 13, 14, 16, 18, 22, dan 27px untuk KPI.
4. Halaman lain menyusul setelah pilot dicek.

## Ruang Lingkup

- Token teks primer/sekunder/tersier, surface, border, accent, dan status.
- Skala font Tailwind yang konservatif.
- Komponen label form dan back button.
- Dashboard, My Leads, dan Detail Customer beserta partial yang dipakai.
- Test pilot untuk menolak teks di bawah 12px dan warna netral mentah.

Tidak termasuk: status warna seluruh aplikasi, halaman di luar tiga pilot,
perubahan data, route, dan logika bisnis.

## Verifikasi

- [x] Tiga halaman pilot tidak punya teks di bawah 12px.
- [x] Warna teks netral tiga halaman pilot memakai token.
- [x] Tidak ada overflow di desktop/mobile pada halaman pilot.
- [x] Build dari main dan test pilot lulus.
- [x] User menyatakan hasil pilot sesuai.
