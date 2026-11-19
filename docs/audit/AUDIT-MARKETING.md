# Audit: Modul Marketing (Lead/Opportunity)

> **Tujuan**: Memeriksa apakah modul Marketing (Lead/Opportunity) sudah pernah dibuat (oleh AI assistant di sesi sebelumnya) — lengkap atau belum.
> **Status**: Laporan audit — TIDAK ada perubahan kode/route.
> **Kesimpulan**: **SUDAH ADA TAPI BELUM LENGKAP** — backend lengkap (model, migration, controller, route, permission, view), tapi menu sidebar tidak ada sehingga fitur tidak terlihat di tampilan, dan tabel `leads` masih kosong.

---

## 1. Backend — Sudah Ada & Lengkap

### Model

`app/Models/Lead.php` — sudah terhubung ke Customer:

| Relasi | Keterangan |
|--------|-----------|
| `customer()` | `belongsTo(Customer::class)` — kolom `customer_id` ✓ |
| `assignee()` | `belongsTo(User::class, 'assigned_to')` |
| `projects()` | via `customer->projects()` |

Field: `customer_id`, `status`, `source`, `notes`, `opportunity_value` (decimal:2), `expected_close_date` (date), `assigned_to`. SoftDeletes aktif.

### Migration

`database/migrations/2026_07_28_035513_create_leads_table.php` — sudah jalan di DB:

- `customer_id` → FK `constrained()->cascadeOnDelete()` ✓
- `status` enum: `new, contacted, qualified, proposal, won, lost` (default `new`)
- `source` enum: `website, referral, cold_call, email, social_media, event, other`
- `opportunity_value` decimal(15,2), `expected_close_date` date, `assigned_to` FK users (nullOnDelete)

### Controller

`app/Http/Controllers/LeadController.php` — method lengkap: `index, create, store, show, edit, update, destroy, convert` (+ `previewDocument`, `downloadDocument`).

### Route

`routes/web.php:157-170`:

| Middleware | Route |
|-----------|-------|
| `permission:manage-marketing` | `leads.create/store/edit/update/destroy/convert` |
| `permission:view-marketing\|manage-marketing` | `leads.index/show`, `leads.documents.preview/download` |

### View

`resources/views/leads/` — `create.blade.php`, `edit.blade.php`, `index.blade.php`, `show.blade.php` ✓

---

## 2. Database — Migration Ada, Data Kosong

Query langsung ke PostgreSQL `db_teknisi` (read-only):

| Tabel | Jumlah Row |
|-------|-----------|
| `leads` | **0** |
| `customers` | 5 |
| `meetings` | 0 |
| `follow_ups` | 0 |

Migration terdaftar di tabel `migrations`: `2026_07_28_000001_create_meetings_table`, `2026_07_28_000002_create_follow_ups_table`, `2026_07_28_035513_create_leads_table` — semuanya sudah dijalankan.

---

## 3. Permission — Sudah Ada

Seeder `database/seeders/RoleAndPermissionSeeder.php:38-40`:

- Role **`marketing`** dibuat dengan permission `manage-marketing`, `view-marketing` (+ lainnya).
- Konfirmasi DB: role `marketing` ada, memegang 6 permission — termasuk `manage-marketing` dan `view-marketing`.
- User ber-role `marketing`: **0 dari 7 user** (role terdaftar tapi belum ada user yang memakainya).

---

## 4. Sidebar — MENU TIDAK ADA (Penyebab Fitur Tak Terlihat)

`resources/views/layouts/partials/sidebar.blade.php` hanya memiliki menu **Sales** (dropdown → Tracker Meeting & Follow Up, guarded `@can('view-sales')`). **Tidak ada menu Marketing/Lead sama sekali** — tidak ada link `leads.*` di sidebar.

> Ini sesuai hipotesis awal: **backend sudah dibuat lengkap, tapi menu sidebar kelewat** sehingga halaman Lead hanya bisa diakses lewat URL manual (`/leads`), tidak lewat navigasi.

---

## Kesimpulan

**SUDAH ADA TAPI BELUM LENGKAP.**

Yang kurang:

1. **Menu sidebar Marketing/Lead** — tambahkan link/group ke `leads.index` (guard `@can('view-marketing')`) di `resources/views/layouts/partials/sidebar.blade.php`, mengikuti pola menu Sales yang sudah ada.
2. **Data masih kosong** — `leads` 0 row; wajar karena fitur belum bisa diakses lewat UI. Setelah menu muncul, data bisa diisi via form create lead.
3. **Opsional**: assign role `marketing` ke user yang relevan (saat ini 0 user memakai role tersebut).

Tidak ada yang perlu dibuat dari nol — model, migration, controller, route, permission, dan view sudah lengkap dan sudah terkoneksi ke `customers`.
