# Panduan Desain UI — 3DY App

Dokumen ini adalah acuan tunggal untuk gaya visual, komponen, dan konvensi UI di seluruh aplikasi. Baca sebelum menambah/mengubah tampilan apa pun.

## 1. Ringkasan & Stack

| Lapisan | Teknologi |
|---|---|
| Rendering | Blade (`resources/views`), komponen `x-*` |
| Styling | Tailwind CSS (config `tailwind.config.js`) |
| Interaksi | Alpine.js (`resources/js/app.js`) |
| Chart | ApexCharts (donut, `import ApexCharts from 'apexcharts'`) |
| Kalender | FullCalendar (kalender teknisi) |

## 2. Design Tokens

### Font
- **Plus Jakarta Sans** (400–800) dimuat via `fonts.bunny.net` di `layouts/app.blade.php:14`; fallback ke `defaultTheme.fontFamily.sans`.

### Warna aksen
- Sistem tampilan = **2 sumbu: Mode (light/dark/system) + Accent (ocean/terracotta/purple/emerald)**.
  Detail lengkap: `docs/DARK_MODE_SYSTEM.md`.
- Aksen dipilih pengguna di Settings; token `--accent-50..950` di
  `resources/css/themes/accent/*.css`. Default: **Ocean** (`#3b82f6`).
- Komponen interaksi baru pakai kelas `accent-*` (`bg-accent-600`, `text-accent-600`, `ring-accent-500`).
  Kelas `blue-*`/`indigo-*` adalah **lapisan kompatibilitas** (tetap Ocean) — jangan dipakai untuk kode baru.
- Netral: palet **slate** (50–900) — dasar semua permukaan & teks.
- Status (Open/Done/Cancelled, dll) **tidak mengikuti accent** — selalu semantik (green/red/yellow/blue, dst).

### Status project (donut, badge, bar)

| Status | Warna | Hex (bar/donut) |
|---|---|---|
| Open | blue (info) | `#3b82f6` |
| On Progress | yellow | `#eab308` |
| Pending | orange | `#f97316` |
| Hold | red | `#dc2626` |
| Done | green | `#22c55e` |
| Cancelled | slate | `#64748b` |
| Warranty | cyan | `#06b6d4` |
| Maintenance | purple | `#a855f7` |

Sumber: `TechnicianDashboardController::STATUS_BADGE_COLORS` & `STATUS_BAR_COLORS`.

### Funnel lead (ApexCharts donut marketing)
`new=#3b82f6, contacted=#eab308, qualified=#a855f7, proposal=#f97316, won=#22c55e, lost=#ef4444` — lihat `marketing/dashboard.blade.php:186-193`.

### Shadow & easing (`resources/css/app.css:8-12`)
```css
--ease-out: cubic-bezier(0.16, 1, 0.3, 1);
--shadow-rest: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
--shadow-lift: 0 12px 32px -12px rgba(15,23,42,.16), 0 4px 12px -6px rgba(15,23,42,.08);
```
Dark mode memakai varian hitam lebih pekat.

### Radius & spacing
- Kartu & section: `rounded-2xl`
- Tombol, badge, input: `rounded-xl` / `rounded-full`
- Container dashboard: `max-w-[1400px] mx-auto space-y-6`
- Padding main: `px-4 sm:px-6 lg:px-8`

## 3. Layout

- Sidebar **fixed 280px** kiri; off-canvas + overlay di mobile (<1024px).
- Header di atas `main`; flash message (success/error) di bawah header.
- Struktur ada di `layouts/app.blade.php` + `layouts/partials/`.

## 4. Dark Mode & Aksen

Berjalan **satu sistem tampilan** (mode × accent). Detail lengkap: `docs/DARK_MODE_SYSTEM.md`.

### Kelas yang di-override `!important` (dark)
| Kelas | Menjadi |
|---|---|
| `bg-white`, `bg-slate-50` | `var(--card-bg)` |
| `bg-slate-100/200/300`, `bg-gray-300` | `#2B2B2B / #3F3F46 / #3F3F46` |
| `border-slate-200` / `-300` | `var(--card-border)` / `var(--input-border)` |
| `text-slate-700/800/900` | `var(--text-primary)` |
| `text-slate-600` | `var(--text-secondary)` |
| `text-slate-500` | `var(--text-muted)` |

