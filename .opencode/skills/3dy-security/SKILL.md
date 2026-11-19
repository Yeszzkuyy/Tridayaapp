---
name: 3DY App Security
description: "Aturan otorisasi dan keamanan khusus 3DY App. Gunakan saat menambah, mengubah, atau meninjau route, controller, policy, menu, permission, role, atau akses per PT (NTI/MGK/TPS/WANI); saat muncul 403, 404 janggal, atau menu hilang; saat menyentuh data sensitif admin (supplier PO, invoice, bukti bayar, goods receipt, finance customer, margin/HPP); saat membuat atau mengubah upload, download dokumen, export/PDF, webhook, broadcast/channel, notifikasi, fitur AI, atau audit log. Juga untuk pertanyaan hak akses, 'siapa boleh lihat/ubah X', review keamanan sebelum merge, dan perbaikan bug yang berisiko membuka akses."
metadata:
  opencode/autoinvoke: true
---

# 3DY App Security

Skill ini mengatur otorisasi dan keamanan yang **spesifik 3DY**. Untuk OWASP
umum, gabungkan dengan `security-and-hardening`; untuk audit menyeluruh,
gabungkan dengan `security-audit`. Bila ada benturan, `AGENTS.md` menang.

Semua path relatif terhadap root repo. Tanda **(verifikasi)** berarti lokasi
atau nama belum dipastikan; cek dulu di repo, jangan ditebak.

## 1. Aturan emas

1. **Backend yang menentukan, bukan tampilan.** Menyembunyikan tombol atau
   menu tidak pernah cukup. Route, policy, dan query harus menolak sendiri.
2. **Tolak secara default (fail-closed).** Bila permission, role, PT, atau
   secret tidak dikenali atau kosong, hasilnya menolak, bukan mengizinkan.
3. **Least privilege.** Baca memakai `view-*`, tulis memakai `manage-*`.
   Jangan menaikkan permission demi kenyamanan atau agar test lolos.
4. **Satu sumber aturan.** Menu, route, policy, dan test memakai permission
   yang sama. Jangan membuat daftar role duplikat di view atau controller.
5. **Semua input tidak tepercaya.** ID dari URL, body, header, file, webhook,
   dan hasil AI dianggap tidak tepercaya sampai divalidasi dan diotorisasi.
6. **Jangan melemahkan pengaman.** `DestructiveCommandGuard`, aturan §8
   `AGENTS.md`, CSRF, dan verifikasi webhook tidak boleh dibypass, termasuk
   saat memperbaiki bug atau menulis test.
7. **Jangan membocorkan lewat sisi samping.** Pesan error, log, notifikasi,
   nama file, response 403 vs 404, dan hitungan total juga bisa membocorkan data.
8. **Ragu berarti tanya.** Jika aturan akses sebuah fitur tidak jelas, tanyakan
   ke pemilik produk. Jangan memilih yang paling longgar.

## 2. Model RBAC

Sumber kebenaran: `database/seeders/RoleAndPermissionSeeder.php`.

### Permission

- `manage-{divisi}` dan `view-{divisi}` untuk `marketing`, `sales`, `admin`,
  `technician`, `monitoring`.
- `view-customer`, `view-trash`, `view-products`, `manage-products`.
- `manage-price-requests`, `manage-sales-leads`, `manage-inside-sales`.
- `monitor-marketing`, `monitor-technical`.
- Baca area admin sensitif (granular, terpisah dari `view-admin`):
  `view-admin-hub`, `view-supplier-pos`, `view-supplier-invoices`,
  `view-payment-proofs`, `view-goods-receipts`, `view-customer-finance`.

### Role

`super-admin`, `management`, `lead-marketing`, `marketing`, `sales`,
`sales-{nti|mgk|tps|wani}`, `inside-sales`, `estimator`, `admin`,
`purchasing`, `finance`, `lead-technician`, `technician`,
`prakerin-technician`, `prakerin-admin`, `ceo`.

- Satu user boleh punya beberapa role; izin efektif adalah gabungannya.
- `super-admin` melewati semua cek lewat `Gate::before`. Test tidak boleh
  hanya mengandalkan bypass ini; uji juga dengan role non-super-admin.
- `management` adalah hub lintas divisi, bukan pengganti permission divisi.
- `sales-*` membatasi **tulis** per PT lewat `App\Support\PtAccess`. Tanpa
  role PT berarti tanpa batas (grandfather). `management`, `super-admin`, dan
  pemegang `manage-sales-leads` selalu tanpa batas.
- `ceo`, `prakerin-technician`, `prakerin-admin` bersifat baca saja. Jangan
  memberi `manage-*` kepada mereka.

