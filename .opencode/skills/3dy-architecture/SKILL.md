---
name: 3DY App Architecture
description: "Peta arsitektur khusus 3DY App (Laravel 13, Blade, Livewire 4, Spatie Permission, Tailwind/Vite). Gunakan saat menambah atau mengubah menu/modul, melacak alur Lead ke Quotation ke Project ke Invoice, memutuskan penempatan kode (Controller, Service, Policy, Livewire, Support), atau bekerja lintas divisi marketing/sales/admin/teknisi, multi-PT, Document Center, notifikasi, dan repo multi-worktree. Termasuk pertanyaan struktur folder, alur data, dan di mana logika bisnis harus ditulis."
metadata:
  opencode/autoinvoke: true
---

# 3DY App Architecture

Skill ini adalah peta kerja proyek. Aturan di `AGENTS.md` tetap menang bila
terjadi benturan. Semua path di bawah relatif terhadap root repo
(`git rev-parse --show-toplevel`), bukan folder skill ini.

## Kapan dipakai

- Menambah menu, halaman, modul, atau route baru.
- Melacak alur data dari Lead sampai Invoice/Payment.
- Memutuskan kode ditulis di Controller, Service, Model, Policy, Livewire,
  Observer, atau Support.
- Menyentuh area lintas divisi, multi-PT, Document Center, notifikasi, atau
  AI assistant.
- Bekerja di salah satu worktree divisi dan perlu tahu batas kepemilikan file.
- Membuat migration, seeder, atau perubahan struktur database.

## Kapan tidak dipakai

- Menulis test harian: pakai `3dy-testing`.
- Mengubah hak akses: pakai `3dy-security`.
- Mengubah aturan bisnis Lead/Quotation/Project: pakai `3dy-crm-workflow`.
- Logika Laravel umum yang tidak menyentuh struktur 3DY: cukup
  `laravel-development`.

## Fakta stack

| Bagian | Teknologi |
|---|---|
| Bahasa | PHP 8.3 |
| Framework | Laravel 13.8 |
| Tampilan | Blade, Alpine.js, Tailwind CSS 3, Vite 8 |
| Komponen stateful | Livewire 4.3 |
| Island React | Notification Center, Toast, Glide Select (`resources/js`) |
| Authorization | `spatie/laravel-permission` 8.3 |
| Realtime | Laravel Reverb + Echo |
| Database produksi | PostgreSQL (`tridayadb`) |
| Database test | SQLite in-memory (`phpunit.xml`) |
| Queue/cache | Queue `database`, cache `file` |
| Skala | sekitar 444 route, 60+ model, 177 migration, 142 file test |

## Peta repo dan worktree

- `/var/www/3dyapp` adalah worktree `main` dan satu-satunya yang melayani
  website.
- Setiap divisi punya worktree dan branch sendiri (lihat tabel §1 `AGENTS.md`).
- Satu worktree = satu branch. Dilarang pindah branch di worktree sesi lain.
- `npm run build` hanya dari `main`, bukan dari worktree divisi.
- `.agents/` bersifat lokal dan di-gitignore. Skill proyek yang dibagikan
  disimpan di `.opencode/skills/`.
- Perubahan struktur database harus lewat `php artisan make:migration`; jangan
  mengedit migration yang sudah merge ke `main` (§15).

## Alur request

1. Route aktif ada di `routes/web.php` dan `routes/auth.php`.
2. `routes/marketing.php`, `routes/admin.php`, `routes/teknisi.php`,
   `routes/monitoring.php`, dan `routes/admin-panel.php` saat ini **tidak
   di-require**. Jangan menganggap file itu sumber route aktif. Cek dengan
   `php artisan route:list --path=<awalan>`.
3. Middleware web global: `SetLocale`, `SetSecurityHeaders`,
   `EnsureUserHasRole`.
4. Route memakai `auth`, `verified`, lalu `permission:` atau `role:` dari
   Spatie.
5. Controller memvalidasi input, memanggil Service/Model, lalu mengembalikan
   Blade atau response.
