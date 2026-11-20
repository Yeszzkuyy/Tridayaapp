# PLAN-SISTEM-BROWSER-TITLE: Judul Tab Browser Tetap Default

**Status Pengerjaan:** Siap Dikerjakan
**Work:** SYS-BROWSER-TITLE
**PIC:** Yeski
**Menu:** Sistem / Workspace Tabs
**Branch:** feature/sys-dropdown-standard
**Terakhir diperbarui:** 2026-11-20

## Tujuan & Hasil Akhir

Judul tab browser selalu memakai nama aplikasi default, tidak berubah mengikuti
tab workspace yang sedang aktif.

## Ruang Lingkup

- Hapus pembaruan judul browser saat pindah tab, membuka tab, menutup tab,
  split view, dan drag tab.
- Judul halaman dari layout utama tetap menjadi sumber judul tab browser.
- Tidak mengubah label tab di dalam aplikasi, riwayat URL, atau perilaku
  workspace lainnya.

## Verifikasi

- [x] Tidak ada lagi pembaruan judul browser dari workspace tabs.
- [x] Judul tab browser tetap "Tridaya App" saat berpindah tab.
- [x] Build dari main dijalankan.
