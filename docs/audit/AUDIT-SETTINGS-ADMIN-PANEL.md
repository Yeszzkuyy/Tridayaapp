# Audit: Settings → Admin Panel

> **Tujuan**: Memindahkan semua menu yang ada di tampilan "Settings" ke menu "Admin Panel" (khusus role super-admin).
> **Status**: Laporan awal — BELUM ada perubahan kode/route.

---

## Menu di Sidebar — Settings

Dari `resources/views/layouts/partials/sidebar.blade.php:85-126`:

| # | Menu/Item di Settings | Route | Controller | View (Blade) | File Terkait Lain |
|---|----------------------|-------|-----------|-------------|-------------------|
| 1 | User Management | `users.index` (tidak terdefinisi) | — | — | — |
| 2 | Account Manager | `account-managers.*` | `AccountManagerController` | `resources/views/settings/account-managers/` (index, create, edit) | `app/Models/AccountManager.php` |
| 3 | Work Type | `work-types.*` | `WorkTypeController` | `resources/views/settings/work-types/` (index, create, edit) | `app/Models/WorkType.php` |
| 4 | Document Category | `document-categories.*` | `DocumentCategoryController` | `resources/views/settings/document-categories/` (index, create, edit) | `app/Models/DocumentCategory.php` |
| 5 | Project Status | `project-statuses.*` | `ProjectStatusController` + `ProjectStatusList` (Livewire) | `resources/views/settings/project-statuses/` (index, create, edit) | `app/Models/ProjectStatus.php`, `app/Livewire/ProjectStatusList.php` |

> **Catatan**: Semua route Settings di atas berada dalam middleware `manage-monitoring` di `routes/web.php:184-209`.

---

## Menu di Sidebar — Admin Panel (Saat Ini)

Dari `resources/views/layouts/partials/sidebar.blade.php:168-178`:

| Menu/Item | Route | Controller | View (Blade) |
|-----------|-------|-----------|-------------|
| Admin Panel (halaman utama) | `admin-panel.index` | `AdminPanelController@index` | `resources/views/admin-panel/index.blade.php` |
| — Users (tabs di halaman utama) | `admin-panel.users.*` (create, edit, update, destroy) | `AdminPanelController@createUser/dll` | `resources/views/admin-panel/users/` (create, edit) |
| — Roles & Permissions (tabs di halaman utama) | `admin-panel.roles.*` (create, edit, update, destroy) | `AdminPanelController@createRole/dll` | `resources/views/admin-panel/roles/` (create, edit) |
| — Audit Log (tabs di halaman utama) | `admin-panel.audit-log` | `AdminPanelController@auditLog` | `resources/views/admin-panel/audit-log.blade.php` |

> **Catatan**: Semua route Admin Panel di atas berada dalam middleware `manage-monitoring` di `routes/web.php:211-230`. Permission-nya SAMA dengan route Settings.

---

## Analisis Per Item

| Menu/Item di Settings | Route & File Terkait | Kategori | Fitur Setara di Admin Panel (kalau ada) | Catatan |
|----------------------|---------------------|----------|----------------------------------------|---------|
| **User Management** | `users.index` (route tdk terdefinisi) — `sidebar.blade.php:98` | **Duplikat** | Admin Panel sudah punya User CRUD (`admin-panel.users.*`) via `AdminPanelController` | Route ini tidak ada di `web.php` — ini dead link. Fungsionalitas manajemen user sudah ada di Admin Panel. |
| **Account Manager** | `AccountManagerController` → `views/settings/account-managers/*` | **Perlu Dipindah** | — | Fitur unik: CRUD data Account Manager. Model, Controller, dan View terpisah. Belum ada di Admin Panel. |
| **Work Type** | `WorkTypeController` → `views/settings/work-types/*` | **Perlu Dipindah** | — | Fitur unik: CRUD jenis pekerjaan. Model, Controller, dan View terpisah. Belum ada di Admin Panel. |
| **Document Category** | `DocumentCategoryController` → `views/settings/document-categories/*` | **Perlu Dipindah** | — | Fitur unik: CRUD kategori dokumen. Model, Controller, dan View terpisah. Belum ada di Admin Panel. |
| **Project Status** | `ProjectStatusController` + `ProjectStatusList` (Livewire) → `views/settings/project-statuses/*` | **Perlu Dipindah** | — | Fitur unik: CRUD status project + Livewire drag-drop list. Model, Controller, View, dan Livewire terpisah. Belum ada di Admin Panel. |

---

## Ringkasan

| Kategori | Jumlah | Item |
|----------|--------|------|
| Duplikat | 1 | User Management (dead link, fungsinya sudah ada di Admin Panel) |
| Perlu Dipindah | 4 | Account Manager, Work Type, Document Category, Project Status |
| Ragu-ragu | 0 | — |

### Catatan Tambahan

1. **Permission middleware sudah sama**: Baik route Settings (Account Manager, Work Type, dll.) maupun route Admin Panel sama-sama menggunakan middleware `manage-monitoring`. Jadi secara akses, tidak ada perubahan — semua sudah khusus super-admin.
2. **User Management adalah dead link**: Route `users.index` tidak didefinisikan di `web.php`. Saat diklik akan error 404. Admin Panel sudah punya User management yang lebih lengkap (dengan role assignment).
3. **Settings di sidebar saat ini menggunakan gate `view-admin`** (line 86), sementara route di dalamnya menggunakan `manage-monitoring`. Admin Panel menggunakan `manage-monitoring`. Ada inkonsistensi permission di sidebar — perlu diselaraskan saat pemindahan.
4. **Yang TIDAK perlu dipindah**: Menu "Admin" (Invoice, PO, Payment) dan "Trash" — ini bukan bagian dari Settings, melainkan menu terpisah di sidebar level atas.
