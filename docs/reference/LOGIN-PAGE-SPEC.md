# 3DY App — Login Page Design Specification

## 1. Arah Utama

Login Page adalah pintu masuk ke **3DY App / Tridaya App**, aplikasi operasional internal Tridaya Group yang menggabungkan CRM + ERP.

Arah visual yang dipilih:

> **Modern Indonesian Enterprise Technology**

Gabungan:
- **A — Technical Operations**
- **B — Tridaya Identity**
- sedikit **D — Minimal Enterprise**

Karakter:
- profesional
- enterprise
- teknikal
- modern
- tenang
- premium tanpa berlebihan
- identitas Indonesia secara subtil
- nyaman dipakai karyawan setiap hari

Login bukan landing page marketing.

## 2. Konteks Produk

3DY digunakan oleh Marketing, Sales, Management, Teknisi, Admin, dan Super Admin.

Alur utama:

`Lead → Qualification → Proposal → Won → Project → Survey/Sizing → Request Harga → Instalasi → Invoice → PO → Payment → Lunas`

Modul mencakup Dashboard, Leads, WhatsApp Center, Partners, Customers, Meetings & Follow-ups, Manage Sales, Projects, Kalender, Invoices/PO/Payments, Monitoring, Trash, Admin Panel, AI Assistant & Knowledge Base, notifikasi, dan profil.

Karena ini sistem operasional internal, login harus terasa seperti **reliable enterprise operational system**, bukan aplikasi konsumen.

## 3. Struktur Layout

Gunakan **fullscreen**.

Jangan gunakan card login kecil yang mengambang di tengah atau template authentication Laravel default.

Desktop:
- area kiri sekitar 45% untuk brand/visual
- area kanan sekitar 55% untuk authentication
- jangan mengunci angka jika membuat komposisi tidak seimbang

### Area kiri — Brand / Visual

Isi:
1. Logo 3DY / Tridaya
2. Brand statement pendek, misalnya:
   - `3DY App`
   - `Operational Intelligence Platform`
3. Abstract technical illustration

Visual dapat merepresentasikan:
- system architecture
- connected nodes
- infrastructure
- data flow
- engineering drawing
- network topology
- modular systems

Visual harus menyatu dengan background, bukan menjadi image card dengan border tebal.

## 4. Identitas Indonesia / Tridaya

Gunakan **subtle geometric texture** yang terinspirasi dari:
- batik
- pola geometris Nusantara
- modular geometry
- architectural pattern

Hasil harus modern dan abstrak.

Texture:
- opacity rendah
- tidak mengganggu teks/form
- bukan wallpaper
- jangan memakai motif batik tradisional secara literal
- gunakan garis geometris, repeating pattern, mesh, layered linework, atau abstract batik-inspired geometry
- menyatu dengan warna background

## 5. Area kanan — Authentication

Area kanan lebih minimal dan lapang.

### Heading

`Welcome back`

Supporting text:

`Sign in to your 3DY account`

Hindari paragraf panjang.

### Email

Label `Email`.

Input harus jelas, nyaman, focus state terlihat, border subtle, radius moderat.

### Password

Label `Password`.

Sediakan show/hide password yang accessible.

### Secondary actions

`Remember me`

`Forgot password?`

Boleh berada pada satu baris jika layout memungkinkan.

### Primary action

`Sign in`

Boleh menggunakan arrow kecil:

`Sign in →`

Jangan menggunakan terlalu banyak icon.

## 6. Footer

Boleh menampilkan:

`© Tridaya Group`

atau:

`Tridaya Group · 3DY App`

Jangan menambahkan informasi yang belum tersedia.

## 7. Warna

**Inspect codebase terlebih dahulu.**

Jika brand colors/design tokens sudah tersedia, gunakan itu. Jangan membuat palette baru tanpa alasan.

Jika warna belum jelas, arah umum:
- deep navy / dark blue
- off-white
- muted neutral
- satu primary accent

