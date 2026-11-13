# PLAN-SISTEM-WORKSPACE-TABS: Multi-Tab Workspace Keep-Alive di Top Navbar

**Status Pengerjaan:** Selesai
**Work:** WORKSPACE-TABS
**PIC:** Yeski
**Menu:** Lintas Divisi (Top Navbar + semua menu sidebar)
**Branch:** `feature/div-panel`
**Worktree:** `/home/yeski/3dyapp-panel` (perlu entry baru di tabel AGENTS §1)
**Terakhir diperbarui:** 2026-11-13

---

## 1. Tujuan & Hasil Akhir

Mengubah pill menu di top navbar menjadi deretan tab seperti browser/IDE.
Setiap menu yang dibuka tetap hidup di latar belakang, sehingga saat berpindah
tab:

- teks yang sedang diketik di form tidak hilang,
- posisi scroll tidak kembali ke atas,
- filter/pencarian tabel tidak ter-reset,
- modal atau langkah form yang sedang terbuka tidak tertutup.

Tab `Dashboard` selalu ada di paling kiri dan tidak bisa ditutup. Tab lain
bisa ditutup, dan daftar tab dipulihkan saat halaman di-refresh.

---

## 2. Keputusan User (sudah disepakati)

**Tanggal keputusan:** 2026-11-13

1. **Letak tab:** di dalam navbar, menggantikan pill lokasi yang sekarang
   (`Admin`, `Sales`, dst.). Tab menjadi penunjuk lokasi, sehingga
   breadcrumb lama tidak ditampilkan lagi saat mode tab aktif (isinya sama
   dengan judul tab aktif dan akan tampak ganda).
2. **HP/tablet:** workspace **tetap aktif**. Karena ruang navbar sempit,
   di bawah `lg` tab ditampilkan sebagai baris kedua di dalam header
   (baris pertama tetap hamburger + lonceng + avatar). Tab bisa digeser
   horizontal.
3. **Cakupan:** semua menu, dinyalakan bertahap mulai dari akun Admin
   sebagai pilot.

## 3. Temuan Teknis (dasar keputusan arsitektur)

1. Aplikasi memakai **Livewire 4.3 + Alpine 3.4** dan **99 tautan
   `wire:navigate`**. Navigasi antar halaman saat ini adalah SPA Livewire.
2. Notification Center adalah island React yang juga membuka koneksi Reverb.
   Kalau setiap tab memuat header lengkap, akan ada **satu koneksi websocket
   per tab** dan notifikasi ganda.
3. `X-Frame-Options: SAMEORIGIN` sudah dipasang di
   `app/Http/Middleware/SetSecurityHeaders.php`, jadi iframe same-origin
   diizinkan tanpa mengubah kebijakan keamanan.
4. `resources/js/confirm-dialog.js` sudah menyediakan `window.askConfirm()`
   (Promise) dan komponen `<x-confirm-dialog>`. Penutupan tab yang ada
   ketikan belum disimpan **wajib** lewat jalur ini (AGENTS §7.3).
5. `resources/js/toast-store.js` sudah menyediakan `window.toast()`; pesan
   "tab penuh" dan "tab ditutup" memakai jalur ini, bukan alert.
6. Sidebar menyimpan dua kelas siap pakai di `<aside data-nav-active=...
   data-nav-inactive=...>`, sehingga status aktif bisa dipindah lewat JS
   tanpa render ulang.

---

## 4. Arsitektur yang Direkomendasikan

### 4.1 Ringkasan

- **Shell (halaman luar):** sidebar, header, tab bar, Notification Center,
  toast, dan confirm dialog. Shell tidak pernah berpindah halaman.
- **Isi tab:** satu `<iframe>` same-origin per tab, memuat halaman dengan
  **layout embed** (tanpa sidebar/header/Notification Center).
- **Komunikasi:** `postMessage` dua arah untuk judul tab, URL, status
  "belum disimpan", dan pesan toast.

### 4.2 Kenapa iframe, bukan suntik HTML (`fetch` + `x-show`)

