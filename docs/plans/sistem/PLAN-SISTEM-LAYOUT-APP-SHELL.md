# PLAN-SISTEM-LAYOUT-APP-SHELL: Lock App Shell, Hilangkan Double Scrollbar, Bersihkan Scrollbar Sidebar

**Status Pengerjaan:** Selesai
**Work:** LAYOUT-APP-SHELL
**PIC:** Yeski
**Menu:** Lintas sistem / App Shell
**Branch:** main
**Terakhir diperbarui:** 2026-11-24

## Tujuan & Hasil Akhir

- Shell aplikasi pas dengan viewport: tidak ada scrollbar luar (vertikal
  maupun horizontal) di jendela browser.
- Halaman tidak bisa lagi digeser sedikit ke kanan atau ke bawah dari shell.
- Sidebar terlihat bersih tanpa garis scrollbar, tetapi daftar menu yang
  panjang tetap bisa digulir dengan mouse wheel atau trackpad.
- Scroll konten tetap normal di dalam area masing-masing (iframe workspace,
  termasuk tabel data di dalamnya).

## Ruang Lingkup

Yang dikerjakan:

- Mengunci `html`, `body`, `.app-wrapper`, dan `.main-content` ke tinggi
  viewport (`100dvh`, fallback `100vh`) dengan `overflow:hidden`.
- Mengganti tinggi minimum area workspace dari `calc(100vh - 5rem)` menjadi
  `flex:1` + `min-height:0`, termasuk pembungkus iframe dan iframe-nya.
- Memastikan header dan footer tidak ikut menyusut, sehingga sisa tinggi
  sepenuhnya menjadi area konten.
- Menyembunyikan scrollbar nav sidebar lintas browser (Firefox, Edge/IE lama,
  Chrome/Safari) tanpa mematikan fungsi scroll.
- Memperbaiki posisi rail sidebar yang sebelumnya memakai `left:100%` relatif
  body, sehingga menonjol 14px keluar viewport dan memicu scroll horizontal.
- Menjaga mode klasik (workspace dimatikan) tetap bisa menggulir lewat
  `#workspace-fallback`, bukan lewat shell luar.

Yang tidak dikerjakan:

- Mengubah layout di dalam halaman/iframe (tabel, kartu, form).
- Mengubah perilaku tab workspace, split view, atau sidebar collapse.
- Redesign header, footer, atau sidebar.

## Langkah Pengerjaan

1. Kunci shell luar ke viewport dan pindahkan scroll ke area konten.
2. Rapikan rantai flex (`min-width:0`, `min-height:0`) pada stage, pane, dan
   iframe workspace.
3. Betulkan posisi rail sidebar agar tetap di tepi sidebar dan tidak
   menambah lebar dokumen.
4. Terapkan `no-scrollbar` pada nav sidebar dan hapus reservasi
   `scrollbar-gutter` yang tidak lagi diperlukan.
5. Verifikasi di browser pada desktop, desktop pendek, dan mobile.

## Kriteria Selesai / Verifikasi

- `document.documentElement.scrollHeight == clientHeight` dan
  `scrollWidth == clientWidth` pada desktop 1440x900, desktop pendek
  1440x500, dan mobile 390x844.
- Area iframe mengisi sisa tinggi setelah header dan footer, dan iframe bisa
  digulir sendiri (My Leads: konten 1276px di viewport 797px).
- Nav sidebar tetap bisa digulir saat daftar menu melebihi tinggi layar,
  dengan lebar scrollbar 0px.
- Split view tetap menampilkan dua panel dengan tinggi penuh tanpa scroll
  luar.
- `php artisan test tests/Feature/AppShellLayoutTest.php`,
  `WorkspaceTabsTest`, dan `RoleMenuTest` lulus.

## Dependensi

- Layout dipakai semua divisi, jadi perubahan ini tidak bergantung pada menu
  tertentu.
- Build CSS wajib dijalankan dari `/var/www/3dyapp` setelah merge ke `main`
  karena `sidebar-nav.css` ikut berubah.
