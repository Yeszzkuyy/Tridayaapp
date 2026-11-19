# Theme System (Dark Mode + Accent)

Aplikasi memakai **satu sistem tampilan** berbasis dua sumbu:

- **Mode** (light / dark / system) — dikendalikan kelas `.dark` pada `<html>`.
- **Accent** (ocean / terracotta / purple / emerald) — dikendalikan atribut `data-theme`.

Mode dan accent **independen**: mengubah accent tidak mengubah surface/mode, dan sebaliknya.

---

## Arsitektur Token

Token dideklarasikan di `resources/css/app.css` + `resources/css/themes/accent/*.css`, **tidak lagi di inline `<style>` layout**.

| Layer | Token | Dideklarasikan | Fungsi |
|---|---|---|---|
| Accent | `--accent-50..950` (RGB triplet) | `themes/accent/{ocean,terracotta,purple,emerald}.css`, dipilih via `html[data-theme=...]` | Identitas brand/interaksi: tombol utama, link, sidebar aktif, fokus input, today marker kalender |
| Semantic | `--semantic-info/success/warning/danger` (RGB triplet) | `:root` app.css | Status (Open/Done/Cancelled/dll), badge, donut. **Tidak mengikuti accent** |
| Surface | `--bg`, `--sidebar-bg`, `--card-bg`, `--input-*`, `--nav-*`, `--text-*`, dll | `:root` (light) + `.dark` (dark) | Permukaan & tipografi, bergantung mode saja |
| Compatibility | `--theme-blue-*`, `--theme-indigo-*` | `:root` app.css (bernilai Ocean) | Lapisan legacy: seluruh utility `blue-*`/`indigo-*` yang belum dimigrasi |

### Aturan pakai

- **Kode baru**: jangan pakai `blue-*`/`indigo-*` sebagai aksen. Gunakan `accent-*` (mis. `bg-accent-600`, `text-accent-600`, `ring-accent-500`).
- **Status & data-viz**: tetap pakai warna semantik (`green-*`, `red-*`, `yellow-*`, hex status), jangan sampai berubah karena accent.
- **Menambah accent baru**: buat file `resources/css/themes/accent/<nama>.css` berisi 11 triplet `--accent-50..950`, tambahkan opsi di `settings/edit.blade.php`, dan masukkan ke validasi `SettingsController` (`in:ocean,terracotta,purple,emerald`) + test `SettingsTest`.
- **Menambah kelas Tailwind accent/baru**: jalankan `npm run build`.

### Nilai surface dark (fixed)

```css
.dark {
  --bg:#111111; --sidebar-bg:#111111; --sidebar-border:#3F3F46;
  --card-bg:#2B2B2B; --card-border:#3F3F46; --card-bg-hover:#333333;
  --input-bg:#2B2B2B; --input-bg-hover:#333333; --input-border:#4B5563;
  --input-border-focus:rgb(var(--accent-400)/1);
  --nav-active-bg:rgb(var(--accent-500)/.18); --nav-active-text:rgb(var(--accent-300)/1);
}
```

**Jangan mengubah surface per accent.**

---

## Alur Preferensi

1. **Penyimpanan**: `users.preferences` (JSON) — kolom `theme` dan `accent`. Dikelola `SettingsController` (`update`, `appearance`).
2. **Render server** (`layouts/app.blade.php`): `<html lang data-mode="..." data-theme="...">` diisi dari preferensi user.
3. **FOUC script** di `<head>` (sebelum CSS): baca preferensi server + `localStorage` (`appearance-mode` / `appearance-accent`), set `data-mode`, `data-theme`, dan kelas `dark`. Sinkron pre-CSS → tanpa flash.
4. **Interaksi** (Settings): `.store.appearance` di `resources/js/app.js` menerapkan mode/accent secara instan, menulis `localStorage`, dan sync ke `POST /settings/appearance` (async).
5. **livewire:navigated / navigasi SPA**: `data-theme` dan `data-mode` melekat pada `<html>` sehingga tidak hilang saat navigate.

### Precedence nilai

`localStorage` (perangkat) → preferensi server (persisten antar perangkat) → `prefers-color-scheme` (hanya saat mode = system).

---

## Sinkronisasi `data-theme` / `data-mode` (FOUC)

Script antarmuka sebelum CSS memakai urutan:

```js
var pref   = <theme server>;            // 'light'|'dark'|'system'
var accent = <accent server>;           // 'ocean'|'terracotta'|...
var mode   = localStorage['appearance-mode'] || pref;
var acc    = localStorage['appearance-accent'] || accent;
// legacy migrasi: localStorage['dark-mode'] === 'true'/'false' → 'dark'/'light'
root.setAttribute('data-mode', mode);
root.setAttribute('data-theme', acc);
root.classList.toggle('dark', mode==='dark' || (mode==='system' && matchMedia('(prefers-color-scheme:dark)').matches));
```

---

## !important Override Rules (app.blade.php)

Aturan override dark di `<style>` layout **masih berlaku** untuk kelas legacy (`bg-slate-*`, `bg-white`, `text-slate-*`, dll). Perubahan dari versi lama:

