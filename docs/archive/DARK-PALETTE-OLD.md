# Palet Dark Mode Global (Catatan Sesi)

> SUPERSEDED — digantikan referensi resmi `docs/3DY_Dark_Theme_Palette.md`
> (Unified Dark Theme). Tabel di bawah versi percobaan awal, dipertahankan
> sebagai riwayat. Implementasi berlaku memakai nilai unified.

Disetujui user pada sesi login redesign v2, 23 Sep 2026.
Status: **UJI COBA di dashboard dulu** (`feature/general-dashboard`).
Kalau cocok, apply ke seluruh halaman (ganti blok `.dark` di `resources/css/app.css`).

## Token

| Token         | Hex       | Peran                          |
|---------------|-----------|--------------------------------|
| `--bg-page`   | `#1a1f2e` | background utama               |
| `--bg-card`   | `#222840` | card / panel                   |
| `--bg-sidebar`| `#1e2336` | sidebar                        |
| `--bg-hover`  | `#2a3150` | hover state                    |
| `--bg-input`  | `#2d3454` | input field                    |
| `--border`    | `#363d5c` | border default                 |
| `--border-strong` | `#4a5280` | border emphasis             |
| `--text-primary` | `#e2e8f8` | teks utama                  |
| `--text-secondary` | `#8d96b8` | teks pendukung            |
| `--text-muted`| `#545d7e` | placeholder, label kecil       |
| `--accent`    | `#4f7df3` | biru brand disesuaikan dark    |
| `--accent-soft` | `#1e3a7a` | background badge/tag biru    |

## Mapping ke token existing (saat apply global)

- `--bg` ← `--bg-page`
- `--card-bg` ← `--bg-card`, `--card-border` ← `--border`, `--card-bg-hover` ← `--bg-hover`
- `--sidebar-bg` ← `--bg-sidebar`
- `--input-bg` ← `--bg-input`
- `--text-primary/secondary/muted` ← token teks di atas
- Accent tema (ocean/dll) TIDAK diubah; `--accent` dipakai per-view bila perlu

## Catatan percobaan dashboard

- Scope: hanya `dashboard/index.blade.php` via class `dash-scope` (`.dark .dash-scope` override, tanpa ubah `.dark` global).
- Varian Tailwind `dark:slate-*` di view diganti arbitrary hex dari tabel di atas.
- Layout shared (sidebar/header) TIDAK diubah.
