---
name: 3DY App Security
description: "Aturan otorisasi dan keamanan khusus 3DY App. Gunakan saat menambah atau meninjau route, controller, policy, menu, atau permission; saat muncul 403 atau menu hilang; saat menyentuh akses role dan PT (NTI/MGK/TPS/WANI); saat mengelola data sensitif admin (supplier PO, invoice, bukti bayar, finance customer), dokumen, upload, webhook, atau audit log. Termasuk pertanyaan hak akses, role, permission, dan keamanan data."
metadata:
  opencode/autoinvoke: true
---

# 3DY App Security

Fokus skill ini adalah otorisasi dan keamanan yang **spesifik 3DY**. Untuk
OWASP umum, gabungkan dengan `security-and-hardening`; untuk audit menyeluruh,
gabungkan dengan `security-audit`. `AGENTS.md` tetap menang bila benturan.

Semua path relatif terhadap root repo.

## Aturan emas

1. **Backend yang menentukan, bukan tampilan.** Menyembunyikan tombol atau
   menu tidak pernah cukup; route dan policy wajib menolak juga.
2. **Least privilege.** Aksi baca memakai `view-*`, aksi tulis memakai
   `manage-*`. Jangan menaikkan permission demi kenyamanan.
3. **Satu sumber aturan.** Menu, route, policy, dan test harus memakai
   permission yang sama. Jangan membuat daftar role duplikat di view.
4. **Default curiga.** Input, upload, webhook, dan ID dari URL dianggap tidak
   tepercaya sampai divalidasi dan diotorisasi.
5. **Jangan melemahkan guard.** `DestructiveCommandGuard` dan aturan §8
   `AGENTS.md` tidak boleh dibypass, termasuk saat memperbaiki bug.

## Model RBAC

Sumber kebenaran: `database/seeders/RoleAndPermissionSeeder.php`.

### Permission inti

- `manage-{divisi}` dan `view-{divisi}` untuk `marketing`, `sales`, `admin`,
  `technician`, `monitoring`.
- `view-customer`, `view-trash`, `view-products`, `manage-products`.
- `manage-price-requests`, `manage-sales-leads`, `manage-inside-sales`.
- `monitor-marketing`, `monitor-technical`.
- Permission baca admin yang dipisah per area sensitif:
  `view-admin-hub`, `view-supplier-pos`, `view-supplier-invoices`,
  `view-payment-proofs`, `view-goods-receipts`, `view-customer-finance`.

### Role utama

`super-admin`, `management`, `lead-marketing`, `marketing`, `sales`,
`sales-{nti|mgk|tps|wani}`, `inside-sales`, `estimator`, `admin`, `purchasing`,
`finance`, `lead-technician`, `technician`, `prakerin-technician`,
`prakerin-admin`, `ceo`.

- Satu user boleh punya beberapa role.
- `super-admin` melewati semua cek lewat `Gate::before`. Jangan menulis test
  yang hanya mengandalkan bypass ini.
- `management` adalah hub lintas divisi, bukan pengganti permission divisi.
- Role `sales-*` membatasi tulis per PT lewat `App\Support\PtAccess`.
  Tanpa role PT berarti tanpa batas (grandfather). `management`,
  `super-admin`, dan pemegang `manage-sales-leads` selalu tanpa batas.

## Lapisan penegakan

| Lapisan | Lokasi | Fungsi |
|---|---|---|
| Route middleware | `routes/web.php` | Gerbang pertama per halaman/aksi |
| Policy | `app/Policies` | Izin per record dan kepemilikan |
| Controller check | `abort_unless`, `authorize`, `PtAccess` | Aturan yang bergantung data |
| Middleware global | `EnsureUserHasRole` | User tanpa role diarahkan ke pending approval |
| Header keamanan | `SetSecurityHeaders` | `nosniff`, `SAMEORIGIN`, referrer, permissions |
| UI | Blade/sidebar | Hanya menyembunyikan, bukan mengamankan |

## Panduan memilih permission

| Kebutuhan | Permission |
|---|---|
| Membaca menu divisi | `view-{divisi}` atau `manage-{divisi}` |
| Menulis/mengubah data divisi | `manage-{divisi}` |
| Melihat customer dan trash | `view-customer`, `view-trash` |
| Katalog produk | `view-products`; ubah harga `manage-products` |
| Tiket price request | `manage-price-requests` |
| Assign lead dan hub management | `manage-sales-leads` |
| Task inside sales | `manage-inside-sales` |
| Area admin sensitif | permission granular, **bukan** `view-admin` |
| Restore/hapus permanen trash | `manage-admin` |
| Monitoring anggota | `monitor-marketing` / `monitor-technical` |

Aturan penting: `view-admin` tidak membuka supplier PO, invoice supplier,
bukti pembayaran, goods receipt, atau finance customer. Halaman itu memakai
permission granular di atas.

## Pola otorisasi per record