Pendekatan `fetch` lalu menempelkan HTML ke `<div>` tersembunyi **ditolak**
untuk aplikasi ini karena:

- Livewire 4 mengelola komponen berdasarkan `wire:id`. Menempelkan HTML
  halaman kedua di DOM yang sama membuat ID komponen ganda, listener ganda,
  dan aksi pada satu tab bisa memicu komponen tab lain.
- ID elemen global (`id="modal-..."`, `id="filter-..."`) akan bentrok.
- Alpine root dan plugin Livewire tidak mendukung inisialisasi banyak
  halaman sekaligus secara resmi.

Iframe memberi isolasi penuh: form, modal, Livewire, dan skrip tiap modul
berjalan di dokumennya sendiri tanpa saling ganggu. Ini satu-satunya cara
yang memenuhi kriteria "ketikan tidak hilang" tanpa menulis ulang semua
modul.

### 4.3 Cara mendeteksi mode embed (tanpa menambah parameter di 99 tautan)

Server menandai sebuah request sebagai embed bila salah satu benar:

1. `Sec-Fetch-Dest: iframe` (dikirim browser otomatis untuk navigasi iframe,
   termasuk setelah redirect form), atau
2. header `X-Workspace-Embed: 1` (dipasang oleh skrip embed pada `fetch`
   Livewire), atau
3. parameter `?embed=1` (cadangan untuk pengujian/browser lama).

Dengan cara ini, tautan biasa dan redirect setelah simpan tetap berada di
layout embed tanpa perlu mengubah setiap route/controller.

### 4.4 Alur buka/tutup tab

1. Klik menu sidebar ditangkap di fase capture (sebelum `wire:navigate`).
2. Jika path menu sudah punya tab, cukup aktifkan tab itu (tidak duplikat).
3. Jika belum, buat tab baru, lalu muat iframe `url + ?embed=1`.
4. Tab aktif disimpan; URL browser diubah dengan `history.pushState`.
5. `popstate` (tombol back/forward browser) memilih tab yang cocok.
6. Tutup tab: iframe dihapus (`src = about:blank` lalu `remove()`) agar
   memori dilepas. Jika ada ketikan belum disimpan, minta konfirmasi.

### 4.5 Pemulihan setelah refresh

- Daftar tab disimpan di `sessionStorage` dengan kunci berversi
  `workspace-tabs:v1` berisi `id`, `title`, `url`, `icon`, `pinned`.
- Saat refresh, server merender halaman sesuai URL aktif. Skrip workspace
  membuat tab untuk URL itu dan memulihkan tab lain.
- **Tab lain dipulihkan secara lazy:** iframe-nya baru dimuat ketika tab
  diklik. Ini mencegah 8 halaman dimuat serentak (memori + lock session).
- Isi form yang belum disimpan **tidak** dijamin pulih setelah refresh;
  yang dipulihkan hanya daftar tab dan tab aktif. Ini batasan yang
  disepakati, bukan bug.

### 4.6 Batas memori

- Maksimal **8 tab di desktop** dan **6 tab di HP** (termasuk Dashboard
  yang pinned).
- Saat batas tercapai, tampilkan toast: minta tutup salah satu tab.
- Saat tab ditutup, iframe dibuang, bukan sekadar disembunyikan.
- Ada pengukuran wajib sebelum merge (lihat §9).

---

## 5. Spesifikasi UI Tab Bar

### 5.1 Susunan di dalam navbar

Desktop (satu baris, menggantikan pill + breadcrumb):

```
[ Dashboard ] [ Eng - Field Work x ] [ Eng - Sizing Project x ] ... [ v ]   [bell] [nama] [avatar]
```

HP/tablet (dua baris di dalam header):

```
[☰]                                                        [bell] [avatar]
[ Dashboard ] [ Eng - Field Work x ] [ Eng - Sizing ... ] ...        [ v ]
```

