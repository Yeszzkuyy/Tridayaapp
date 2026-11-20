# PLAN-MARKETING-TIPOGRAFI: Terapkan Tipografi & Warna di Lead dan Partner

**Status Pengerjaan:** Selesai
**Work:** MKT-TIPOGRAFI
**PIC:** Yeski
**Menu:** Marketing / Lead & Partner
**Branch:** feature/div-marketing
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Halaman Lead, Partner, Activity Log, Monitoring, dan Dashboard Marketing
memakai skala teks minimum 12px dan token warna teks primer/sekunder/tersier.
Tampilan dan alur kerja tidak berubah.

## Ruang Lingkup

- Lead list, tambah, edit, import, detail, activity log, monitoring.
- Partner list, tambah, edit, dan tabel partner.
- Dashboard Marketing.
- Partial timeline lead.
- Test yang menolak teks di bawah 12px dan warna netral mentah.

Tidak termasuk: Pipeline (milik Sales), status warna penuh, perubahan data,
route, filter, dan logika bisnis.

## Verifikasi

- [x] Tidak ada teks di bawah 12px pada file Marketing.
- [x] Warna teks netral memakai token.
- [x] Test Marketing lulus.
- [ ] Build dari main dan pengecekan browser.
- [x] User menyatakan hasilnya sesuai.
