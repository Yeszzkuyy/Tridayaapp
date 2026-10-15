
# 3DY App — Unified Dark Theme Color Palette

> Referensi warna resmi untuk dark mode 3DY App (Tridaya Group).
> Gunakan file ini sebagai acuan ketika meminta AI membuat atau memodifikasi tampilan UI.
> Teknologi: Laravel + Tailwind CSS + Livewire/Alpine.js

---

## Prinsip Tema

- **Tone**: Biru-abu gelap (Slate Deep) — konsisten dengan brand biru 3DY
- **Hierarki surface**: Setiap layer naik ~7–10 poin kecerahan, tidak ada lompatan tiba-tiba
- **Aksen utama**: Biru `#3b7ef8` (brand) + Teal `#0fd4a0` (navigasi aktif)
- **Tujuan**: Nyaman dipakai seharian, tidak terlalu gelap, tidak terlalu terang

---

## 1. Surfaces

Lapisan background dari paling gelap ke paling terang.

```css
--surface-outer:    #0d1117;  /* background body / luar halaman */
--surface-sidebar:  #161b27;  /* sidebar navigasi */
--surface-base:     #1c2235;  /* konten utama / halaman */
--surface-card:     #232b3e;  /* card, panel, modal */
--surface-card-2:   #2a3350;  /* card hover / nested card */
--surface-input:    #1e2640;  /* input field */
--surface-tooltip:  #2f3a56;  /* dropdown, tooltip, popover */
```

### Hierarki Visual

```
#0d1117  ← body / outer (paling gelap)
  #161b27  ← sidebar
    #1c2235  ← halaman utama
      #232b3e  ← card
        #2a3350  ← nested card / hover state
          #2f3a56  ← tooltip / dropdown
```

---

## 2. Border

```css
--border-subtle:    #2a3352;  /* border default, sangat tipis */
--border-default:   #334070;  /* border card & input */
--border-strong:    #445088;  /* border emphasis / focus ring */
```

---

## 3. Typography

```css
--text-primary:     #e8edf8;  /* judul, konten utama */
--text-secondary:   #8e99bf;  /* subtitle, label, keterangan */
--text-muted:       #4f5b7e;  /* placeholder, metadata, timestamp */
--text-disabled:    #353e58;  /* disabled state */
```

---

## 4. Accent & Brand

```css
/* Biru — brand utama */
--accent-blue:        #3b7ef8;
--accent-blue-soft:   #1a3a7a;           /* background badge/tag biru */
--accent-blue-glow:   rgba(59,126,248,0.15); /* glow / box-shadow biru */

/* Teal — navigasi aktif, success highlight */
--accent-teal:        #0fd4a0;
--accent-teal-soft:   #0a3d30;           /* background badge/tag teal */

/* Purple — icon spesial, modul management */
--accent-purple:      #7c6ef5;
--accent-purple-soft: #2a2060;           /* background badge/tag purple */
```

---

## 5. Status Colors

Dipakai untuk badge, label, alert, dan indikator status di seluruh modul.

```css
/* Sukses — lunas, selesai, aktif */
--status-success:     #10d078;
--status-success-bg:  #062d1a;

/* Warning — pending, on progress, menunggu */
--status-warning:     #f5a623;
--status-warning-bg:  #2d1f05;

/* Danger — overdue, gagal, hapus, cancel */
--status-danger:      #f04f4f;
--status-danger-bg:   #2d0a0a;

/* Info — new, informasi, lead baru */
--status-info:        #3b7ef8;
--status-info-bg:     #0f1f45;

/* Neutral — draft, belum ada aksi */
--status-neutral:     #8e99bf;
--status-neutral-bg:  #1c2235;
```

---

## 6. Icon Card Colors

Untuk icon di Key Summary cards (dashboard) dan komponen serupa.

```css
/* Customers */
--icon-customers:     #3b7ef8;
--icon-customers-bg:  #1a3a7a;

/* Projects */
--icon-projects:      #7c6ef5;
--icon-projects-bg:   #2a2060;

/* Active / On Progress */
--icon-active:        #0fd4a0;
--icon-active-bg:     #0a3d30;

/* Documents */
--icon-docs:          #3b7ef8;
--icon-docs-bg:       #1a3a7a;

/* Invoice / Admin */
--icon-invoice:       #f5a623;
--icon-invoice-bg:    #2d1f05;

/* Leads / Marketing */
--icon-leads:         #7c6ef5;
--icon-leads-bg:      #2a2060;
```

---

## 7. Contoh Pemakaian per Komponen

### Sidebar
```css
background: var(--surface-sidebar);
border-right: 1px solid var(--border-subtle);
color: var(--text-secondary);
```

### Active Nav Item
```css
background: var(--accent-teal-soft);
color: var(--accent-teal);
border-radius: 8px;
```

### Card
```css
background: var(--surface-card);
border: 1px solid var(--border-default);
border-radius: 12px;
color: var(--text-primary);
```