- Tinggi tab 34px; baris tab desktop menempel di area kiri header.
- Tab aktif: latar aksen lembut + teks aksen + garis bawah 2px aksen.
- Tab inaktif: transparan, hover memakai `--menu-hover-bg` (AGENTS §7.4).
- **Tidak memakai warna-warni per menu.** Satu warna aksen untuk semua tab.
- Mode gelap memakai token tema (`--card-bg`, `--card-border`,
  `--text-primary`), bukan kelas `slate-800`/`indigo-400` hardcoded.
- Breadcrumb lama disembunyikan saat mode tab aktif.

### 5.2 Isi tab

- Ikon kecil (16px) + judul.
- Prefix departemen singkat: `Adm`, `Sales`, `Eng`, `Mkt`, `Mgmt`, `Tek`,
  `Cust` (mengambil `section` dari breadcrumb server).
- Tab pinned tidak punya tombol `x`.
- Tab dengan ketikan belum disimpan menampilkan titik kuning 6px sebelum
  tombol tutup.
- Tombol tutup 24px, `rounded-lg`, muncul saat hover/fokus dan selalu
  tampil di tab aktif (agar bisa diakses keyboard).

### 5.3 Overflow dan menu ujung kanan

- Kontainer tab `overflow-x-auto no-scrollbar`; tab aktif otomatis
  di-scroll ke tampilan.
- Tombol `v` di ujung kanan memakai **`<x-dropdown>` + `<x-dropdown-link>`**
  (AGENTS §7.4), isi: `Close others`, `Close all tabs`.
- `Close all` tetap menyisakan tab Dashboard.

### 5.4 Aksesibilitas

- Bar: `role="tablist"`, tab: `role="tab"` + `aria-selected`.
- Keyboard: panah kiri/kanan, Home/End, Enter/Space, Delete untuk menutup.
- Fokus awal tetap terlihat (`focus-visible:ring`).
- Iframe diberi `title` sesuai judul tab.
- Hormati `prefers-reduced-motion` untuk animasi buka/tutup tab.

### 5.5 Mobile (workspace tetap aktif)

- Di bawah `lg`, header menjadi dua baris: baris atas hamburger + lonceng +
  avatar, baris bawah deretan tab yang bisa digeser horizontal.
- Tab memakai versi ringkas: ikon + judul yang dipotong, tombol tutup
  selalu tampil di tab aktif.
- Maksimal tab di HP **6** (lebih kecil dari desktop) untuk menjaga memori;
  saat penuh, tampilkan toast.
- Menu `v` (Close others / Close all) tetap tersedia di ujung kanan.

---

## 6. Aturan Interaksi

1. **Buka dari sidebar:** klik kiri biasa = buka/aktifkan tab.
   Ctrl/Cmd/Shift+klik = biarkan browser membuka tab browser baru.
2. **Link di dalam iframe:** tetap di tab yang sama (list -> detail).
   Judul dan URL tab ikut berubah lewat pesan dari iframe.
3. **Tab aktif dan sidebar:** status aktif sidebar disamakan dengan tab
   aktif (pakai `data-nav-active`/`data-nav-inactive`); grup induknya
   dibuka bila sedang terlipat.
4. **Tutup tab aktif:** pindah ke tab di sebelah kiri; jika tidak ada,
   ke Dashboard.
5. **Tutup tab dengan ketikan:** `window.askConfirm(...)` dengan tone
   `warning`, judul "Close this tab?", pesan bahwa ketikan akan hilang.
6. **Tab penuh (8):** `window.toast(...)` peringatan, tab baru tidak dibuat.
7. **Aksi simpan/gagal:** toast tetap milik shell. Jika aksi terjadi di
   dalam iframe, iframe mengirim pesan toast ke shell agar tampil di posisi
   yang sama dan tetap terlihat walau tab sedang tidak aktif.
8. **Logout / akses ditolak:** iframe yang menerima redirect ke login
   mengirim pesan ke shell untuk melakukan full reload.

---

## 7. Langkah Pengerjaan

### Step 0 - Persiapan

