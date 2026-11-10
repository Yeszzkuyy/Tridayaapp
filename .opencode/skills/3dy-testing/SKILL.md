---
name: 3DY App Testing
description: "Cara menjalankan dan menulis test di repo 3DY App (PHPUnit, SQLite in-memory, seeder role, view tanpa Vite, kebersihan cache di worktree main). Gunakan sebelum merge, saat memperbaiki bug, saat menambah test otorisasi atau konvensi UI, atau saat test gagal karena seeding, storage, cache, atau Vite. Gabungkan dengan test-driven-development untuk siklus red-green-refactor."
metadata:
  opencode/autoinvoke: true
---

# 3DY App Testing

Skill ini adalah panduan **harness dan konvensi test repo 3DY**. Untuk filosofi
TDD (red-green-refactor), gabungkan dengan `test-driven-development`. Untuk
alur bisnis yang diuji, baca `3dy-crm-workflow`; untuk izin, baca
`3dy-security`.

Semua path relatif terhadap root repo.

## Fakta harness

| Bagian | Nilai |
|---|---|
| Framework | PHPUnit 12.5 di atas Laravel 13 |
| Jumlah file test | 142 file (`tests/Feature` dominan, `tests/Unit` untuk helper) |
| Database test | SQLite in-memory (`phpunit.xml`) |
| Cache/session | `array` |
| Queue | `sync` |
| Broadcast | `null` |
| Mail | `array` |
| Storage | Folder sementara per test, diisolasi `Tests\TestCase` |
| Vite | Dinonaktifkan (`withoutVite()`) agar tidak bergantung build |
| Timezone | `Asia/Jakarta` |
| Locale default | `en` |

`phpunit.xml` mengatur env test. Jangan mengandalkan `.env` lokal untuk test.

## Perintah

```bash
# Semua test (Unit + Feature)
php artisan test

# Satu file
php artisan test tests/Feature/DivisionAccessTest.php

# Satu method / pola
php artisan test --filter=test_teknisi_menu_and_access

# Test JS (node:test)
npm run test:js
```

`composer test` menjalankan `config:clear` lalu `php artisan test`. Pakai
`php artisan test` untuk iterasi cepat; pakai `composer test` sebelum merge
bila perubahan menyentuh config.

## Struktur dan aturan dasar

- Letakkan test fitur di `tests/Feature`, helper murni di `tests/Unit`.
- Nama method memakai `test_snake_case` atau atribut `#[Test]`; ikuti pola
  file di sekitarnya.
- Gunakan `RefreshDatabase` untuk test yang menyentuh database. Mayoritas
  test fitur memakainya.
- Jangan memakai `DatabaseSeeder` penuh. Seed hanya
  `RoleAndPermissionSeeder` bila butuh role/permission.
- Hanya ada `UserFactory`. Model domain lain dibuat langsung dengan
  `Model::create([...])` atau lewat seeder khusus. Jangan mengarang factory
  yang tidak ada.
- Gunakan `actingAs($user)` dan assertion HTTP (`assertOk`,
  `assertForbidden`, `assertRedirect`, `assertSee`) untuk test halaman.
- Gunakan `assertDatabaseHas` / `assertDatabaseMissing` untuk memastikan
  perubahan data, bukan hanya tampilan.
- Bersihkan state global: locale, timezone, dan cache permission.

## Base TestCase

`tests/TestCase.php` menyediakan:

- Storage sementara per test (`sys_get_temp_dir().'/3dy-test-...'`) agar
  `Storage::fake()` tidak bentrok antar user/worktree.
- `withoutVite()` agar test tidak gagal saat manifest build belum ada.
- Helper `makeUserWithRole(array $attributes = [], string $role = 'admin')`
  yang otomatis men-seed `RoleAndPermissionSeeder` bila role belum ada.

Contoh:

```php
$admin = $this->makeUserWithRole();
$teknisi = $this->makeUserWithRole([], 'technician');
```