### Input Field
```css
background: var(--surface-input);
border: 1px solid var(--border-default);
color: var(--text-primary);
border-radius: 8px;
```

```css
/* Focus state */
border-color: var(--accent-blue);
box-shadow: 0 0 0 3px var(--accent-blue-glow);
```

### Tombol Primary
```css
background: var(--accent-blue);
color: #ffffff;
border-radius: 8px;
box-shadow: 0 0 16px var(--accent-blue-glow);
```

### Tombol Secondary / Ghost
```css
background: transparent;
border: 1px solid var(--border-default);
color: var(--text-secondary);
border-radius: 8px;
```

### Badge Status
```css
/* Contoh: badge "Lunas" */
background: var(--status-success-bg);
color: var(--status-success);
border-radius: 999px;
padding: 2px 10px;
font-size: 12px;
```

### Modal / Overlay
```css
/* Backdrop */
background: rgba(13, 17, 23, 0.75);

/* Modal card */
background: var(--surface-card);
border: 1px solid var(--border-default);
border-radius: 16px;
```

### Dropdown / Tooltip
```css
background: var(--surface-tooltip);
border: 1px solid var(--border-default);
border-radius: 8px;
box-shadow: 0 8px 24px rgba(0,0,0,0.4);
```

### Tabel Data
```css
/* Header row */
background: var(--surface-card-2);
color: var(--text-muted);
font-size: 12px;

/* Body row */
background: var(--surface-card);
color: var(--text-primary);
border-bottom: 1px solid var(--border-subtle);

/* Row hover */
background: var(--surface-card-2);
```

---

## 8. CSS :root Siap Pakai

Tempelkan di `resources/css/app.css` atau file CSS utama kamu.

```css
:root[data-theme="dark"],
.dark {
  /* Surfaces */
  --surface-outer:      #0d1117;
  --surface-sidebar:    #161b27;
  --surface-base:       #1c2235;
  --surface-card:       #232b3e;
  --surface-card-2:     #2a3350;
  --surface-input:      #1e2640;
  --surface-tooltip:    #2f3a56;

  /* Border */
  --border-subtle:      #2a3352;
  --border-default:     #334070;
  --border-strong:      #445088;

  /* Text */
  --text-primary:       #e8edf8;
  --text-secondary:     #8e99bf;
  --text-muted:         #4f5b7e;
  --text-disabled:      #353e58;

  /* Accent */
  --accent-blue:        #3b7ef8;
  --accent-blue-soft:   #1a3a7a;
  --accent-blue-glow:   rgba(59,126,248,0.15);
  --accent-teal:        #0fd4a0;
  --accent-teal-soft:   #0a3d30;
  --accent-purple:      #7c6ef5;
  --accent-purple-soft: #2a2060;

  /* Status */
  --status-success:     #10d078;
  --status-success-bg:  #062d1a;
  --status-warning:     #f5a623;
  --status-warning-bg:  #2d1f05;
  --status-danger:      #f04f4f;
  --status-danger-bg:   #2d0a0a;
  --status-info:        #3b7ef8;
  --status-info-bg:     #0f1f45;
  --status-neutral:     #8e99bf;
  --status-neutral-bg:  #1c2235;
}
```

---

## 9. Tailwind Config (opsional)

Tambahkan di `tailwind.config.js` jika ingin pakai sebagai class Tailwind.

```js
// tailwind.config.js
module.exports = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        surface: {
          outer:   '#0d1117',
          sidebar: '#161b27',
          base:    '#1c2235',
          card:    '#232b3e',
          card2:   '#2a3350',
          input:   '#1e2640',
          tooltip: '#2f3a56',
        },
        brand: {
          blue:   '#3b7ef8',
          teal:   '#0fd4a0',
          purple: '#7c6ef5',
        },
        status: {
          success: '#10d078',
          warning: '#f5a623',
          danger:  '#f04f4f',
          info:    '#3b7ef8',
          neutral: '#8e99bf',
        },
      },
    },
  },
}
```

---

## 10. Catatan untuk AI

Ketika meminta AI membuat atau memodifikasi UI komponen untuk 3DY App dark mode, sertakan instruksi berikut:

```
Gunakan color palette dari 3DY App Unified Dark Theme:
- Background page: #1c2235
- Sidebar: #161b27
- Card: #232b3e
- Input: #1e2640
- Border default: #334070
- Teks utama: #e8edf8
- Teks sekunder: #8e99bf
- Aksen biru (brand): #3b7ef8
- Aksen teal (active nav): #0fd4a0
- Aksen purple (icon spesial): #7c6ef5
Jangan gunakan warna di luar palette ini kecuali untuk status (success/warning/danger/info).
```

---

*File ini dibuat sebagai design system reference untuk 3DY App — Tridaya Group.*
*Update file ini setiap ada perubahan keputusan desain.*