1. Buat worktree `feature/div-panel` di
   `/home/yeski/3dyapp-panel`, symlink `.env` dan `public/build`.
2. Tambahkan entry worktree baru ke tabel AGENTS §1 (commit `docs(sys)`).
3. Buat `config/workspace.php`:
   `enabled` (env `WORKSPACE_TABS_ENABLED`, default `false`) dan
   `max_tabs` (default 8). Kill switch cukup mematikan env.

### Step 1 - Layout embed

1. Tambah `layouts/embed.blade.php`: `<head>` minimal (CSRF, appearance,
   Vite `app.css` + `app.js` + `submit-loader.js`), `<body>` hanya
   `{{ $slot }}` + `<x-confirm-dialog>` + wadah toast.
2. **Jangan** muat `notification-center.jsx` dan `echo.js` di layout embed
   agar tidak ada koneksi Reverb per tab.
3. Di `AppLayout` (atau `layouts/app.blade.php`), pilih layout embed bila
   request terdeteksi embed (§4.3); jika tidak, layout normal.

### Step 2 - Metadata halaman

1. Pindahkan perhitungan breadcrumb/section/icon dari
   `layouts/partials/header.blade.php` ke satu helper/view composer
   (mis. `App\Support\PageContext`) agar dipakai header dan layout embed.
2. Layout embed menulis `window.__workspacePage = { url, title, section,
   icon }` dan mengirimkannya ke parent setelah load dan setiap
   `livewire:navigated`.

### Step 3 - Skrip workspace (shell)

Buat `resources/js/workspace-tabs.js` (vanilla, diimpor `app.js`, hanya
aktif bila `#workspace-tabs` ada):

- `init()`: baca `sessionStorage`, buat tab untuk URL saat ini, pasang
  listener klik sidebar (capture), `popstate`, `message`.
- `openTab(url, meta)`, `activateTab(id)`, `closeTab(id)`,
  `closeOthers()`, `closeAll()`, `syncSidebar(path)`, `syncUrl(tab)`.
- `createFrame(tab)`: buat iframe `src = url + embed`, `loading="lazy"`,
  `title`, dan pasang hanya saat pertama diaktifkan.
- Simpan state ke `sessionStorage` setiap ada perubahan.
- Batas 8 tab desktop / 6 tab HP + toast.

### Step 4 - Skrip embed (isi tab)

Buat `resources/js/workspace-embed.js` (dimuat hanya di layout embed):

- Kirim `workspace:meta` (url, title, section, icon, dirty=false) ke parent.
- Pantau `input`/`change` pada form -> `workspace:dirty` true.
- Reset dirty setelah `livewire:navigated` atau submit berhasil.
- Teruskan `session('success'|'warning'|'error')` ke parent sebagai
  `workspace:toast`.
- Patch `window.fetch` untuk menambahkan header `X-Workspace-Embed: 1`
  (menjaga Livewire navigate tetap memakai layout embed).
- Kirim `workspace:login` bila halaman akhirnya berisi form login.

### Step 5 - Tampilan tab bar

Buat `resources/views/layouts/partials/workspace-tabs.blade.php`:

- Markup tablist, tombol tutup, menu `v` memakai `<x-dropdown>`.
- Gaya memakai token tema; tanpa warna per menu.
- Badge/titik "belum disimpan".

### Step 6 - Integrasi layout shell

1. Saat `config('workspace.enabled')` dan bukan request embed:
   - render tab bar,
   - render wadah iframe,
   - **jangan** echo `{{ $slot }}` (halaman tetap dieksekusi server,
     tetapi isinya dimuat lewat iframe).
2. Saat flag mati: perilaku lama tidak berubah sama sekali.
3. Sembunyikan pill lokasi dan breadcrumb lama saat mode tab aktif; tab
   menjadi penunjuk lokasi. Saat flag mati, pill + breadcrumb kembali
   seperti sekarang.

### Step 7 - Integrasi sidebar

1. Intersepsi klik pada `#sidebar a[href]` (capture phase) dengan syarat
   same-origin, bukan `target=_blank`, tanpa modifier.