## 3. Lapisan penegakan (defense in depth)

| Lapisan | Lokasi | Fungsi |
|---|---|---|
| Route middleware | `routes/web.php` | Gerbang pertama per halaman/aksi |
| Policy | `app/Policies` | Izin per record dan kepemilikan |
| Controller check | `authorize`, `abort_unless`, `PtAccess` | Aturan yang bergantung data |
| Query scoping | model/scope/controller | Daftar hanya berisi record yang boleh dilihat |
| Middleware global | `EnsureUserHasRole` | User tanpa role diarahkan ke pending approval |
| Header keamanan | `SetSecurityHeaders` | `nosniff`, `SAMEORIGIN`, referrer, permissions |
| UI | Blade/sidebar | Hanya menyembunyikan, bukan mengamankan |

Sebuah fitur dianggap aman bila **minimal dua lapisan** menolak akses yang
tidak sah (misalnya route + policy), bukan hanya satu.

## 4. Alur kerja saat menyentuh akses

Ikuti urutan ini setiap kali menambah atau mengubah fitur yang punya data atau
aksi berpemilik:

1. **Petakan aktor dan aset.** Siapa yang boleh baca, tulis, hapus? Data apa
   yang terlibat (sensitif atau tidak)? Apakah dibatasi per PT atau per pemilik?
2. **Pilih permission** memakai tabel di bagian 5. Bila belum ada yang cocok,
   buat permission baru di seeder, jangan memakai `role:` atau `view-admin`.
3. **Pasang di semua lapisan**: route `permission:`, policy/`authorize`,
   scoping query, lalu UI terakhir.
4. **Tulis test** (bagian 11): positif, negatif, URL langsung, multi-role.
5. **Jalankan test** terkait dan test divisi, lalu periksa diff tidak
   menyentuh guard yang dilarang.
6. **Laporkan** di ringkasan: permission yang dipakai, lapisan yang dipasang,
   dan hal yang belum bisa diverifikasi.

## 5. Memilih permission

| Kebutuhan | Permission |
|---|---|
| Membaca menu divisi | `view-{divisi}` atau `manage-{divisi}` |
| Menulis/mengubah data divisi | `manage-{divisi}` |
| Melihat customer dan trash | `view-customer`, `view-trash` |
| Katalog produk | `view-products`; ubah harga `manage-products` |
| Tiket price request | `manage-price-requests` |
| Assign lead dan hub management | `manage-sales-leads` |
| Task inside sales | `manage-inside-sales` |
| Supplier PO | `view-supplier-pos` |
| Invoice supplier | `view-supplier-invoices` |
| Bukti pembayaran | `view-payment-proofs` |
| Goods receipt | `view-goods-receipts` |
| Finance customer, margin, HPP | `view-customer-finance` |
| Hub admin (hanya halaman hub) | `view-admin-hub` |
| Restore/hapus permanen trash | `manage-admin` |
| Monitoring anggota | `monitor-marketing` / `monitor-technical` |

`view-admin` **tidak** membuka supplier PO, invoice supplier, bukti
pembayaran, goods receipt, atau finance customer. Halaman itu wajib memakai
permission granular di atas, termasuk endpoint turunannya (export, download,
API/AJAX, broadcast).

## 6. Otorisasi per record dan per PT

- **Lead**: marketing boleh kelola; sales hanya lead yang di-assign; inside
  sales hanya lead yang punya task untuknya; teknisi/admin boleh convert
  sesuai policy.
- **Follow-up dan meeting**: sales biasa hanya menyentuh data miliknya. Pakai
  pola `FollowUpOwnershipTest`.
- **Customer/Project per PT**: panggil `PtAccess::canWritePt($user, $pt)` di
  policy **dan** di `store`/`update`/`destroy`, bukan hanya di form. Nilai
  `$pt` harus diambil dari record di database, bukan dari input request.
- **Dokumen**: `DocumentAccess::canManage()` untuk arsip/restore/hapus,
  `DocumentCenterAccess` untuk daftar. Dokumen transaksi (invoice/PO/payment)
  tidak boleh dikelola lewat route dokumen proyek.
- **Convert quotation**: cek pemilik proposal, `manage-admin`,
  `manage-sales-leads`, atau `ceo`; lalu status `accepted`, PO lengkap, PO
  terverifikasi, dan belum pernah dikonversi.
- **Trash**: restore dan force delete hanya lewat `manage-admin`; pastikan
  objek turunan ikut aturan soft delete.

### Pola kode yang benar

