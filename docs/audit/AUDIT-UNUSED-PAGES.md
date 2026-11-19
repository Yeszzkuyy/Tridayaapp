# Laporan Analisis Halaman/Route/Controller Tidak Terpakai

Dihasilkan: 28 Jul 2026
Metode: Cross-reference route definitions, controller methods, view files, dan blade references.

---

## Ringkasan

| Kategori | Jumlah |
|----------|--------|
| Route aktif | ~100 |
| View terpakai | ~96 |
| View **tidak terpakai** | 4 |
| Controller method **tidak terpakai** | 3 |
| Controller method **missing** (route ada, method tidak) | 2 |
| Bug (route name salah) | 1 |

---

## Tabel Lengkap

### A. VIEW TIDAK TERPAKAI (tidak direferensikan route manapun)

| File | Jenis | Status | Alasan |
|------|-------|--------|--------|
| `resources/views/document_categories/create.blade.php` | View | Tidak Terpakai | Controller menggunakan `settings.document-categories.create`, bukan ini |
| `resources/views/document_categories/index.blade.php` | View | Tidak Terpakai | Controller menggunakan `settings.document-categories.index`, bukan ini |
| `resources/views/document_categories/edit.blade.php` | View | Tidak Terpakai | Controller menggunakan `settings.document-categories.edit`, bukan ini |
| `resources/views/components/⚡project-status-list.blade.php` | View | Tidak Terpakai | Bukan Blade view — isinya adalah PHP class `App\Livewire\ProjectStatusList` duplikat yang tersimpan di folder views secara tidak sengaja |

### B. VIEW TERDAFTAR TAPI TIDAK BISA DIRAIH KARENA CONTROLLER METHOD MISSING

| File | Jenis | Status | Alasan |
|------|-------|--------|--------|
| `resources/views/project_documents/create.blade.php` | View | Tidak Terpakai | Route `project-documents.create` ada dan mengarah ke `ProjectDocumentController::create()`, tapi method `create()` tidak didefinisikan di controller tersebut. View tidak akan pernah dirender. |

### C. CONTROLLER METHOD TIDAK TERPAKAI (tidak dipanggil route manapun)

| Method | Jenis | Status | Alasan |
|--------|-------|--------|--------|
| `ProjectStatusController::index()` | Controller | Tidak Terpakai | Route `project-statuses.index` (web.php:148) menggunakan Livewire `ProjectStatusList`, bukan controller ini. Method `index()` di controller tidak pernah dipanggil. |
| `ProjectSupportController::index()` | Controller | Tidak Terpakai | Tidak ada route yang mengarah ke `ProjectSupportController@index`. Method body kosong (`//`). |
| `ProjectSupportController::edit()` | Controller | Tidak Terpakai | Method body kosong (`//`), tidak ada route yang mengarah ke sini. |
| `ProjectSupportController::update()` | Controller | Tidak Terpakai | Method body kosong (`//`), tidak ada route yang mengarah ke sini. |

### D. CONTROLLER METHOD MISSING (route terdaftar, method tidak ada)

| Route | Jenis | Status | Alasan |
|-------|-------|--------|--------|
| `project-documents.create` | Route | Ragu-ragu | Route mengarah ke `ProjectDocumentController::create()` tetapi method `create()` tidak didefinisikan. Akan throw error 500 jika diakses. View `project_documents/create.blade.php` ada tapi tidak bisa diraih. |
| `document-categories.restore` | Route | Ragu-ragu | Route mengarah ke `DocumentCategoryController::restore()` tetapi method `restore()` tidak didefinisikan. Akan throw error 500 jika diakses. |

### E. BUG: ROUTE NAME SALAH DI VIEW

| Lokasi | Jenis | Status | Alasan |
|--------|-------|--------|--------|
| `resources/views/monitoring/index.blade.php:13,50` | Blade | Ragu-ragu | Menggunakan `route('monitoring')` tetapi route name yang benar adalah `monitoring.index`. Akan throw `RouteNotFoundException` jika diakses karena `monitoring` bukan route name yang valid. |

### F. DUPLICATE ROUTE NAMES

| Route Name | Duplikat? | Status | Alasan |
|------------|-----------|--------|--------|
| `users.create`, `users.store`, `users.edit`, `users.update`, `users.destroy` | Ya | Terpakai | Dua grup route: (1) `UserController` di middleware `manage-admin`, dan (2) `AdminPanelController` di middleware `manage-monitoring` prefix `admin-panel.`. Names tidak bentrok karena prefix `admin-panel.` membedakan. |

### G. VIEW TERPAKAI (tidak perlu dihapus)

Semua view lain di bawah `resources/views/` **terpakai** — direferensikan oleh controller/Livewire/di-include oleh layout. Termasuk:

- `admin/invoices/*`, `admin/pos/*`, `admin/payments/*` — via AdminController
- `admin-panel/*` — via AdminPanelController
- `settings/account-managers/*` — via AccountManagerController
- `settings/work-types/*` — via WorkTypeController
- `settings/document-categories/*` — via DocumentCategoryController
- `settings/project-statuses/*` — via ProjectStatusController (create, edit, store, update, destroy masih via controller; index via Livewire)
- `leads/*` — via LeadController
- `customers/*` — via CustomerController
- `projects/*` — via ProjectController
- `project_documents/index.blade.php`, `preview.blade.php`, `show.blade.php` — via ProjectDocumentController
- `project_supports/*` — via ProjectSupportController
- `project_tasks/create.blade.php` — via ProjectTaskController
- `sales/meetings/*` — via MeetingController
- `sales/follow-ups/*` — via FollowUpController
- `dashboard/*` — via DashboardController
- `monitoring/*` — via MonitoringController
- `layouts/*` — di-include oleh app.blade.php
- `components/*` — dipanggil via `<x-...>` di berbagai view
- `auth/*` — via Auth controllers
- `profile/*` — via ProfileController
- `livewire/*` — via Livewire ProjectStatusList
- `trash/*` — via TrashController
- `users/*` — via UserController
- `customer_contacts/*` — via CustomerContactController

---

## Rekomendasi

1. **Hapus** `resources/views/document_categories/` (3 file) — pindah ke `settings/document-categories/` sudah jalan.
2. **Hapus** `resources/views/components/⚡project-status-list.blade.php` — duplikat Livewire class yang salah simpan.
3. **Hapus atau implementasi** `ProjectDocumentController::create()` — View `project_documents/create.blade.php` ada, tapi method `create()` tidak didefinisikan. Jika tidak perlu, hapus route dan view.
4. **Implementasi** `DocumentCategoryController::restore()` — atau hapus route jika fitur restore tidak diperlukan.
5. **Perbaiki** `route('monitoring')` menjadi `route('monitoring.index')` di `monitoring/index.blade.php`.
6. **Hapus** method `index()`, `edit()`, `update()` dari `ProjectSupportController` jika tidak akan dipakai.