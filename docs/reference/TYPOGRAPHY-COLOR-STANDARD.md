# Standar Tipografi & Warna 3DY App

**Status:** Aturan resmi. Penerapan massal belum dilakukan.
**Berlaku sejak:** 2026-11-20
**Rujukan aturan kerja:** `AGENTS.md` §7.2 dan §7.5

Dokumen ini adalah sumber kebenaran untuk ukuran teks, warna teks, dan kontras.
Perubahan pada dokumen ini tidak otomatis mengubah tampilan. Penerapan ke
seluruh halaman dilakukan bertahap melalui pekerjaan terpisah.

## 1. Prinsip

1. Perubahan tipografi dan warna adalah perubahan visual. Jangan mengubah
   logika bisnis, route, API, struktur data, atau urutan field.
2. Warna didefinisikan sekali sebagai design token. Komponen memakai token,
   bukan hex/rgb acak.
3. Pertahankan tema aksen pengguna dan mode light/dark yang sudah ada.
4. Semua kombinasi tema aksen x mode harus lolos kontras WCAG AA.
5. Teks harus terbaca tanpa mengandalkan opacity atau warna samar.
6. Jika ragu, pertahankan tampilan lama dan catat keputusannya.

## 2. Kondisi Proyek Saat Ini

| Bagian | Lokasi |
|---|---|
| Token warna & mode | `resources/css/app.css` |
| Tema aksen | `resources/css/themes/accent/{ocean,purple,emerald,terracotta}.css` |
| Konfigurasi font Tailwind | `tailwind.config.js` |
| Pemuatan font | link Google Fonts / Bunny Fonts di layout |
| Mode gelap | class `.dark` pada elemen `html` |
| Tema aksen | atribut `data-theme` pada elemen `html` |

Catatan penting:

- `html` memakai `font-size: 90%`. Akibatnya `text-xs` saat ini setara
  **10,8px**, bukan 12px. Selama root belum dinormalisasi, teks minimum 12px
  harus memakai ukuran eksplisit `text-[12px]` atau token ukuran khusus.
- Font yang dipakai saat ini: **Plus Jakarta Sans** dan **Outfit**. Konfigurasi
  masih menyimpan `Exo 2` dan `Urbanist` sebagai warisan. Keduanya tidak boleh
  dipakai untuk komponen baru.
- Tema aksen yang ada: **ocean (Biru)**, **purple (Ungu)**, **emerald**, dan
  **terracotta**.
- Token lama yang perlu dipetakan: `--bg`, `--card-bg`, `--input-bg`,
  `--card-border`, `--text-primary`, `--text-secondary`, `--text-muted`.

## 3. Skala Tipografi

| Token | px | Kelas yang disarankan | Weight | Penggunaan |
|---|---:|---|---|---|
| `--fs-xs` | 12 | `text-[12px]` selama root 90% | 500 | Label mikro, badge, metadata, catatan tren |
| `--fs-sm` | 14 | `text-sm` (12,6px saat root 90%) atau `text-[14px]` | 400/500 | Isi tabel, input, tombol, label form |
| `--fs-base` | 16 | `text-base` atau `text-[16px]` | 600 | Sub-judul, header kartu |
| `--fs-lg` | 18 | `text-lg` atau `text-[18px]` | 600 | Header kartu besar |
| `--fs-xl` | 20 | `text-xl` atau `text-[20px]` | 600-700 | Judul halaman |
| `--fs-2xl` | 24 | `text-2xl` atau `text-[24px]` | 700 | Judul utama / salam dashboard |
| `--fs-kpi` | 24-30 | `text-2xl` sampai `text-3xl` | 800 | Angka KPI |

Aturan:

- Ukuran minimum absolut adalah **12px**. Dilarang `text-[10px]`,
  `text-[11px]`, `font-size: 10px`, `font-size: 11px`, atau setara.
- Label form dan metadata memakai weight **500**.
- Heading section uppercase kecil: 12-13px, weight 600,
  `letter-spacing: 0.04-0.05em`. Body tidak memakai letter-spacing tambahan.
- Angka uang dan metrik wajib memakai `tabular-nums`.
- Outfit hanya untuk judul atau penegas visual. Jangan memakai Outfit di tabel,
  form, atau tombol.
- Jangan memuat weight font yang tidak dipakai. `font-display: swap` dan
  fallback system font wajib dipertahankan.

## 4. Token Warna Target

Nilai berikut adalah target. Sebelum diterapkan, nilai harus dicek ulang
terhadap kontras dan tampilan yang ada. Jangan mengganti token massal tanpa
pekerjaan terpisah.

### 4.1 Dark mode

| Token | Nilai | Fungsi |
|---|---|---|
| `--bg-page` | `#0B1220` | Latar halaman |
| `--surface` | `#111B2E` | Kartu, panel |
| `--surface-elevated` | `#172338` | Input, hover row, popover |
| `--border` | `#2A3750` | Garis kartu dan pemisah |
| `--text-primary` | `#F1F5F9` | Judul, nilai penting |
| `--text-secondary` | `#CBD5E1` | Label, isi bantu, deskripsi |
| `--text-tertiary` | `#94A3B8` | Metadata, hint |
| `--text-disabled` | `#64748B` | Hanya disabled/dekoratif |
| `--info` | `#60A5FA` | Informasi dan link netral |
| `--success` | `#4ADE80` | Sukses, tren naik, margin sehat |
| `--warning` | `#FBBF24` | Proses, tertunda, margin waspada |
| `--danger` | `#F87171` | Bahaya, hapus, margin rugi, expired |