```php
// Route: gerbang pertama, permission granular
Route::get('/supplier-pos', [SupplierPoController::class, 'index'])
    ->middleware('permission:view-supplier-pos');

// Controller: otorisasi per record, ambil record lewat route-model binding
public function update(UpdateXRequest $request, X $x)
{
    $this->authorize('update', $x);                 // policy
    abort_unless(PtAccess::canWritePt($request->user(), $x->pt), 403);

    $x->update($request->validated());              // bukan $request->all()
}

// Daftar: batasi query, jangan hanya menyembunyikan di view
$items = X::query()
    ->when(! $user->can('manage-sales-leads'), fn ($q) => $q->visibleTo($user))
    ->paginate();
```

## 7. Data sensitif

Perlakukan sebagai sensitif:

- `SupplierPurchaseOrder`, `SupplierInvoice`, `SupplierPayment`,
  `GoodsReceipt`, `Payment`, dan bukti transfer.
- Finance customer, invoice customer, nilai margin/HPP.
- Dokumen customer dan dokumen proyek di disk privat.
- Kredensial WhatsApp, Google, Reverb, dan API key di `.env`.

Aturan:

- Jangan menaruh data sensitif di URL publik, query string, log, pesan error,
  atau notifikasi yang bisa dibaca role lain.
- Jangan menyimpan file sensitif di `public/`. Gunakan disk privat dan
  response yang sudah diotorisasi (cek policy sebelum `Storage::download` /
  `response()->file`). Hindari URL tertebak; jangan pernah mengandalkan
  "tidak ada yang tahu URL-nya".
- Jangan mencetak token, header `Authorization`, isi dokumen, atau nominal
  finansial di log.
- Export (Excel/PDF/CSV) dan laporan memakai permission yang **sama** dengan
  halaman sumbernya, dan hanya memuat kolom yang boleh dilihat peminta.
- Jangan mengirim data sensitif ke layanan AI atau pihak ketiga tanpa
  keputusan eksplisit; masker atau buang field finansial dari konteks.
- Kolom sensitif jangan ikut `$fillable` bila tidak perlu, dan sembunyikan dari
  serialisasi (`$hidden`) agar tidak bocor lewat JSON/API.

## 8. Input, upload, download

- Validasi tipe (berdasarkan isi/MIME, bukan hanya ekstensi), ukuran, dan
  kepemilikan sebelum menyimpan. Simpan dengan nama acak, bukan nama asli.
- Jangan pernah memakai nama file dari user sebagai path; cegah path traversal.
- Gunakan `$request->validated()` atau `only([...])`. Jangan
  `Model::create($request->all())` (mass assignment).
- ID dari URL hanya berarti "record yang diminta", bukan "record yang boleh
  diakses". Setiap akses by-ID wajib melewati policy (cegah IDOR).
- Gunakan `Storage::fake()` di test untuk upload.

## 9. Webhook, realtime, dan integrasi

- Webhook WhatsApp dikecualikan dari CSRF di `bootstrap/app.php`, sehingga
  verifikasi pengirim wajib tetap ada:
  - Meta: HMAC `X-Hub-Signature-256`, bandingkan dengan `hash_equals`,
    **fail-closed** bila secret kosong.
  - Green API: shared secret header, juga dibandingkan konstan-waktu.
  - Payload asing ditolak, bukan diproses sebagai legacy.
- Jangan menambah pengecualian CSRF baru tanpa alasan tertulis dan test.
- Reverb/broadcast: setiap private/presence channel harus punya otorisasi di
  `routes/channels.php` **(verifikasi)** yang memakai permission yang sama
  dengan halaman sumbernya. Payload event tidak boleh memuat data sensitif
  yang lebih luas daripada yang boleh dilihat penerima.
- Aksi berulang (webhook yang dikirim ulang, submit ganda) harus idempoten dan
  dibungkus transaksi database (contoh: `Invoice.idempotency_key`).
- Fitur AI: perlakukan keluaran model sebagai input tidak tepercaya. Jangan
  mengeksekusi perintah, query, atau perubahan akses langsung dari keluaran AI
  tanpa validasi dan otorisasi user yang meminta.

## 10. Sesi, akun, dan jejak audit

- User tanpa role atau permission diarahkan ke pending approval, bukan diberi
  akses default.
- Route undangan, reset password, verifikasi email, dan logout punya
  pengecualian middleware yang sempit. Jangan memperlebarnya.
- Rate limit dipakai pada aksi sensitif (login, kirim AI, webhook, advanced
  settings). Pertahankan bila menambah route serupa.
- Jangan menampilkan pesan yang membocorkan keberadaan akun atau data
  (login, reset password, undangan).
- Perubahan role/permission, akses data finansial, restore/force delete, dan
  konversi quotation harus meninggalkan jejak audit (siapa, kapan, apa) di
  mekanisme audit log yang sudah ada **(verifikasi)**. Jangan memasukkan isi
  dokumen atau nilai rahasia ke dalam entri audit.
