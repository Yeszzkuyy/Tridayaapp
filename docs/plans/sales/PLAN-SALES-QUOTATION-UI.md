# PLAN-SALES-QUOTATION-UI: Revamp Daftar Quotation

**Status Pengerjaan:** Siap Dikerjakan
**Work:** QUOTATION-UI
**PIC:** Yeski
**Menu:** Sales / Quotation
**Branch:** feature/sales-quotation-ui
**Terakhir diperbarui:** 2026-11-24

## Tujuan & Hasil Akhir

Halaman daftar Quotation tampil sebagai tabel berkolom yang mudah dipindai:
nomor quotation menonjol, customer hanya tampil sekali, nominal rata kanan,
badge status seragam, dan tanggal berlaku menandai quotation yang segera
berakhir atau sudah kedaluwarsa. KPI, pencarian, filter, sorting, dan pagination
bisa dipakai tanpa memuat ulang halaman lagi.

## Ruang Lingkup

- Header: tombol kembali di kiri judul, CTA New Quotation memakai aksi yang
  sudah ada.
- Enam kartu KPI bisa diklik untuk memfilter status/expiring/won.
- Toolbar pencarian, dropdown status, refresh, chip filter aktif, dan ringkasan
  hasil.
- Tabel desktop: nomor, customer, amount, status, valid until, sales PIC,
  created, aksi view.
- Sorting server-side (asc, desc, default) pada kolom yang ditandai.
- Pagination 10/25/50 baris dengan ringkasan hasil.
- Kartu ringkas untuk layar di bawah 768px.
- State kosong (belum ada data vs filter tidak cocok) dan penyesuaian ruang
  tombol AI.

Tidak termasuk: sidebar, topbar, halaman detail Quotation, halaman Quotation
Items, logika bisnis status/KPI, dan penambahan fitur baru.

## Cara Verifikasi

- [x] KPI Draft/Expiring/Won memfilter daftar dan jumlahnya sama.
- [x] Pencarian, chip filter, Clear all, dan dropdown status bekerja.
- [x] Sorting amount memakai angka, tiga klik kembali ke urutan default.
- [x] Pagination dan rows per page bekerja.
- [x] Baris bisa diklik dan dibuka lewat keyboard; ikon aksi tidak memicu baris.
- [x] Expired/expiring tampil di kolom Valid Until; status final tidak diberi
  peringatan.
- [x] Amount 0 tampil sebagai strip dengan tooltip.
- [x] Tidak ada scroll horizontal halaman pada 390px, 1024px, dan 1440px.
- [x] Test fitur index quotation lulus.

## Catatan Agent

- **Kolom tersedia:** semua kolom terisi dari data existing (`proposal_number`,
  `customer`, `grand_total`, `status`, `valid_until`, `creator.name`,
  `created_at`). Tidak ada kolom kosong yang dikarang.
- **Mekanisme data:** daftar sudah server-side (search + status + pagination).
  Sorting, filter turunan, dan rows-per-page memakai endpoint index yang sama
  lewat query string; tidak ada endpoint/payload baru. Default baris per halaman
  mengikuti plan (10, sebelumnya 15).
- **KPI interaktif:** keenam kartu interaktif. Expiring Soon memakai definisi KPI
  existing (status ready/sent/viewed, `valid_until` hari ini sampai +7 hari).
  Won This Month memakai status accepted dengan `updated_at` bulan berjalan.
- **Aksi baris:** hanya View (sesuai tombol mata sebelumnya), jadi ditampilkan
  sebagai ikon mata tanpa border, bukan menu titik-tiga.
- **Ambang expiring:** 7 hari, diambil dari hitungan KPI dan logika lama.
- **Status fallback:** status di luar peta memakai gaya netral dengan teks asli.
- **Kontras badge:** teks memakai token `--success/--warning/--danger/--info`
  di atas tint 10% dari token yang sama; dicek di mode terang dan gelap.
- **Penyimpangan kecil yang disengaja:**
  - Placeholder nominal kosong memakai strip (`-`), bukan em dash, mengikuti
    larangan em dash di `AGENTS.md` §7.2.
  - Tombol Filters dihapus karena hanya menduplikasi submit + dropdown status.
  - Skeleton/error-load tidak dibuat karena halaman dirender server-side dan
    tidak memakai fetch data; navigasi memakai browser seperti sebelumnya.
  - Breakpoint KPI dan kolom PIC/Created memakai `lg` agar pada layar desktop
    (konten workspace ±1210px) tetap tampil satu baris/penuh.