- **Lead**: marketing boleh kelola; sales hanya lead yang di-assign; inside
  sales hanya lead yang punya task untuknya; teknisi/admin boleh convert
  sesuai policy.
- **Follow-up dan meeting**: sales biasa hanya boleh menyentuh data miliknya.
  Gunakan pola `FollowUpOwnershipTest`.
- **Customer/Project per PT**: panggil `PtAccess::canWritePt($user, $pt)` di
  policy dan controller sebelum menulis.
- **Dokumen**: pakai `DocumentAccess::canManage()` untuk arsip/restore/hapus
  dan `DocumentCenterAccess` untuk daftar. Dokumen transaksi
  (invoice/PO/payment) tidak boleh dikelola lewat route dokumen proyek.
- **Convert quotation**: cek pemilik proposal, `manage-admin`,
  `manage-sales-leads`, atau `ceo`, lalu cek status `accepted`, PO lengkap,
  PO terverifikasi, dan belum pernah dikonversi.
- **Trash**: restore dan force delete hanya lewat `manage-admin`; pastikan
  objek turunan ikut aturan soft delete.

## Data sensitif

Perlakukan sebagai data sensitif:

- `SupplierPurchaseOrder`, `SupplierInvoice`, `SupplierPayment`,
  `GoodsReceipt`, `Payment` dan bukti transfer.
- `view-customer-finance`, invoice customer, dan nilai margin/HPP.
- Dokumen customer dan dokumen proyek di disk privat.
- Kredensial WhatsApp, Google, Reverb, dan API key di `.env`.

Aturan:

- Jangan menaruh data sensitif di URL publik, log, pesan error, atau
  notifikasi yang bisa dibaca role lain.
- Jangan menyimpan file sensitif di `public/`. Gunakan disk privat dan
  response yang sudah diotorisasi.
- Jangan mencetak token, header Authorization, atau isi dokumen di log.

## Input, upload, dan webhook

- Validasi tipe, ukuran, dan kepemilikan file sebelum menyimpan.
- Gunakan `Storage::fake()` di test untuk upload.
- Webhook WhatsApp dikecualikan dari CSRF di `bootstrap/app.php`, jadi
  verifikasi pengirim wajib tetap ada:
  - Meta: HMAC `X-Hub-Signature-256`, fail-closed bila secret kosong.
  - Green API: shared secret header.
  - Payload asing harus ditolak, bukan diproses sebagai legacy.
- Jangan menambah pengecualian CSRF baru tanpa alasan dan test.
- Untuk aksi berulang, pertimbangkan idempotensi (contoh:
  `Invoice.idempotency_key`) dan transaksi database.

## Sesi dan akun

- User tanpa role atau permission diarahkan ke pending approval, bukan diberi
  akses default.
- Route undangan, reset password, verifikasi email, dan logout punya
  pengecualian middleware yang sempit. Jangan memperlebarnya.
- Rate limit dipakai pada aksi sensitif (login, kirim AI, webhook, advanced
  settings). Pertahankan bila menambah route serupa.
- Jangan menampilkan pesan yang membocorkan keberadaan akun atau data.

## Checklist wajib saat mengubah akses

1. Tambah/ubah permission di `RoleAndPermissionSeeder`, bukan di view.
2. Pasang `permission:` di route, bukan hanya di sidebar.
3. Tambah policy/`abort_unless` untuk record yang punya pemilik atau PT.
4. Cek peran positif, peran negatif, dan akses URL langsung.
5. Cek multi-role: user `management` + `sales-*` tidak boleh terkunci PT.
6. Cek data turunan (dokumen, task, invoice) ikut aturan yang sama.
7. Jalankan test terkait dan test divisi.

Test acuan:

- `tests/Feature/DivisionAccessTest.php`
- `tests/Feature/AdminPanelSecurityTest.php`
- `tests/Feature/AdminReadPermissionTest.php`
- `tests/Feature/FollowUpOwnershipTest.php`
- `tests/Feature/DocumentCenterAccessTest.php`
- `tests/Feature/DocumentTrashAccessTest.php`

## Anti-pola

- Memakai `role:` untuk akses fitur padahal ada permission yang lebih tepat.
- Memberi `view-admin` ke halaman sensitif admin.
- Menyembunyikan menu tanpa menutup route.
- Mengecek `PtAccess` hanya di form, tidak di store/update.
- Menambah `manage-*` ke role read-only seperti `ceo` atau `prakerin-*`.
- Menonaktifkan CSRF, guard destruktif, atau verifikasi webhook demi test.
- Membuat permission baru tanpa menambahkannya ke role dan test.

## File kunci

- `database/seeders/RoleAndPermissionSeeder.php`
- `app/Support/PtAccess.php`
- `app/Support/DocumentAccess.php`
- `app/Support/DocumentCenterAccess.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Middleware/SetSecurityHeaders.php`
- `app/Policies/*`
- `routes/web.php`
- `bootstrap/app.php`
- `app/Console/DestructiveCommandGuard.php`