6. Policy menangani izin per record. `Gate::before` memberi bypass hanya untuk
   `super-admin`.
7. Observer menangani efek samping lifecycle (`CustomerObserver`,
   `ProjectObserver`).
8. Notifikasi dikirim lewat `app/Notifications` (sebagian queued ke tabel
   `jobs`).

## Tanggung jawab folder

| Path | Isi | Aturan |
|---|---|---|
| `app/Http/Controllers` | Orkestrasi HTTP | Tipis: validasi, otorisasi, panggil service |
| `app/Http/Middleware` | Locale, header keamanan, status role | Jangan tambah logika bisnis |
| `app/Livewire` | Komponen stateful | Jangan duplikasi aturan service |
| `app/Services` | Alur bisnis multi-langkah dan transaksi | Tempat utama aturan bisnis |
| `app/Models` | Relasi, konstanta status, helper domain kecil | Jangan taruh request/response |
| `app/Policies` | Izin per record | Selaras dengan menu dan route |
| `app/Support` | Helper lintas modul | Contoh: `PtAccess`, `DocumentAccess`, `SparklineBuilder`, `whereLike` |
| `app/Observers` | Efek samping create/update/delete | Hati-hati rekursi dan `saveQuietly` |
| `app/Notifications` | Notifikasi user | Sertakan data minimum, jangan rahasia |
| `app/Enums` | Enum tetap | Contoh: `App\Enums\ProjectStatus` |
| `app/Rules` | Validasi kustom | Dipakai lewat `$request->validate` |
| `routes/web.php` | Route + peta permission | Sumber utama route aktif |
| `database/seeders/RoleAndPermissionSeeder.php` | Sumber kebenaran role dan permission | Perubahan role wajib lewat sini |
| `resources/views` | Blade per menu | Ikuti komponen dan §7 `AGENTS.md` |
| `resources/js` | Island React dan script halaman | Daftarkan lewat Vite, bukan `<script>` liar |

## Peta domain per divisi

| Area | Model/Controller utama | Catatan |
|---|---|---|
| Marketing | `Lead`, `Partner`, `LeadActivity`, `LeadTask`, `WhatsAppCenterController` | Lead masuk, tugas, follow-up awal, WhatsApp |
| Sales | `FollowUp`, `SalesSchedule`, `Proposal` (quotation), `ProposalItem`, `PriceRequest`, `SalesTarget` | Pipeline, meeting, penawaran, request harga |
| Admin | `Invoice`, `Payment`, `PurchaseOrder`, `SupplierPurchaseOrder`, `SupplierInvoice`, `GoodsDelivery`, `GoodsReceipt`, `BastController` | PO customer, procurement, invoice, pembayaran, BAST |
| Teknisi | `Project`, `ProjectTask`, `ProjectSupport`, `TechnicianSchedule`, `Survey`, `SizingProject`, `Instalasi`, `TechnicalRequest` | Eksekusi proyek dan dokumen teknis |
| Lintas divisi | `Customer`, `CustomerContact`, Document Center, notifikasi, AI assistant | Jangan pecah logika ke salinan per divisi |
| Monitoring/management | `MonitoringController`, `ManageSalesController`, `AdminPanelController` | Recap, assign lead, user/role admin panel |

Alur bisnis lengkap ada di skill `3dy-crm-workflow`. Jangan menebak status
baru dari nama menu; baca konstanta model dan test terkait.

## Aturan penempatan kode

1. **Controller tipis.** Validasi dan otorisasi di controller, aturan
   multi-tabel di `app/Services`.
2. **Satu aturan, satu tempat.** Jangan menulis ulang pengecekan PT, akses
   dokumen, atau status di Blade dan controller sekaligus.
3. **Gunakan helper yang ada.** `PtAccess` untuk batas PT, `DocumentAccess`
   dan `DocumentCenterAccess` untuk dokumen, `SparklineBuilder` untuk grafik
   KPI, `whereLike` untuk pencarian.