2. Jangan ubah 99 tautan satu per satu; cukup satu listener global.
3. Setelah tab aktif berubah, panggil `syncSidebar(path)`.
4. `wire:navigate.hover` pada sidebar tidak lagi dipakai saat workspace
   aktif (prefetch dibatalkan oleh `preventDefault`).

---

## 8. File yang Akan Disentuh

| File | Perubahan |
|---|---|
| `config/workspace.php` | baru: flag, max tabs |
| `app/View/Components/AppLayout.php` | pilih layout embed/normal |
| `resources/views/layouts/app.blade.php` | wadah workspace, tab bar, kondisi flag |
| `resources/views/layouts/embed.blade.php` | baru: layout minimal |
| `resources/views/layouts/partials/header.blade.php` | pakai PageContext; sembunyikan pill/breadcrumb saat tab aktif |
| `resources/views/layouts/partials/sidebar.blade.php` | tambah atribut metadata bila perlu (tanpa mengubah 99 link) |
| `resources/views/layouts/partials/workspace-tabs.blade.php` | baru: tab bar |
| `resources/js/workspace-tabs.js` | baru: state, iframe, sidebar, storage |
| `resources/js/workspace-embed.js` | baru: bridge iframe |
| `resources/js/app.js` | impor skrip workspace |
| `app/Support/PageContext.php` | baru: section/title/icon dari route |
| `docs/plans/sistem/PLAN-SISTEM-WORKSPACE-TABS.md` | dokumen ini |
| `AGENTS.md` | entry worktree baru |

Tidak ada perubahan route/controller bisnis. Tidak ada migrasi database.

---

## 9. Testing & Verifikasi

### 9.1 Test otomatis

1. `php artisan test --filter=WorkspaceTabsTest` (baru):
   - request biasa tetap memakai layout penuh;
   - request dengan `Sec-Fetch-Dest: iframe` atau `X-Workspace-Embed: 1`
     memakai layout embed dan **tidak** memuat sidebar/header;
   - embed tetap butuh login dan izin role (tidak ada bypass);
   - `X-Frame-Options: SAMEORIGIN` tetap ada.
2. `php artisan test --filter=UiFeedbackStandardTest` (AGENTS §7.3/§7.4):
   tidak boleh ada `confirm()` baru; menu tutup tab memakai `x-dropdown`.
3. Test fitur yang sudah ada di area yang disentuh (header/breadcrumb)
   harus tetap lulus.

### 9.2 Uji browser (Playwright, manual sebelum merge)

Skenario wajib:

1. Buka **Field Work**, lalu **Sizing Project** -> muncul 3 tab:
   Dashboard, Field Work, Sizing Project.
2. Ketik di form Sizing Project, pindah ke Field Work, kembali ->
   teks dan posisi scroll masih ada.
3. Klik menu Field Work lagi dari sidebar -> tidak membuat tab ganda.
4. Sidebar mengikuti tab yang aktif.
5. Tutup tab aktif -> pindah ke tab kiri; tutup tab dengan ketikan ->
   muncul kartu konfirmasi (bukan `confirm()` bawaan).
6. Refresh di tab Sizing Project -> tetap di tab itu, tab lain dipulihkan
   dan dimuat saat diklik.
7. Buka 9 tab -> tab ke-9 ditolak dengan toast.
8. Mode terang & gelap: tab aktif, hover, tombol tutup, menu `v` terbaca.
9. **Ukuran HP (390px):** header dua baris, tab bisa digeser, ketikan di
   form tetap ada setelah pindah tab.
10. Buka 7 tab di HP: tab ke-7 ditolak dengan toast (batas 6).
11. Keyboard: panah kiri/kanan, Home/End, Enter, Delete.
12. Simpan form dari dalam tab -> toast muncul di shell, tab tetap aktif.
13. Buka Quotation -> View detail di dalam tab -> judul tab berubah,
    URL browser ikut berubah, tombol back browser kembali ke tab sebelumnya.

