# PLAN-SISTEM-NOTIF-POPUP: Pop Up Notifikasi Pojok Layar

**Status Pengerjaan:** Selesai
**Menu:** Sistem / Notification Center
**Branch:** feature/sys-notif-popup
**Terakhir diperbarui:** 2026-11-09

## Tujuan & Hasil Akhir

Setiap ada notifikasi baru (lead, jadwal, survey, task, dsb.), pengguna melihat
kartu pop up di pojok kanan bawah tanpa harus membuka lonceng. Kartu berisi
ikon jenis kabar, judul, keterangan singkat, dan tombol silang. Klik kartu
langsung membuka halaman terkait dan menandai notifikasi sudah dibaca.

Notifikasi lama saat simpan berhasil/gagal (hijau/merah) ikut memakai bentuk
kartu yang sama supaya seluruh pesan di aplikasi seragam.

## Ruang Lingkup

Dikerjakan:

- Kartu pop up baru dengan tampilan disalin dari template 21st.dev (varian
  default/success/error/warning) memakai token warna aplikasi.
- Pop up otomatis untuk notifikasi realtime dari Notification Center.
- Seluruh pemanggil `window.toast(...)` lama ikut diarahkan ke kartu baru.
- Aturan tampil: maksimal 3 kartu menumpuk, hilang sendiri 8 detik,
  berhenti selama kursor di kartu, tutup manual lewat tombol silang,
  tidak muncul bila panel lonceng sedang terbuka.

Tidak dikerjakan:

- Tidak memasang library baru (sonner, lucide-react, radix, cva, framer-motion);
  animasi memakai `motion` yang sudah ada dan ikon SVG inline.
- Tidak mengubah isi/format notifikasi dari server.
- Tidak menyentuh web push/service worker.

## Langkah Pengerjaan

1. Tambah `resources/js/toast-store.js` sebagai antrean toast bersama.
2. Tambah `resources/js/components/NotificationToast.jsx` (kartu) dan
   `ToastViewport.jsx` (tumpukan kartu, portal ke `<body>`).
3. Hubungkan notifikasi realtime di `NotificationCenter.jsx` ke antrean.
4. Alihkan `window.toast` di `app.js` ke antrean yang sama.
5. Build dari `main`, cek light/dark + mobile/desktop.

## Kriteria Selesai

- Notifikasi baru memunculkan kartu di pojok kanan bawah tanpa refresh.
- Klik kartu membuka halaman terkait dan badge lonceng berkurang.
- Simpan berhasil/gagal menampilkan kartu dengan warna yang sesuai.
- Maksimal 3 kartu bertumpuk; kartu tertua hilang lebih dulu.
- Tampilan aman di mode terang, mode gelap, layar kecil, dan
  `prefers-reduced-motion`.

## Dependensi

- Notification Center (`feature/sys-notif-center`, sudah di `main`).
- Kanal realtime Reverb (`window.Echo`) beserta fallback polling lonceng.