Untuk test yang butuh banyak role sekaligus, seed sekali di awal:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
}
```

Seeder ini menghapus dan membuat ulang role/permission. Jangan menjalankannya
di tengah test yang sudah punya data user, kecuali test memang menguji seeder.

## Pola test yang dipakai repo

### Otorisasi

Uji tiga lapis:

1. Peran yang berhak mendapat `assertOk`.
2. Peran yang tidak berhak mendapat `assertForbidden`.
3. Akses URL langsung tanpa menu tetap ditolak.

Acuan: `DivisionAccessTest`, `AdminPanelSecurityTest`,
`AdminReadPermissionTest`, `FollowUpOwnershipTest`,
`DocumentCenterAccessTest`.

### Alur bisnis

- Siapkan customer, lead, quotation, PO, dan user dengan status yang tepat.
- Jalankan aksi HTTP atau service.
- Cek status akhir, relasi, activity log, dan notifikasi.
- Untuk notifikasi, gunakan `Notification::fake()` lalu
  `Notification::assertSentTo(...)`.
- Untuk job/queue, gunakan `Queue::fake()`.
- Untuk file, gunakan `Storage::fake('private')` (disk utama dokumen) atau
  disk yang dipakai fitur, lalu `UploadedFile::fake()->create(...)`.

Acuan: `SalesFlowTest`, `InvoiceHandoffTest`, `InvoiceSnapshotTest`,
`BastWorkflowTest`, `ProcurementFlowTest`, `PriceRequest*Test`.

### Konvensi UI

`ButtonStyleStandardTest` mengunci aturan tombol §7 `AGENTS.md` (ukuran ikon,
stroke, varian solid, fallback ikon). Jangan mengubah komponen tombol tanpa
menjalankan test ini.

### Bug fix

Tulis test yang mereproduksi bug lebih dulu, pastikan gagal, lalu perbaiki.
Untuk bug waktu, gunakan `travelTo()`/`Carbon::setTestNow()` secara eksplisit
dan kembalikan state setelah test.

## Hal yang sering membuat test gagal

1. **Role belum di-seed.** Gejala: 403/pending approval padahal user dibuat.
   Solusi: `makeUserWithRole()` atau seed `RoleAndPermissionSeeder`.
2. **Permission cache.** Setelah mengubah role/permission di test, panggil
   `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()`.
3. **Vite manifest.** Jangan memanggil `$this->withoutVite()` lagi bila
   memakai base TestCase; sudah otomatis.
4. **Storage milik user lain.** Jangan menulis ke `storage/framework/testing`
   langsung. Gunakan `Storage::fake()`.
5. **Data sisa antar test.** Gunakan `RefreshDatabase`; jangan mengandalkan
   urutan test.
6. **Queue sync.** Notifikasi/job yang `ShouldQueue` tetap jalan di test.
   Fake bila tidak ingin efek samping.
7. **Locale/terjemahan.** String UI memakai `__()`. Set locale eksplisit bila
   assertion bergantung bahasa.
8. **Waktu.** Gunakan `travelTo` dan `Carbon::setTestNow`; jangan mengandalkan
   jam asli untuk pengingat/expiry.

## Setelah menjalankan test di worktree `main`

Web server berjalan sebagai `www-data`. Menjalankan test/artisan sebagai user
lain dapat membuat cache view/file milik user tersebut, lalu halaman live
menjadi 500. Setelah test di `main`:

```bash
sudo -u www-data php artisan view:clear
sudo chmod -R g+rwX storage/framework/cache storage/framework/views \
     storage/framework/sessions storage/app storage/logs
```

Jangan menjalankan test di `main` sebagai root tanpa merapikan izin.

## Database safety

- `DestructiveCommandGuard` memblokir `migrate:fresh`, `migrate:refresh`,
  `migrate:reset`, `db:wipe`, dan `db:drop` di luar test.
- Test memakai SQLite `:memory:` sehingga tidak menyentuh database produksi.
- Jangan pernah mengarahkan `DB_DATABASE` test ke `tridayadb` atau database
  kantor.
- Jangan memakai `migrate:fresh` sebagai jalan pintas memperbaiki test.
  Perbaiki migration/test-nya.
- Untuk test yang butuh skema, cukup `RefreshDatabase`; jangan menjalankan
  migration manual ke database nyata.

## Checklist sebelum merge

1. `php artisan test` lulus (atau minimal test terkait bila suite terlalu
   lama, lalu sebutkan yang belum dijalankan).
2. Tidak ada `dd()`, `dump()`, `var_dump()`, `console.log`, atau test yang
   di-skip tanpa alasan.
3. Test baru mencakup peran positif dan negatif bila menyentuh otorisasi.
4. Tidak ada file sisa: `.bak`, screenshot debug, log, atau database sementara.
5. Bila menambah kelas Tailwind, `npm run build` dari `main`.
6. Bila menyentuh komponen tombol, `ButtonStyleStandardTest` lulus.
7. Bila menyentuh migration, rollback diuji di database test dan tidak ada
   perubahan pada migration lama.

## Anti-pola

- Menulis test yang hanya mengecek status 200 tanpa mengecek data/efek.
- Menguji tampilan menu sebagai bukti otorisasi.
- Memakai `DatabaseSeeder` penuh untuk setiap test.
- Mengandalkan urutan eksekusi test.
- Menonaktifkan guard database atau CSRF agar test lulus.
- Menghapus assertion yang gagal tanpa memahami penyebabnya.
- Menjalankan test di `main` lalu lupa membersihkan cache milik web server.
