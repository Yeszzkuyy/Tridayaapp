# PLAN-SISTEM-FEEDBACK-UI: Toast & Kartu Konfirmasi Standar

**Status Pengerjaan:** Siap Dikerjakan
**Work:** FEEDBACK-UI
**PIC:** Yeski
**Menu:** Lintas sistem (semua divisi)
**Branch:** feature/sys-ui-feedback
**Terakhir diperbarui:** 2026-11-12

## Tujuan & Hasil Akhir

- Setiap aksi user (simpan, ubah, hapus, kirim, unggah, setujui, tolak, batal)
  memunculkan pop up kecil di pojok kanan bawah sebagai konfirmasi.
- Setiap notifikasi baru dan pengingat (jadwal teknisi, follow-up lewat jatuh
  tempo, laporan meeting) muncul sebagai kartu yang sama, dengan ikon berwarna
  sesuai jenisnya.
- Semua aksi yang butuh persetujuan memakai kartu konfirmasi standar, bukan
  kotak abu-abu bawaan browser.
- Tampilan pop up dan kartu konfirmasi selaras dengan panel lonceng notifikasi.

## Ruang Lingkup

Termasuk:

- Antrean toast global + judul per jenis (Berhasil / Gagal / Peringatan /
  Informasi) dan perbaikan jenis `danger` yang salah warna.
- Middleware `FlashActionToast` agar aksi tanpa pesan tetap dapat konfirmasi.
- Pensiun kartu centang `<x-status-card>` (12 halaman, 9 controller).
- Komponen `<x-confirm-dialog>` + penggantian 55 `confirm()` bawaan browser
  di 43 halaman.
- Notifikasi & pengingat: tampil lebih lama (12 detik), warna ikon lengkap
  untuk semua jenis.
- Aturan standar di AGENTS.md §7.3 + pengujian penjaga standar.

Tidak termasuk:

- Perubahan isi/alur bisnis tiap menu.
- Notifikasi email/WhatsApp di luar yang sudah ada.
- Migrasi database (tidak ada perubahan skema).

## Langkah Pengerjaan

1. Rapikan antrean toast (`toast-store.js`, `NotificationToast.jsx`,
   `NotificationCenter.jsx`) dan judul per jenis.
2. Tambah middleware `FlashActionToast` + register di `bootstrap/app.php`.
3. Hapus `success_card` dan `<x-status-card>`; semua sukses lewat toast.
4. Buat `<x-confirm-dialog>` + `resources/js/confirm-dialog.js`, pasang di
   layout, lalu ganti seluruh `confirm()` di view.
5. Lengkapi rincian data pada aksi berisiko (Accept/Cancel Price, proposal,
   estimator).
6. Tambah terjemahan `lang/id.json`, aturan AGENTS.md, dan test penjaga.

## Kriteria Selesai / Verifikasi

- `php artisan test --filter=UiFeedbackStandardTest` lulus (tidak ada
  `confirm()` atau `x-status-card` di view).
- `php artisan test --filter=FlashActionToastTest` lulus (pesan otomatis per
  jenis aksi).
- `npm run test:js` lulus.
- Manual: simpan lead, hapus data, Accept/Cancel Price, dan tunggu notifikasi
  baru → semuanya memakai kartu yang sama; tidak ada alert browser.
- Light & dark mode, desktop & mobile.

## Dependensi

- Menyentuh file view milik Sales, Marketing, Admin, Teknisi, Estimator,
  Documents, dan Trash. Sebelum merge, pastikan tidak menimpa pekerjaan aktif
  di worktree divisi terkait.
- `npm run build` dijalankan dari `/var/www/3dyapp` setelah merge ke `main`.