Hindari:
- neon
- purple SaaS gradient
- rainbow gradient
- saturated cyan berlebihan
- terlalu banyak accent color

Texture harus memakai turunan warna yang sama agar menyatu.

## 8. Typography

Gunakan font yang sudah dipakai aplikasi jika tersedia.

Prioritas:
- heading kuat
- body sederhana
- label readable
- button jelas

Jangan mengganti typography seluruh aplikasi hanya untuk login.

## 9. Shape & Component

Gunakan rounded corners secara moderat.

Hindari:
- rounded ekstrem
- pill UI berlebihan
- neumorphism
- glassmorphism berlebihan
- excessive shadows

Login harus terasa modern enterprise, bukan playful SaaS.

## 10. Technical Decoration

Gunakan secara halus:
- grid
- fine lines
- nodes
- connection lines
- subtle dots
- geometric arcs
- architectural lines

Dekorasi harus memiliki hirarki visual rendah.

Jangan menutupi form atau mengurangi accessibility.

Jika ada animasi:
- subtle
- slow
- tidak distracting
- dukung `prefers-reduced-motion`

## 11. Responsive

Desktop:
`Brand / Visual | Authentication`

Tablet:
- kurangi visual
- pertahankan form nyaman

Mobile:
- jangan memaksa split screen
- prioritaskan authentication
- visual dapat menjadi background/header kecil/decorative layer
- texture tetap subtle

## 12. Accessibility

Pastikan:
- label input jelas
- keyboard navigation
- visible focus state
- contrast memadai
- error message jelas
- button loading state
- password toggle accessible
- checkbox accessible
- status tidak hanya dibedakan dengan warna
- reduced motion didukung

## 13. Interaction States

Design harus memperhitungkan:
- default
- focus
- error
- loading
- disabled

Loading dapat menggunakan:

`Signing in...`

Cegah submit berulang saat request berlangsung.

## 14. Jangan Membuat

Hindari:
- Laravel Breeze default
- centered white authentication card
- generic purple SaaS gradient
- stock photo teknisi/kantor
- ilustrasi orang dengan laptop
- cyberpunk
- hacker aesthetic
- neon
- excessive glassmorphism
- excessive blur/shadow
- terlalu banyak decorative icons
- terlalu banyak text
- marketing copy panjang
- fake dashboard/status yang tidak diperlukan

## 15. Target Visual

Perpaduan:

**Enterprise software**
+
**engineering / system integration**
+
**subtle Indonesian identity**
+
**minimal authentication UI**

Bukan:

**generic SaaS**
+
**gaming**
+
**cyber/futuristic UI**

## 16. Design Philosophy

Login harus memberi kesan:

> “Ini adalah pintu masuk ke sistem operasional perusahaan.”

Bukan:

> “Ini adalah halaman promosi perusahaan.”

User kemungkinan melihat halaman ini berkali-kali, sehingga desain harus tetap nyaman setelah ratusan penggunaan.

## 17. Sebelum Coding

Inspect terlebih dahulu:
1. Login Page saat ini
2. authentication/layout components
3. Tailwind configuration
4. global CSS
5. existing color tokens
6. typography
7. logo/assets
8. dashboard dan halaman utama
9. existing design patterns/components

Reuse design system yang sudah ada. Jangan membuat sistem styling kedua khusus login.

## 18. Definition of Done

Login berhasil jika:
- fullscreen
- satu keluarga dengan 3DY App
- tidak terlihat seperti template Laravel
- identitas Tridaya terasa
- technical visual terasa tetapi tidak berlebihan
- unsur geometris Indonesia subtil
- form tetap menjadi fokus
- responsive
- accessible
- nyaman untuk workflow harian
- authentication logic existing tidak rusak

## 19. Prinsip Akhir

> **Visual identity boleh kuat. Authentication harus tetap sederhana.**

Desain harus menunjukkan karakter 3DY tanpa membuat proses login menjadi rumit.