### 4.2 Light mode

| Token | Nilai | Fungsi |
|---|---|---|
| `--bg-page` | `#F8FAFC` | Latar halaman |
| `--surface` | `#FFFFFF` | Kartu, panel |
| `--surface-elevated` | `#F1F5F9` | Input, hover row |
| `--border` | `#E2E8F0` | Garis |
| `--text-primary` | `#0F172A` | Judul, teks penting |
| `--text-secondary` | `#334155` | Teks isi dan tabel |
| `--text-tertiary` | `#64748B` | Label pendukung dan meta |
| `--text-disabled` | `#94A3B8` | Disabled |
| `--info` | `#1D4ED8` | Informasi |
| `--success` | `#15803D` | Sukses |
| `--warning` | `#B45309` | Proses dan tertunda |
| `--danger` | `#B91C1C` | Bahaya |

### 4.3 Warna aksen

Setiap tema aksen memakai dua peran:

- `--accent-text`: untuk teks, link, dan ikon di atas permukaan.
- `--accent-solid`: untuk latar tombol primer dengan teks putih.

| Tema | Mode | `--accent-text` | `--accent-solid` |
|---|---|---|---|
| Terracotta | Dark | `#FB923C` | `#C2410C` |
| Terracotta | Light | `#C2410C` | `#C2410C` |
| Ungu | Dark | `#A78BFA` | `#7C3AED` |
| Ungu | Light | `#7C3AED` | `#7C3AED` |
| Emerald | Dark | `#34D399` | `#047857` |
| Emerald | Light | `#047857` | `#047857` |
| Biru (ocean/default) | Dark | `#60A5FA` | `#2563EB` |
| Biru (ocean/default) | Light | `#1D4ED8` | `#2563EB` |

Warna status (`success`, `warning`, `danger`, `info`) tidak mengikuti tema
aksen.

### 4.4 Pemetaan token lama ke token baru

| Token lama | Token baru | Catatan |
|---|---|---|
| `--bg` | `--bg-page` | Nilai target berbeda dari nilai saat ini |
| `--card-bg` | `--surface` | Kartu dan panel |
| `--input-bg` | `--surface-elevated` | Input dan hover row |
| `--card-border` | `--border` | Garis |
| `--text-primary` | `--text-primary` | Nilai target disesuaikan |
| `--text-secondary` | `--text-secondary` | Nilai target lebih gelap di light dan lebih terang di dark |
| `--text-muted` | `--text-tertiary` | Jangan dipakai untuk informasi kritis |

## 5. Aturan Pemakaian Warna Teks

| Konteks | Token |
|---|---|
| Judul halaman, judul kartu, nama item, angka penting | `--text-primary` |
| Label form, isi tabel, deskripsi, tombol sekunder, menu navigasi | `--text-secondary` |
| Metadata, timestamp, hint, helper text | `--text-tertiary` |
| Placeholder input | `--text-tertiary`, weight 400 |
| Disabled | `--text-disabled` dan `aria-disabled` |
| Link dan item aktif | `--accent-text` |
| Tombol primer | latar `--accent-solid`, teks putih |
| Status | token status + ikon atau label teks |

Larangan:

- Dilarang memakai opacity atau alpha pada teks untuk membuatnya redup.
  Ganti dengan token tersier.
- Dilarang memakai `--text-tertiary` atau `--text-disabled` untuk informasi
  kritis seperti tanggal kedaluwarsa, margin, atau pesan error.
- Dilarang memakai hitam/putih murni sebagai teks di atas latar biasa. Putih
  hanya boleh di atas tombol atau permukaan berwarna yang sudah lolos kontras.
- Dilarang memakai kelas warna mentah seperti `text-slate-500`,
  `text-gray-400`, atau `text-white/60` untuk teks pada pekerjaan baru.

## 6. Status dan Informasi Kritis

| Kasus | Tampilan |
|---|---|
| Sukses, tren naik, margin sehat | Warna success + ikon centang/panah |
| Proses, tertunda, margin waspada, harga hampir kedaluwarsa | Warna warning + ikon peringatan |
| Bahaya, hapus, margin rendah/negatif, expired | Warna danger + ikon bahaya |
| Informasi | Warna info + ikon informasi |

Badge status memakai latar tint dari token status, bukan opacity pada teks.
Teks badge harus memakai warna yang lolos kontras terhadap tint tersebut.
Status tidak boleh dibedakan hanya dengan warna.

## 7. Aturan per Area

- **Sidebar/topbar:** item menu 14px, weight 500-600, teks sekunder. Item aktif
  memakai teks primer dan indikator accent. Judul grup 12px uppercase weight
  600, teks tersier.