- Semua hardcoded hex (`#171010`, `#2B2B2B`, `#423F3E`, `#362222`, `#5A5451`, `#E0A370`) diganti token (`var(--card-bg)`, `var(--input-bg)`, dst).
- Override `.dark .text-blue-*` → `#E0A370` **dihapus** — blue/indigo kini semantic Ocean, bukan terracotta.
- Override `[data-status-color]` yang memetakan ke terracotta **dihapus** — status tetap berwarna semantiknya sendiri.
- Toggle ON dan fokus input pakai `var(--accent-*)`.

> Catatan: karena `--theme-blue-*` kini selalu Ocean (bukan terracotta di dark), view legacy dengan `text-blue-600`/`bg-blue-600` di dark mode tetap **biru**, bukan hangat seperti sebelumnya. Ini disengaja (semantic tetap biru). Migrasi bertahap ke `accent-*` terjadi per komponen.

---

## Input / Form

Aturan global input (background, border, teks, placeholder) tetap seperti sebelum, kini fokus memakai `var(--input-border-focus)` (accent-aware):

```css
input:focus, select:focus, textarea:focus { border-color: var(--input-border-focus) !important; }
```

Aturan-aturan lama yang lain (input tidak kena `:not([type=...])`, halaman auth standalone, error state teks) tetap berlaku.

---

## Chart (ApexCharts)

Gunakan helper global `window.getAppearanceColors()` (`resources/js/app.js`) — membaca token live dari computed style sehingga **tidak perlu snapshot `isDark` saat render**:

```js
const c = window.getAppearanceColors();
// c.dark, c.theme, c.accent500..., c.info/success/warning/danger, c.cardBg
```

Contoh donut (marketing): warna status **semantik tetap**, hanya `theme.mode` dan `stroke` yang ikut mode/accent. Semua donut meregistrasi instance-nya (mis. `window.marketingDonutChart`) dan dipanggil ulang lewat listener `appearance:change`:

```js
window.addEventListener('appearance:change', () => {
  if (window.marketingDonutChart) window.marketingDonutChart.updateOptions(donutOptions());
});
```

**Dilarang** me-hardcode warna status per mode (misal `isDark ? '#D18B5C' : '#3b82f6'`) — status harus tetap semantik di kedua mode.

---

## Compat Layer `--theme-blue-*` / `--theme-indigo-*`

Variabel kompatibilitas **hanya** digunakan 3 lokasi (audit terakhir); semuanya halaman/section **standalone** yang tidak memakai token accent — sengaja DIPERTAHANKAN:

| Lokasi | Penggunaan | Alasan keep |
|---|---|---|
| `resources/css/app.css` | Definisi variabel + conic-gradient notifikasi (app.css:450-455) | Layer compat Ocean (fixed) + gradien dekoratif |
| `resources/views/ai/chat.blade.php` | ~46 ref CSS scoped `.ai-*` | Halaman standalone brand AI |
| `resources/views/calendar.blade.php` | 1 ref (`.card .time`) | Halaman standalone |

Semua `.blade.php` lain sudah dimigrasi dari `blue-*`/`indigo-*` ke `accent-*`. Halaman `auth/*` memakai inline `--accent` scoped sendiri (brand) — di luar sistem theme, dibiarkan.

Cleanup lebih lanjut (migrasi ai-chat) = pekerjaan terpisah, tidak menyentuh sistem token.

---

## Ringkasan Migrasi Accent (seluruh UI)

Setelah sistem token live, seluruh UI aplikasi dimigrasi dari `blue-*`/`indigo-*` hardcode ke skala `accent-*` (Tailwind) sehingga ikut preferensi accent user. Klasifikasi hasil:

**MIGRASI (±1.050 kemunculan, 114 file views):**
- Focus ring/border input & focus-visible → `accent-500` / `dark:accent-400`
- Tombol primer/submit → `bg-accent-600 hover:bg-accent-700`
- Link utama & label aktif → `text-accent-600/700` (`dark:text-accent-300/400`)
- Tab aktif (projects/show, customers/show) → `border/text-accent-600`
- Checkbox/radio terpilih → `text-accent-600`
- Progress bar & pill filter aktif → `bg-accent-600`
- Toggle settings (`peer-checked:bg-accent-600`)
- Tindakan ikon lunak (Lihat/Edit/Download) → `bg-accent-50/100`
- Komponen reuse (15 file) — primary-button, text-input, nav-link, profile-tabs, dll.

**KEEP (sengaja tetap `blue-*`/`indigo-*`):**
- Badge status semantik (`status-badge` 'blue', status survey 'scheduled', status lead 'new', `*_COLORS`)
- Warna avatar data (`user-avatar` palette) & chip kategori
- Pair stat-card dekoratif di dashboard (biru+indigo, merah, hijau, dll) supaya kartunya berbeda-beda
- Blob/glow aurora dekoratif + bullet dot sidebar
- Rule CSS `.peer-checked\:bg-blue-600` di `app.blade.php:113` (nama dev tetap, nilainya sudah `--accent-600`)

> Aturan: kalau warna = **semantik** (status), pasangan statistik (pair), atau data-kategori → jangan ubah. Kalau = aksi primer/selected/fokus/link → pakai `accent-*`.

---

## Regresi & Verifikasi

Matriks regresi: **Light/Dark × Ocean/Terracotta/Purple/Emerald** pada Dashboard, Teknisi, Marketing, Sales, Sidebar, Header, Tables, Forms, Modal, Dropdown, Tabs, Calendar, Charts, Status badge, Settings. Verifikasi: `npm run build` + `php artisan test` (218 hijau).