- Perubahan di seeder yang dijalankan ke data hidup harus idempoten
  (`firstOrCreate`/`syncPermissions`), dan cache permission perlu di-reset
  setelahnya **(verifikasi paket yang dipakai)**.

## 11. Checklist wajib saat mengubah akses

1. Tambah/ubah permission di `RoleAndPermissionSeeder`, bukan di view.
2. Pasang `permission:` di route, bukan hanya di sidebar.
3. Tambah policy/`authorize`/`abort_unless` untuk record yang punya pemilik
   atau PT; scoping query untuk daftar.
4. Uji **peran positif**, **peran negatif**, dan **akses URL langsung**
   (tanpa lewat menu), termasuk endpoint export/download/AJAX.
5. Uji **multi-role**: user `management` + `sales-*` tidak boleh terkunci PT.
6. Uji **IDOR**: user A tidak bisa membaca/mengubah record user B atau PT lain
   dengan mengganti ID.
7. Pastikan data turunan (dokumen, task, invoice, notifikasi) mengikuti aturan
   yang sama.
8. Pastikan tidak ada role read-only yang mendapat `manage-*`.
9. Jalankan test terkait dan test divisi; periksa diff tidak menyentuh guard.

Test acuan:

- `tests/Feature/DivisionAccessTest.php`
- `tests/Feature/AdminPanelSecurityTest.php`
- `tests/Feature/AdminReadPermissionTest.php`
- `tests/Feature/FollowUpOwnershipTest.php`
- `tests/Feature/DocumentCenterAccessTest.php`
- `tests/Feature/DocumentTrashAccessTest.php`

Kerangka test minimal untuk fitur baru:

```php
it('menolak user tanpa permission', function () {
    $this->actingAs(userWithRole('technician'))
        ->get('/supplier-pos')->assertForbidden();
});

it('mengizinkan pemegang permission granular', function () {
    $this->actingAs(userWithPermission('view-supplier-pos'))
        ->get('/supplier-pos')->assertOk();
});

it('tidak bisa mengakses record PT lain lewat ID', function () {
    $other = Project::factory()->create(['pt' => 'MGK']);
    $this->actingAs(userWithRole('sales-nti'))
        ->put("/projects/{$other->id}", [...])->assertForbidden();
});
```

(Sesuaikan nama helper dengan yang sudah ada di `tests/`.)

## 12. Anti-pola

- Memakai `role:` untuk akses fitur padahal ada permission yang lebih tepat.
- Memberi `view-admin` ke halaman sensitif admin.
- Menyembunyikan menu tanpa menutup route.
- Mengecek `PtAccess` hanya di form, tidak di `store`/`update`/`destroy`.
- Mengambil nilai PT dari input request, bukan dari record.
- Menambah `manage-*` ke role read-only seperti `ceo` atau `prakerin-*`.
- Mengandalkan `super-admin` bypass dalam test.
- Daftar/export yang tidak di-scope sehingga bocor walau halaman detail aman.
- `Model::create($request->all())` atau `forceFill` dari input user.
- Menyimpan file sensitif di `public/` atau memberi URL yang bisa ditebak.
- Menonaktifkan CSRF, guard destruktif, atau verifikasi webhook demi test.
- Membandingkan secret/signature dengan `==` alih-alih `hash_equals`.
- Membuat permission baru tanpa menambahkannya ke role dan test.
- Menulis token, nominal finansial, atau isi dokumen ke log.

## 13. Bila menemukan celah

1. Jangan memperbaiki dengan melonggarkan aturan lain.
2. Tutup celah di lapisan yang benar (route + policy + query), tambahkan test
   yang gagal sebelum perbaikan.
3. Catat di ringkasan: apa celahnya, siapa yang terdampak, dan apakah perlu
   rotasi kredensial atau audit data yang sudah terlanjur terbuka.
4. Jika celah menyangkut data finansial atau kredensial yang mungkin sudah
   diakses pihak tidak berwenang, beri tahu pemilik produk; jangan menyimpulkan
   sendiri bahwa "belum ada yang memakai".

## 14. File kunci

- `database/seeders/RoleAndPermissionSeeder.php`
- `app/Support/PtAccess.php`
- `app/Support/DocumentAccess.php`
- `app/Support/DocumentCenterAccess.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Middleware/SetSecurityHeaders.php`
- `app/Policies/*`
- `routes/web.php`
- `routes/channels.php` **(verifikasi)**
- `bootstrap/app.php`
- `app/Console/DestructiveCommandGuard.php`