- **Tabel:** header 12-13px weight 600, teks tersier atau sekunder. Isi 14px
  teks sekunder. Angka `tabular-nums` rata kanan. Nilai kunci teks primer.
- **Form:** label 12-13px weight 500 teks sekunder. Input 14px teks primer.
  Placeholder teks tersier. Helper 12px teks tersier. Error 12px danger +
  ikon.
- **Dashboard KPI:** angka 24-30px weight 800 + tabular, teks primer. Judul
  kartu 16px weight 600. Catatan tren 12px dengan warna status + ikon.
- **Tombol:** teks 14px weight 600. Primer memakai accent solid + teks putih.
  Sekunder memakai border + teks sekunder/primer. Focus ring wajib terlihat.
- **Modal/toast/popover:** permukaan elevated. Teks mengikuti tingkat di atas.
- **Chart:** label dan legend minimal 12px, teks sekunder. Jangan mengandalkan
  warna saja untuk membedakan seri.

## 8. Audit Sebelum dan Sesudah

Jalankan dari root repo:

```bash
# Ukuran font di bawah 12px
grep -rnE "text-\[(9|10|11)(\.[0-9]+)?px\]" resources/views resources/js
grep -rnE "text-\[0?\.[5-7][0-9]*rem\]" resources/views resources/js
grep -rnE "font-size:\s*(9|10|11)(\.[0-9]+)?px" resources/css resources/views
grep -rnE "font-size:\s*0?\.[5-7][0-9]*rem" resources/css resources/views

# Warna mentah di luar file token
grep -rnE "#[0-9a-fA-F]{3,8}\b" resources/views resources/js | grep -v "resources/css"
grep -rnE "(rgb|rgba|hsl|hsla)\(" resources/views resources/js

# Warna teks Tailwind mentah
grep -rnE "text-(slate|gray|zinc|neutral|stone)-[0-9]{2,3}" resources/views resources/js
grep -rnE "text-(white|black)(/[0-9]+)?\b" resources/views resources/js

# Opacity/alpha pada teks
grep -rnE "text-[a-z]+(-[0-9]+)?/[0-9]+" resources/views resources/js
grep -rnE "\bopacity-[0-9]+\b" resources/views resources/js
```

Target akhir:

- Ukuran teks di bawah 12px: 0.
- Warna mentah di komponen/halaman: 0, kecuali file token dan pengecualian
  yang dicatat.
- Warna teks mentah dan opacity pada teks: 0.
- Font hanya Plus Jakarta Sans, Outfit, dan fallback.

## 9. Verifikasi Kontras

Buat test otomatis yang memuat nilai token untuk setiap tema aksen x mode dan
menghitung rasio kontras WCAG. Ambang:

| Pasangan | Ambang |
|---|---|
| Teks primer/sekunder/tersier di atas bg-page, surface, surface-elevated | minimal 4,5:1 |
| Teks accent di atas surface dan bg-page | minimal 4,5:1 |
| Putih di atas accent-solid | minimal 4,5:1 |
| Success/warning/danger/info di atas surface | minimal 4,5:1 |
| Border/focus ring penting | minimal 3:1 |
| Teks disabled | dikecualikan |

Jika ada yang gagal, sesuaikan token. Jangan menurunkan ambang.

## 10. Larangan

- Mengganti font family atau menambah font ketiga.
- Menambah palet warna baru tanpa alasan kontras.
- Menurunkan ukuran teks di bawah 12px agar muat.
- Memakai opacity pada teks untuk efek redup.
- Mengandalkan warna saja untuk status.
- Mengubah teks, label, urutan field, atau layout bisnis di luar kebutuhan
  ukuran dan warna.
- Menyentuh logika, API, atau data.

Jika label 12px menyebabkan overflow, perbaiki layout, wrap, atau lebar kolom.
Jangan mengecilkan font.

## 11. Tahapan Penerapan (Belum Dikerjakan)

1. Audit jumlah temuan dan pemetaan token lama ke token baru.
2. Rapikan token warna dan tipografi di file tema.
3. Migrasi komponen bersama: tombol, input, label, badge, tabel, kartu,
   modal, toast, sidebar, topbar, tab, dropdown.
4. Migrasi halaman per divisi.
5. Jalankan audit, test kontras, dan pengecekan visual light/dark.
6. Build dari `/var/www/3dyapp` dan perbarui catatan hasil.

Penerapan massal menunggu perintah kerja terpisah. Untuk pekerjaan UI baru,
aturan ini berlaku sejak file tersebut disentuh.

## 12. Catatan Agent

- Dokumen ini belum mengubah `app.css`, tema aksen, komponen, atau halaman.
- Nilai target di atas belum diverifikasi di layar. Verifikasi dilakukan saat
  pekerjaan penerapan dimulai.
- Root font-size 90% adalah keputusan lama. Opsi yang dipertahankan: memakai
  ukuran eksplisit 12px sampai ada persetujuan untuk menormalkan root.
- Tema `ocean` dipetakan sebagai Biru, `purple` sebagai Ungu. Nama tema tidak
  diubah pada tahap aturan ini.