### Aturan
- **Jangan** tambah `dark:bg-slate-700`/`dark:text-slate-*` untuk properti yang sudah di-override di atas — kalah `!important`, mubasir.
- Untuk properti tanpa override, `dark:` biasa tetap jalan (mis. `dark:bg-slate-700`, `dark:border-slate-600`).
- Input (text/select/textarea) diatur global via CSS var — **jangan** paksa `bg-white`/`border-slate-300` di blade; `--input-border` dark memakai `#4B5563` untuk visibilitas.
- Dark mode memakai surface `#111111`, `#2B2B2B`, `#333333`, dan `#3F3F46`. Surface **tidak berubah** per accent.
- Utility `blue`/`indigo` adalah lapisan kompatibilitas: **tetap Ocean di kedua mode** (sebelumnya berubah jadi terracotta di dark). Komponen interaksi baru harus pakai `accent-*`.
- Input focus mengikuti accent via `--input-border-focus`.
- Menambah override baru? Tambahkan aturan `!important` di blok `<style>` `app.blade.php` mengikuti pola yang ada; hardcoded hex harus memakai token var.

## 5. Komponen Blade (`resources/views/components/`)

| Komponen | Kegunaan |
|---|---|
| `stat-card` | Kartu statistik `title` + `value` + `icon` |
| `section-header` | Judul section + slot aksi kanan |
| `status-badge` | Badge status; `color` = green/red/yellow/blue/purple/orange/cyan/slate |
| `empty-state` | State kosong dengan label + deskripsi |
| `user-avatar` | Avatar user (nama → inisial/avatar) |
| `icon` | `<x-icon name="..." class="..."/>` (lihat daftar ikon) |
| `data-table`, `th` | Tabel data dengan kolom header |
| `modal`, `dropdown` | Modal & dropdown |
| `searchable-select`, `datepicker`, `text-input`, `input-label`, `input-error` | Form controls |
| `primary-button`, `secondary-button`, `danger-button`, `add-button` | Tombol |
| `info-tip`, `activity-item`, `nav-link` | Lainnya |

### Ikon tersedia (`x-icon`)
`grid, home, users, folder, search, eye, calendar, activity, book, tools, edit, trash, restore, chevron-right, logout, chart-bar, settings, briefcase, building, user, map-pin, phone, chat, mail, check-circle, bolt` — daftar lengkap di `icon.blade.php`.

## 6. Konvensi Tombol

| Aksi | Kelas standar |
|---|---|
| Simpan / Tambah / Submit utama | `bg-blue-600 hover:bg-blue-700 text-white` |
| Lihat / detail | `bg-indigo-50 hover:bg-indigo-100 text-indigo-700` |
| Edit | `bg-blue-100 hover:bg-blue-200 text-blue-700` |
| Hapus | `bg-red-100 hover:bg-red-200 text-red-700` |
| Aksi positif (convert/approve) | `bg-green-100 hover:bg-green-200 text-green-700` |
| Batal / Kembali | `border border-slate-300 text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200` |

**Larangan:** `bg-slate-*`, `bg-gray-*`, `bg-white` sebagai background tombol (ter-override dark mode → tombol menyatu dengan background).

## 7. Motion & Interaksi

- **Reveal on scroll**: `data-reveal` + `data-reveal-delay="1..3"` (IntersectionObserver di layout, hormati `prefers-reduced-motion`).
- **Angka animasi**: `x-data="counter(n)" x-init="start()" x-text="display"`.
- **Hover kartu**: lift `translateY(-3px)` + `--shadow-lift` (otomatis via CSS `main .bg-white.shadow-sm:hover`).
- **Tombol aktif**: `scale(0.97)`.
- **Fokus**: ring `rgba(59,130,246,.14)` pada input.

## 8. Chart (ApexCharts)

- Donut dipakai di `marketing/dashboard.blade.php` dan `teknisi/dashboard.blade.php`.
- Warna **status** selalu semantik — jangan diubah per mode/accent.
- Ambil warna token via `window.getAppearanceColors()` (live computed style) — tanpa snapshot `isDark`.
- Mode/accent berubah tanpa reload: dengarkan `appearance:change` lalu `updateOptions()`. Instance chart disimpan di `window.*DonutChart`.

## 9. Aturan Kontribusi UI

1. **Pakai komponen yang ada** sebelum bikin baru.
2. Konsisten: 1 view = 1 wrapper (`max-w-[1400px] mx-auto space-y-6` untuk dashboard).
3. **Setiap class Tailwind baru**: jalankan `npm run build` setelah merge ke `main`.
4. Setiap tambahan status baru: tambah warnanya di `STATUS_BADGE_COLORS` + `STATUS_BAR_COLORS` + palet `status-badge.blade.php`.
5. Hormati `prefers-reduced-motion`.
6. Dark mode: ikuti §4; jangan menambah `dark:` yang kalah override.