### 9.3 Pengukuran performa (wajib, sebelum merge)

Bandingkan kondisi 1 tab vs 8 tab pada halaman yang sama:

- JS heap tiap frame (via `performance.memory` di dalam iframe),
- waktu muat tab pertama,
- jumlah koneksi jaringan aktif (pastikan tidak ada websocket per tab).

Ambang yang disepakati: kenaikan heap 8 tab tidak lebih dari **150 MB**
dan tidak ada koneksi Reverb di iframe. Jika melewati ambang, turunkan
`max_tabs` atau aktifkan lazy-load lebih agresif; jangan merge tanpa angka.

---

## 10. Kriteria Selesai

- [ ] Klik **Field Work** lalu **Sizing Project** menghasilkan tab
      `Dashboard`, `Field Work`, `Sizing Project`.
- [ ] Ketikan dan posisi scroll di Sizing Project tetap ada setelah
      berpindah tab.
- [ ] Menu sidebar yang sudah terbuka tidak membuat tab ganda.
- [ ] Status aktif sidebar mengikuti tab yang dipilih.
- [ ] Tutup tab memindahkan layar ke tab terdekat; tab dengan ketikan
      meminta konfirmasi lewat `<x-confirm-dialog>`.
- [ ] Refresh mempertahankan tab aktif; tab lain dipulihkan lazy.
- [ ] Maksimal 8 tab desktop / 6 tab HP dengan toast saat penuh.
- [ ] Mode terang/gelap dan keyboard berfungsi.
- [ ] Di HP (390px) header dua baris, tab bisa digeser, dan ketikan form
      tetap ada saat pindah tab.
- [ ] `WorkspaceTabsTest`, `UiFeedbackStandardTest`, dan test area
      header/sidebar lulus.
- [ ] Hasil pengukuran performa dicatat di PR/commit.
- [ ] Flag bisa dimatikan tanpa mengubah kode lain.

---

## 11. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Livewire/ID bentrok antar tab | Isolasi iframe; tidak menyuntik HTML ke DOM shell |
| Koneksi Reverb berlipat | Layout embed tidak memuat Notification Center/echo |
| Redirect setelah simpan keluar dari mode embed | Deteksi `Sec-Fetch-Dest: iframe` + header `X-Workspace-Embed` pada fetch |
| Memori 8 tab besar | Batas 8, lazy restore, buang iframe saat ditutup, ukur sebelum merge |
| Session lock PHP saat banyak tab dimuat | Lazy load; hanya tab aktif yang memuat saat awal |
| Back/forward browser membingungkan | Satu entry history per perpindahan tab; perubahan di dalam iframe memakai `replaceState` |
| Sesi habis / logout di dalam tab | Iframe kirim `workspace:login`, shell full reload ke login |
| Notifikasi ganda | Toast hanya dari shell; embed meneruskan pesan lewat postMessage |
| Tab bar menghabiskan ruang header | Tab menggantikan pill + breadcrumb; breadcrumb disembunyikan saat mode tab |
| HP sempit | Header dua baris + tab ringkas + batas 6 tab |

---

## 12. Bertahap (Rollout)

1. **Fase 1 - Fondasi (flag mati):** layout embed, skrip workspace, tab bar,
   test server-side. Belum terlihat user.
2. **Fase 2 - Pilot:** aktifkan flag untuk akun Admin/Yeski. Uji semua
   skenario §9.2 + performa.
3. **Fase 3 - Rollout desktop:** aktifkan untuk role lain setelah pilot
   disetujui; pantau keluhan/memori.
4. **Rollback:** matikan `WORKSPACE_TABS_ENABLED`; layout kembali normal
   tanpa perlu revert kode.

---

## 13. Di Luar Lingkup

- Memulihkan isi form yang belum disimpan setelah refresh.
- Sinkronisasi tab antar-jendela browser.
- Drag-and-drop urutan tab.
- Split view / dua tab berdampingan.
- Mengganti seluruh `wire:navigate` di aplikasi.