4. **Status memakai konstanta.** Contoh: `Proposal::STATUSES`,
   `Invoice::STATUSES`, `Lead::PT_GROUPS`, `App\Enums\ProjectStatus`.
   Jangan hardcode string status di query atau view.
5. **Label UI lewat terjemahan.** `__('...')` dengan default Inggris;
   terjemahan Indonesia di `lang/id.json` atau `lang/id/`.
6. **Komponen UI dipakai ulang.** Tombol wajib ikut §7 `AGENTS.md`
   (`x-icon-button`, `x-flow-button`, `x-back-button`, `rounded-xl`).
7. **Migration baru, bukan edit lama.** Sertakan rollback yang benar dan
   jangan pernah memakai perintah destruktif.
8. **Jangan menambah dependency** tanpa persetujuan user.

## Konvensi data penting

- `Customer.status`: `lead` (belum jadi customer) atau `deal` (sudah deal).
  Customer berstatus `lead` tidak boleh mengotori menu Customer.
- `Lead::PT_GROUPS`: `NTI`, `MGK`, `TPS`, `WANI`. Role `sales-<pt>` membatasi
  tulis lewat `PtAccess`.
- `Lead.status`: `cool`, `warm`, `hot`, `won`, `lost`. `cool` ditampilkan
  sebagai "New" di area sales.
- `Proposal` adalah tabel quotation. Status: `draft`, `pending_technical`,
  `ready`, `sent`, `viewed`, `revision`, `accepted`, `rejected`, `expired`,
  `cancelled`.
- `Project` menyimpan snapshot quotation, nomor PO, deadline, progres, dan
  kolom BAST. Status proyek memakai tabel `project_statuses`; enum
  `App\Enums\ProjectStatus` hanya untuk label/warna.
- `Invoice.status`: `unpaid`, `partial`, `paid`, `cancelled`.
  `Invoice.invoice_type`: `dp`, `termin`, `pelunasan`, `full`.
- Soft delete dipakai di banyak model. Restore customer ikut merestore
  project, kontak, dan dokumen. Force delete tidak boleh membangkitkan data.
- Uang memakai cast decimal; tanggal memakai cast date/datetime. Jangan
  menghitung uang dengan float di tampilan.

## Checklist menambah menu/modul

1. Tentukan divisi, aktor, dan permission yang berhak.
2. Tambah route di `routes/web.php` di grup permission yang tepat.
3. Tambah controller/action, validasi, dan policy bila menyentuh record.
4. Taruh aturan bisnis di Service atau Model, bukan di Blade.
5. Tambah view/komponen dan entri sidebar untuk role yang benar.
6. Tambah migration lewat artisan bila perlu, lalu `php artisan migrate`.
7. Tambah test positif dan negatif (lihat `3dy-testing`).
8. Cek `php artisan route:list`, light/dark mode, dan mobile.
9. Bila menambah kelas Tailwind, `npm run build` dari `main`.

## Verifikasi cepat

```bash
php artisan route:list --path=<awalan>
php artisan test --filter=<TestName>
git status --short
```

## Anti-pola

- Mengedit `routes/marketing.php` atau file route divisi lain yang tidak
  di-require, lalu mengira route berubah.
- Menaruh query dan aturan status panjang di Blade.
- Menyalin pengecekan PT hanya di tampilan tanpa `PtAccess` di backend.
- Memakai `view-admin` untuk membuka data sensitif admin.
- Menambah tabel/kolom tanpa migration atau mengedit migration lama.
- Menjalankan `npm run build` dari worktree divisi.
- Menyalin skill pihak ketiga ke dalam repo atau mengubah isinya.

## File kunci

- `bootstrap/app.php`
- `routes/web.php`, `routes/auth.php`
- `app/Providers/AppServiceProvider.php`
- `database/seeders/RoleAndPermissionSeeder.php`
- `app/Support/PtAccess.php`, `app/Support/DocumentAccess.php`
- `app/Observers/CustomerObserver.php`, `app/Observers/ProjectObserver.php`
- `phpunit.xml`, `tests/TestCase.php`
- `AGENTS.md` (§1, §5, §7, §7.1, §8, §12, §15)
