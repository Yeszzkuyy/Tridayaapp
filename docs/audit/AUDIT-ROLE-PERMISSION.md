# Role & Permission Matrix — Version 1

> **Status:** v1 — 7 keputusan bisnis telah disetujui pemilik aplikasi (2026-10-26). Implementasi keputusan belum dikerjakan.
> **Tgl audit:** 2026-10-26
> **Sumber:** Audit read-only terhadap `/var/www/3dyapp` (main) dan `/var/www/3dyapp-sec` (feature/security-hardening).

---

## 1. Executive Summary

Aplikasi 3DY menggunakan **Spatie Laravel Permission** (v8) untuk RBAC dengan role dan permission disimpan di database (di-seed via `RoleAndPermissionSeeder`). Ditambah `Gate::before` untuk super-admin bypass, 6 Policy class, dan 2 support class (`PtAccess`, `DocumentAccess`) untuk pembatasan PT/divisi.

| Fakta | Detail |
|---|---|
| Total role di seeder | **15 role** (termasuk varian `sales-<pt>`) |
| Total permission di seeder | **~23 permission** |
| Super Admin bypass | `Gate::before` di `AppServiceProvider.php:31` |
| K2 (ProjectStatusList auth) | **Belum di-merge ke main** — hanya di branch `feature/security-hardening` (3 commit) |
| K1 (WhatsApp webhook) | **Risiko terbuka** — route `/api/whatsapp/webhook` tanpa auth, hanya `throttle` |
| Enum UserRole | `App\Enums\UserRole` (Manager/Teknisi/Guest) — **tidak sinkron** dengan 15 role Spatie |
| Working tree (main) | 5 file berubah, 2 untracked — bukan bagian dari dokumentasi ini |

---

## 2. Daftar Role Aktual

Sumber: `database/seeders/RoleAndPermissionSeeder.php` baris 50–135 (fungsi `$mk`).

| # | Role (nama persis) | Komponen Permission | Catatan |
|---|---|---|---|
| 1 | `super-admin` | `Permission::pluck('name')` — semua permission | Developer (2 akun). Bypass semua via `Gate::before` |
| 2 | `management` | `manage-sales-leads`, `view-marketing`, `view-sales`, `view-technician`, `view-admin`, `view-monitoring`, `monitor-marketing`, `view-customer`, `view-trash` | Hub management |
| 3 | `ceo` | `view-marketing`, `view-sales`, `view-admin`, `view-technician`, `view-monitoring`, `view-customer`, `view-trash` | Read-only semua divisi |
| 4 | `lead-marketing` | `manage-marketing`, `view-marketing`, `monitor-marketing`, `view-technician`, `view-customer`, `view-trash` | Koordinasi tim marketing |
| 5 | `marketing` | `manage-marketing`, `view-marketing`, `view-customer`, `view-trash` | Operasional marketing |
| 6 | `sales` | `manage-sales`, `view-sales`, `view-admin`, `view-customer`, `view-trash` | Operasional sales (tanpa batas PT) |
| 7 | `sales-<pt>` | `manage-sales`, `view-sales`, `view-admin`, `view-customer`, `view-trash` | Sales per PT (dinamis: `sales-mgk`, `sales-nti`, dll.) — tulis dibatasi PT via `PtAccess` |
| 8 | `lead-technician` | `manage-technician`, `view-technician`, `monitor-technical`, `view-sales`, `view-admin`, `view-customer`, `view-trash` | Koordinasi teknisi |
| 9 | `technician` | `manage-technician`, `view-technician`, `view-customer`, `view-trash` | Operasional teknis |
| 10 | `admin` | `manage-admin`, `view-admin`, `view-sales`, `view-customer`, `view-trash` | Operasional administrasi |
| 11 | `inside-sales` | `manage-inside-sales`, `view-customer`, `view-trash` | Task penjualan yang di-assign |
| 12 | `prakerin-technician` | `view-technician`, `view-customer`, `view-trash` | Read-only teknis |
| 13 | `prakerin-admin` | `view-admin`, `view-customer`, `view-trash` | Read-only admin |
| 14 | `manage-sales-leads` (permission, bukan role) | — | Di-link ke `management` dan `lead-technician` |
| 15 | `sales-<pt>` per PT | Dibuat per grup PT via `Lead::PT_GROUPS` | Satu user boleh pegang banyak PT |

### Mekanisme Penentuan Role

| Komponen | File | Mekanisme |
|---|---|---|
| Primary: Spatie roles | `app/Models/User.php` (trait `HasRoles`) | Role disimpan di tabel `roles`, `model_has_roles` |
| Legacy: kolom `role` | `app/Models/User.php` (fillable `role`) | Masih ada, digunakan seeder awal + relasi `User::role('super-admin')` |
| Enum (terpisah) | `app/Enums/UserRole.php` | Manager, Teknisi, Guest — **tidak terintegrasi** dengan Spatie |

### Super Admin Mechanism

```php
// app/Providers/AppServiceProvider.php:31
Gate::before(fn ($user, $ability) => $user->hasRole('super-admin') ? true : null);
```
- `super-admin` menerima semua permission dari seeder.
- Bypass konsisten untuk Gate, Policy, dan middleware permission.
- **Tidak relevan** untuk webhook WhatsApp (`/api/whatsapp/webhook` — tanpa auth, K1).

---

## 3. Daftar Permission Aktual

Sumber: `RoleAndPermissionSeeder.php` baris 40–55.

| # | Permission | Role yang mendapat | Enforcement utama |
|---|---|---|---|
| 1 | `manage-marketing` | marketing, lead-marketing | Route: `leads.create/store/edit/update/destroy/import`, `partners.*`; `LeadPolicy::create/update/delete` |
| 2 | `view-marketing` | marketing, lead-marketing, management, ceo | Route: `leads.index`, `leads.pipeline`, `leads.activities`; `LeadPolicy::viewAny` |
| 3 | `monitor-marketing` | lead-marketing, management, ceo | Route: `leads.monitoring` |
| 4 | `manage-sales` | sales, sales-<pt>, admin, lead-technician | Route: `follow-ups.*`, `pocs.*`, `schedules.*`, `proposals.*`, `price-requests.create/store/cancel`; `CustomerPolicy::create/update/delete` |
| 5 | `view-sales` | sales, sales-<pt>, admin, lead-technician, management, ceo | Route: `follow-ups.show`, `pocs.show`, `sales/dashboard`, `my-leads`, `leads.pipeline`; `ProjectPolicy::view` |
| 6 | `view-admin` | sales, sales-<pt>, admin, lead-technician, management, ceo, prakerin-admin | Route: `products.index`, `payments.index/show`, invoices/POS show |
| 7 | `manage-admin` | admin | Route: `admin-panel` users/roles/work-types, `admin/invoices/pos/payments` mutasi |
| 8 | `view-customer` | Semua role (via `$common`) | Route: `customers.index/show/npwp`; `ProjectDocumentPolicy::view` |
| 9 | `view-trash` | Semua role (via `$common`) | Route: trash index; `DocumentAccess::applyTrashVisibility` |
| 10 | `manage-monitoring` | super-admin saja | Route: `admin-panel.*` |
| 11 | `manage-technician` | technician, lead-technician | Route: `teknisi.*`, project mutasi; `ProjectPolicy::create/update/delete`; `ProjectDocumentPolicy::upload` |
| 12 | `view-technician` | technician, lead-technician, management, lead-marketing, ceo, prakerin-technician | Route: `teknisi.index/show`, project view; `ProjectPolicy::viewAny` |
| 13 | `monitor-technical` | lead-technician | Tidak ditemukan di route `permission:` — **belum jelas penggunaannya** |
| 14 | `manage-sales-leads` | management | Route: `manage-sales.*`, `manage.*`; `PriceRequestPolicy::decide/cancel` |
| 15 | `manage-inside-sales` | inside-sales | Route: follow-ups/pocs/schedules/proposals (gabungan) |
| 16 | `view-monitoring` | management, ceo | Route: `monitoring.index`; `MonitoringPolicy::viewMonitoring` |

### Permission yang didefinisikan vs diberikan vs ditegakkan

| Status | Keterangan |
|---|---|
| Didefinisikan | ~23 permission dibuat via `Permission::create` di seeder |
| Diberikan ke role | Melalui `$mk()` di seeder |
| Ditegakkan backend | Melalui route `permission:` middleware, Policy, `Gate::authorize`, atau inline `abort_unless` |

---

## 4. CURRENT STATE Matrix

Berdasarkan kode aktual (bukan asumsi). Simbol:
**ALLOW** = enforcement backend ditemukan · **DENY** = ditolak eksplisit · **CONDITIONAL** = bergantung kondisi · **UI ONLY** = hanya UI, backend belum terbukti · **UNKNOWN** = bukti tidak cukup · **PENDING** = bisnis belum putuskan.

### 4.1 Dashboard, Profile, Settings, Notifications

| Operasi | super-admin | management | ceo | lead-mkt | marketing | sales | sales-<pt> | lead-tech | technician | admin | inside-sales | prakerin-* |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| View dashboard | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| Profile update | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| Settings | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| Notifications | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |

> **Sumber:** `routes/web.php:57–87` (group `auth` saja, tanpa `permission:` middleware).
> **Catatan:** Semua role yang login memiliki akses penuh ke fitur profil/pengaturan/notifikasi pribadi.

### 4.2 Customer

| Operasi | super-admin | management | ceo | lead-mkt | marketing | sales | sales-<pt> | lead-tech | technician | admin | inside-sales | prakerin-* |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| View list | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| View detail | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| Create | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY |
| Update | ALLOW | DENY | DENY | DENY | DENY | **CONDITIONAL**¹ | **CONDITIONAL**² | DENY | DENY | DENY | DENY | DENY |
| Delete | ALLOW | DENY | DENY | DENY | DENY | **CONDITIONAL**¹ | **CONDITIONAL**² | DENY | DENY | DENY | DENY | DENY |
| Upload dokumen | ALLOW | DENY | DENY | DENY | DENY | **CONDITIONAL**³ | **CONDITIONAL**³ | ALLOW | ALLOW | DENY | DENY | DENY |
| Download NPWP | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |

> **Sumber:** `routes/web.php:129–150`, `CustomerPolicy.php`, `ProjectDocumentPolicy.php`, `PtAccess.php`.
> ¹ `CustomerPolicy::update/delete` → `PtAccess::canWritePt()` — sales non-PT bisa tulis semua.
> ² `sales-<pt>` → `PtAccess::canWritePt()` — hanya PT yang dipegang.
> ³ `ProjectDocumentPolicy::upload` → cek `manage-technician` atau `manage-sales-leads`, dan ownership project.

### 4.3 Leads

| Operasi | super-admin | management | ceo | lead-mkt | marketing | sales | sales-<pt> | lead-tech | technician | admin | inside-sales |
|---|---|---|---|---|---|---|---|---|---|---|---|
| View list | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | **CONDITIONAL**⁴ | **CONDITIONAL**⁴ | ALLOW | **CONDITIONAL**⁵ | ALLOW | **CONDITIONAL**⁶ |
| View detail | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | **CONDITIONAL**⁴ | **CONDITIONAL**⁴ | ALLOW | **CONDITIONAL**⁵ | ALLOW | **CONDITIONAL**⁶ |
| Create | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY | DENY |
| Update | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY | DENY |
| Delete | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY | DENY |
| Convert | ALLOW | DENY | DENY | DENY | DENY | **CONDITIONAL** | **CONDITIONAL** | DENY | DENY | DENY | DENY |
| Update status | ALLOW | DENY | DENY | DENY | DENY | **CONDITIONAL** | **CONDITIONAL** | DENY | DENY | DENY | DENY |
| Monitoring | ALLOW | ALLOW | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY | DENY | DENY |
| Upload attachment | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY | DENY |
| Delete attachment | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY | DENY | DENY |

> **Sumber:** `routes/web.php:467–529`, `LeadPolicy.php`.
> ⁴ `LeadPolicy::view` → `assigned_to === user.id` untuk sales.
> ⁵ Fallback: `manage-technician` atau `manage-admin` → **lihat semua lead** (temuan H3).
> ⁶ `manage-inside-sales` → lead yang punya task untuknya.

### 4.4 Follow-Up & POC

| Operasi | super-admin | management | ceo | lead-mkt | marketing | sales | sales-<pt> | lead-tech | technician | admin | inside-sales |
|---|---|---|---|---|---|---|---|---|---|---|---|
| View list | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |
| View detail | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |
| Create | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |
| Update | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |
| Delete | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |
| Complete/Reopen | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |
| Meeting Report | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | ALLOW |

> **Sumber:** `routes/web.php:159–227` (group `manage-sales` dan `manage-sales|manage-inside-sales`).

### 4.5 Proposals, Price Requests, Products

| Operasi | super-admin | management | lead-tech | sales | sales-<pt> | technician | admin | inside-sales |
|---|---|---|---|---|---|---|---|---|
| Proposal: create/edit | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | ALLOW |
| Proposal: ready/send/revise | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | ALLOW |
| Proposal: return/complete-tech | ALLOW | DENY | DENY | DENY | DENY | ALLOW | ALLOW | DENY |
| Proposal: convert to project | ALLOW | DENY | DENY | **COND**⁷ | **COND**⁷ | DENY | DENY | DENY |
| Price Request: create/store | ALLOW | DENY | DENY | ALLOW | ALLOW | ALLOW | DENY | ALLOW |
| Price Request: review/assign/reject | ALLOW | DENY | ALLOW | DENY | DENY | DENY | DENY | DENY |
| Price Request: reply | ALLOW | DENY | DENY | DENY | DENY | **COND**⁸ | DENY | DENY |
| Price Request: accept/revision | ALLOW | **COND**⁹ | DENY | ALLOW | ALLOW | DENY | DENY | ALLOW |
| Products: CRUD | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | ALLOW | DENY |
| Products: view | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | ALLOW | DENY |

> **Sumber:** `routes/web.php:179–271`, `PriceRequestPolicy.php`.
> ⁷ Inline check di `ProposalController::convert` — `abort_unless` ownership.
> ⁸ `PriceRequestPolicy::reply` → `assigned_to === user.id` dan status tertentu.
> ⁹ `PriceRequestPolicy::decide` → `manage-sales-leads` atau `requested_by === user.id`.

### 4.6 Projects & Project Documents

| Operasi | super-admin | management | ceo | lead-tech | technician | sales | sales-<pt> | admin | inside-sales |
|---|---|---|---|---|---|---|---|---|---|
| Project: view | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | UNKNOWN |
| Project: create | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY |
| Project: update | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY |
| Project: delete | ALLOW | DENY | DENY | ALLOW | ALLOW | DENY | DENY | DENY | DENY |
| Doc: view | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | UNKNOWN |
| Doc: upload | ALLOW | DENY | DENY | ALLOW | ALLOW | **COND**¹⁰ | **COND**¹⁰ | DENY | DENY |
| Doc: upload version | ALLOW | DENY | DENY | ALLOW | ALLOW | **COND**¹⁰ | **COND**¹⁰ | DENY | DENY |
| Doc: update meta | ALLOW | DENY | DENY | ALLOW | **COND**¹¹ | **COND**¹¹ | **COND**¹¹ | DENY | DENY |
| Doc: archive/restore/forceDelete | ALLOW | DENY | DENY | ALLOW | **COND**¹² | **COND**¹³ | **COND**¹³ | DENY | DENY |

> **Sumber:** `routes/web.php:279–394`, `ProjectPolicy.php`, `ProjectDocumentPolicy.php`, `DocumentAccess.php`.
> ¹⁰ `ProjectDocumentPolicy::upload` → ownership project + `PtAccess`.
> ¹¹ `ProjectDocumentPolicy::update` → `manage-technician` atau `update` project policy.
> ¹² `DocumentAccess::technicianCanManage` → uploaded_by, deleted_by, project teknisi_user_id.
> ¹³ `DocumentAccess::salesCanManage` → project sales_user_id, customer PT.

### 4.7 Admin Panel (User, Roles, Master Data)

| Operasi | super-admin | management | ceo | admin | Lainnya |
|---|---|---|---|---|---|
| View admin panel | ALLOW | DENY | DENY | DENY | DENY |
| Users CRUD | ALLOW | DENY | DENY | DENY | DENY |
| Roles CRUD | ALLOW | DENY | DENY | DENY | DENY |
| Work types CRUD | ALLOW | DENY | DENY | DENY | DENY |
| Document categories CRUD | ALLOW | DENY | DENY | DENY | DENY |
| Project statuses (Livewire) | **ALLOW**¹⁴ | DENY | DENY | DENY | DENY |

> **Sumber:** `routes/web.php:609–652` (group `permission:manage-monitoring`), `AdminPanelController`.
> ¹⁴ Di **main**: `ProjectStatusList` **TANPA** `Gate::authorize` — celah K2.
> Di **branch sec**: `Gate::authorize('manage', ProjectStatus::class)` di `save/update/delete`.

### 4.8 Invoice, PO, Payment

| Operasi | super-admin | management | sales | sales-<pt> | admin | Lainnya |
|---|---|---|---|---|---|---|
| View list | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | DENY |
| View detail | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | DENY |
| Create invoice | ALLOW | DENY | DENY | DENY | ALLOW | DENY |
| Update invoice | ALLOW | DENY | DENY | DENY | ALLOW | DENY |
| Delete invoice | ALLOW | DENY | DENY | DENY | ALLOW | DENY |
| Create PO | ALLOW | DENY | DENY | DENY | ALLOW | DENY |
| Create payment | ALLOW | DENY | DENY | DENY | ALLOW | DENY |
| View payment proof | ALLOW | DENY | DENY | DENY | ALLOW | DENY |

> **Sumber:** `routes/web.php:432–460` (group `manage-admin` / `view-admin|manage-admin`).

### 4.9 Monitoring & Laporan

| Operasi | super-admin | management | ceo | Lainnya |
|---|---|---|---|---|
| Monitoring dashboard | ALLOW | ALLOW | ALLOW | DENY |

> **Sumber:** `routes/web.php:604` (middleware `permission:view-monitoring|manage-monitoring`).

### 4.10 Trash & Recovery

| Operasi | super-admin | management | ceo | technician | lead-tech | sales | sales-<pt> | admin | Lainnya |
|---|---|---|---|---|---|---|---|---|---|
| View trash | ALLOW | ALLOW | ALLOW | ALLOW (technical) | ALLOW (all tech) | CONDITIONAL¹⁵ | CONDITIONAL¹⁵ | CONDITIONAL¹⁶ | DENY |
| Restore | ALLOW | DENY | DENY | CONDITIONAL¹⁷ | ALLOW | CONDITIONAL¹⁸ | CONDITIONAL¹⁸ | CONDITIONAL¹⁶ | DENY |
| Force delete | ALLOW | DENY | DENY | CONDITIONAL¹⁷ | ALLOW | CONDITIONAL¹⁸ | CONDITIONAL¹⁸ | CONDITIONAL¹⁶ | DENY |
| Empty my trash | ALLOW | DENY | DENY | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | DENY |

> **Sumber:** `DocumentAccess.php`, `TrashController`, `routes/web.php:418–425`.
> ¹⁵ `salesCanSee` → PT/customer miliknya.
> ¹⁶ `view-admin` → hanya dokumen transaksi.
> ¹⁷ `technicianCanManage` → uploaded_by/deleted_by/project teknisi.
> ¹⁸ `salesCanManage` → project sales_user_id/customer PT.

### 4.11 WhatsApp Webhook (K1)

| Operasi | Super Admin | Semua role | Anonymous |
|---|---|---|---|
| GET verify webhook | **UNKNOWN**¹⁹ | **UNKNOWN**¹⁹ | **UNKNOWN**¹⁹ |
| POST receive webhook | **UNKNOWN**¹⁹ | **UNKNOWN**¹⁹ | **UNKNOWN**¹⁹ |

> **Sumber:** `routes/web.php:666–667`, `WhatsAppCenterController.php`.
> ¹⁹ Route tidak memiliki `auth` atau `permission:` middleware — hanya `throttle:60,1`.
> HMAC `X-Hub-Signature-256` hanya diverifikasi untuk payload bentuk Meta.
> Payload Green API / legacy `account_code` **tanpa verifikasi**. **Risiko K1 terbuka.**

---

## 5. PROPOSED STATE Matrix

Berdasarkan konteks bisnis yang dikonfirmasi dan prinsip rancangan §7.

### 5.1 Prinsip yang Diterapkan

| # | Prinsip | Penerapan |
|---|---|---|
| 1 | Super Admin akses penuh | Semua operasi ALLOW (termasuk audit trail) |
| 2 | Need-to-know | Role operasional hanya mendapat hak minimum untuk tugasnya |
| 3 | View ≠ mutasi | Hak melihat tidak memberikan hak mengubah/menghapus |
| 4 | Create ≠ approve | Hak membuat data tidak memberikan hak menyetujui |
| 5 | Ownership enforcement | Pembatasan owner/assignment di backend, bukan UI |
| 6 | PT enforcement | `PtAccess` tidak bisa dilewati via manipulasi ID |
| 7 | Teknisi ≠ keuangan | Teknisi tidak otomatis akses invoice/payment |
| 8 | Admin = administrasi | Admin kelola PO/invoice/payment sesuai kewenangan |
| 9 | Management/CEO = monitoring | Tidak otomatis dapat mutasi pada setiap modul |
| 10 | Prakerin = minimum | Read-only divisi terkait |
| 11 | Menu ≠ otorisasi | Sembunyikan menu bukan pengganti enforcement |
| 12 | Audit trail | Operasi sensitif mencatat aktor dan waktu |

### 5.2 PROPOSED — Customer (✅ Keputusan Disetujui 2026-10-26)

> **Keputusan terbaru:** Sales boleh melihat **semua Customer**, operasi tulis (create/update/delete) **dibatasi PT** melalui `PtAccess`.
> **Keputusan sebelumnya yang digantikan:** "Sales hanya boleh ubah Customer yang ditugaskan kepadanya" — kini diganti dengan pembatasan PT via `PtAccess.canWritePt()`.

| Tindakan | Super Admin | Sales | Sales-<pt> | Admin | Management | Lainnya |
|---|---|---|---|---|---|---|
| View semua | ALLOW | **ALLOW** ✅ | **ALLOW** ✅ | **ALLOW** ✅ (view Customer) | ALLOW | DENY |
| Create | ALLOW | **ALLOW** ✅ | **ALLOW** ✅ | **PENDING** (belum disetujui) | DENY | DENY |
| Update | ALLOW | **CONDITIONAL** (PtAccess) ✅ | **CONDITIONAL** (PtAccess) ✅ | **PENDING** | DENY | DENY |
| Delete | ALLOW | DENY | DENY | **ALLOW** (sementara) ✅ | DENY | DENY |

> **Catatan keputusan yang disetujui:**
> - Sales (termasuk `sales-<pt>`) boleh **melihat semua Customer** tanpa batas PT.
> - Operasi **tulis (update/delete)** dibatasi oleh `PtAccess.canWritePt()` — `sales-<pt>` hanya bisa tulis di PT yang dipegang; `sales` polos (tanpa role PT) bisa tulis semua.
> - Admin dapat **view Customer** di samping invoice/PO/payment (tidak otomatis dapat hak mutasi tambahan yang belum disetujui).
> - **Konflik yang sudah diidentifikasi:** Keputusan ini **menggantikan** aturan lama "Sales hanya boleh ubah Customer yang ditugaskan kepadanya". Jika masih ada mekanisme assignment-based di kode (mis. `CustomerPolicy::update` yang mengecek `assigned_to`), perlu disesuaikan ke `PtAccess`. **Tindakan follow-up:** verifikasi `CustomerPolicy` dan `PtAccess` — hapus/ubah logika assignment jika bertentangan.

### 5.3 PROPOSED — Leads

| Operasi | Super Admin | Marketing | Lead-Mkt | Sales | Admin | Lainnya |
|---|---|---|---|---|---|---|
| View | ALLOW | ALLOW (all) | ALLOW (all) | CONDITIONAL (assigned) | DENY | DENY |
| Create | ALLOW | ALLOW | ALLOW | DENY | DENY | DENY |
| Update | ALLOW | ALLOW | ALLOW | DENY | DENY | DENY |
| Delete | ALLOW | ALLOW | ALLOW | DENY | DENY | DENY |

### 5.4 PROPOSED — Invoice/PO/Payment & Purchasing

> **Keputusan disetujui:** Admin dapat view Customer & view Products di samping invoice/PO/payment. Role Purchasing baru, izin awal = sama dengan Admin.

| Operasi | Super Admin | Admin | Purchasing (baru) | Sales | Lainnya |
|---|---|---|---|---|---|
| View Customer | ALLOW | **ALLOW** ✅ (baru) | **ALLOW** ✅ | ALLOW | DENY |
| View Products | ALLOW | **ALLOW** ✅ (baru) | **ALLOW** ✅ | ALLOW | DENY |
| View invoice/PO/payment | ALLOW | ALLOW | **ALLOW** ✅ | ALLOW (view) | DENY |
| Create/Update/Delete invoice/PO/payment | ALLOW | ALLOW | **ALLOW** ✅ (izin awal sama Admin) | DENY | DENY |
| Payment proof | ALLOW | ALLOW | **ALLOW** ✅ | DENY | DENY |

> **Catatan Purchasing:**
> - Role `purchasing` baru akan ditambahkan ke `RoleAndPermissionSeeder` dengan permission awal: `manage-admin`, `view-admin`, `view-sales`, `view-customer`, `view-trash`.
> - **Belum otomatis ditugaskan ke akun pengguna mana pun** — assignment ke akun akan dilakukan terpisah setelah role dibuat.
> - Nanti bisa dipersempit jika sudah jelas operasi mana yang spesifik Purchasing vs Admin.
> - **Status:** dokumentasi saja — seeder belum diubah di batch ini.

### 5.5 PROPOSED — Monitoring & Management (✅ Keputusan Disetujui 2026-10-26)

> **Keputusan disetujui:** Management/CEO tetap **read-only** (kecuali Management yang sudah punya `manage-sales-leads` untuk assign lead). **Jangan memberikan hak mutasi tambahan** tanpa persetujuan terpisah.

| Operasi | Super Admin | Management | CEO | Lainnya |
|---|---|---|---|---|
| Monitoring dashboard | ALLOW | ALLOW | ALLOW | DENY |
| Assign lead (manage-sales-leads) | ALLOW | **ALLOW** ✅ (tetap) | DENY | DENY |
| Mutasi modul lain (update project, approve dokumen, dll.) | ALLOW | **DENY** ✅ (tanpa persetujuan) | **DENY** ✅ | DENY |
| Evaluasi/Review (view) | ALLOW | ALLOW | ALLOW (view) | DENY |

---

## 6. Aturan Ownership, Assignment, PT, dan Scope Data

### 6.1 Ownership & Assignment

| Mekanisme | File | Deskripsi |
|---|---|---|
| `assigned_to` (Lead) | `LeadPolicy.php`, `Lead.php` | Sales hanya lihat lead yang di-assign |
| `requested_by` / `assigned_to` (PriceRequest) | `PriceRequestPolicy.php` | Peserta atau yang di-assign |
| `sales_user_id` (Project) | `ProjectDocumentPolicy`, `DocumentAccess` | Sales hanya upload dokumen project miliknya |
| `teknisi_user_id` (Project) | `DocumentAccess.php` | Teknisi hanya kelola dokumen project yang ditugaskan |
| `uploaded_by` / `deleted_by` (Document) | `DocumentAccess.php` | Pemilik file bisa kelola file-nya |
| `trashOwnerId()` (Customer/Project) | `CustomerPolicy`, `ProjectPolicy` | Owner data untuk restore/forceDelete |

### 6.2 Pembatasan PT (`PtAccess`)

```php
// app/Support/PtAccess.php
isUnrestricted($user): super-admin, management, manage-sales-leads
userPts($user): role sales-<pt> → PT yang dipegang
canWritePt($user, $pt): cek PT dalam scope user
isSalesLike($user): sales atau sales-<pt>
```

### 6.3 Scope DocumentAccess

| Method | Scope |
|---|---|
| `isTechnical($doc)` | Non-transaksi (bukan invoice/PO/payment) |
| `canManage($user, $doc)` | Super-admin: all; technician: technical miliknya/projectnya; sales: non-module, project/PT-nya |
| `canViewInTrash($user, $doc)` | Super-admin/management/ceo: all; technician: technical; sales: PT/customer; admin: transaksi |
| `applyTrashVisibility($query, $user)` | Query scoping per role — diterapkan di `TrashController` |

---

## 7. Konflik Konfigurasi Aktual vs Rancangan (Status Per 2026-10-26)

| # | Konflik | Aktual | Keputusan yang Disetujui | Status |
|---|---|---|---|---|
| 1 | Customer view scope | `sales` boleh lihat semua (via `view-customer` di `$common`) | **SAH:** Sales boleh lihat semua Customer; tulis dibatasi `PtAccess`. Menggantikan aturan lama "hanya Customer yang ditugaskan". | ✅ **DIPUTUSKAN** |
| 2 | `LeadPolicy::view` fallback | `manage-technician`/`manage-admin` bisa lihat semua lead | Sebaiknya dibatasi assignment | **Masih perlu review** |
| 3 | `manage-monitoring` hanya untuk super-admin | Hanya super-admin yang bisa akses admin-panel | Belum ada keputusan baru — tetap | **TETAP** |
| 4 | Enum UserRole vs Spatie | `UserRole` enum (Manager/Teknisi/Guest) hanya dipakai di `RegisteredUserController.php:43` | **SAH:** Hapus jika terbukti tidak digunakan. Pemeriksaan menunjukkan hanya 1 tempat — aman dihapus, tapi **kode tidak diubah di batch ini**. | ✅ **DIPUTUSKAN** (implementasi terpisah) |
| 5 | `monitor-technical` tidak dipakai di route | Didefinisikan di seeder, tidak ditemukan di `permission:` | Perlu penjelasan penggunaan | **UNKNOWN** |
| 6 | CustomerPolicy update/delete: assignment vs PtAccess | `CustomerPolicy::update` mengecek `PtAccess.canWritePt()` | **Perlu penyesuaian:** keputusan baru memakai `PtAccess`, bukan assignment. Verifikasi kode, sesuaikan jika masih ada logika assignment. | ⚠️ **PERLU PENYESUAIAN KODE** |

---

## 8. Keputusan Bisnis (✅ Semua Disetujui 2026-10-26)

| # | Pertanyaan | Keputusan yang Disetujui | Dampak |
|---|---|---|---|
| 1 | Hak akses Admin | **(B)** — tambah **view Customer** dan **view Products** di samping invoice/PO/payment. Tidak otomatis dapat hak mutasi tambahan. | `manage-admin` tetap, tambahan `view-customer` sudah ada via `$common` |
| 2 | Sales & Customer view | **(C)** — Sales boleh **lihat semua Customer**; tulis dibatasi PT via `PtAccess`. Menggantikan aturan lama "hanya Customer yang ditugaskan". | Perlu penyesuaian `CustomerPolicy` jika masih ada logika assignment |
| 3 | Management/CEO mutasi | **(A)** — tetap **read-only** + assign lead (`manage-sales-leads`). Tidak ada hak mutasi tambahan. | Scope `management` tetap |
| 4 | Role Purchasing | **(C)** — buat role `purchasing` dengan izin awal sama Admin: `manage-admin`, `view-admin`, `view-sales`, `view-customer`, `view-trash`. Belum assigned ke akun mana pun. | Tambah role baru ke seeder (implementasi terpisah) |
| 5 | Enum UserRole | **(A)** — hapus jika terbukti tidak digunakan. Pemeriksaan: hanya dipakai di `RegisteredUserController.php:43` — aman dihapus. Kode tidak diubah di batch ini. | Hapus `App\Enums\UserRole`, ganti dengan string literal di controller |
| 6 | K1 webhook WhatsApp | **(A)** — **prioritaskan segera**. Perlu verifikasi signature untuk Meta, Green API, dan legacy yang benar-benar didukung + **fail-closed** jika verifikasi gagal. Jangan anggap satu mekanisme Meta cocok untuk semua provider. | Implementasi K1 segera (estimasi 1 hari) |
| 7 | Super Admin | **(B)** — akses penuh + **audit trail**. Semua tindakan sensitif harus mencatat aktor, waktu, tindakan, dan objek yang diubah. Jangan klaim sudah tercatat sebelum diverifikasi. | Pastikan audit log mencakup semua operasi sensitif |

---

## 9. Temuan Backend yang Berisiko

### Critical

| ID | Temuan | Bukti Kode | Risiko |
|---|---|---|---|
| **H1** | **WhatsApp webhook tanpa auth** | `routes/web.php:666-667`: tanpa `auth`/`permission`, hanya `throttle:60,1`; Green API/legacy payload tanpa HMAC | Siapa pun dapat suntik pesan palsu, ubah status outgoing, picu notifikasi massal |

### High

| ID | Temuan | Bukti Kode | Risiko |
|---|---|---|---|
| **H2** | **ProjectStatusList di main tanpa otorisasi mutasi** | `app/Livewire/ProjectStatusList.php` (main): `save/update/delete` tanpa `Gate::authorize`; `find()` null-unsafe → 500 | User apa pun bisa ubah master status via Livewire request langsung |
| **H3** | **LeadPolicy::view fallback terlalu lebar** | `LeadPolicy.php:36`: `manage-technician`/`manage-admin` lihat semua lead | Pelanggaran need-to-know |

### Medium

| ID | Temuan | Bukti Kode | Risiko |
|---|---|---|---|
| **M1** | Enum UserRole tidak sinkron dengan Spatie | `app/Enums/UserRole.php` vs 15 role di seeder | Kekacauan naming, potensi bug |
| **M2** | Route gabungan `|` mungkin terlalu lebar | `web.php:489`: `manage-marketing\|manage-sales\|manage-technician\|manage-admin` pada route lead convert/status | Perlu verifikasi apakah sengaja |

### Low

| ID | Temuan | Bukti Kode |
|---|---|---|
| **L1** | Permission `monitor-technical` tidak jelas penggunaannya | Seeder baris 46, tidak ditemukan di route |
| **L2** | `App\Enums\UserRole` tidak terpakai untuk otorisasi | Hanya untuk label/UI |

---

## 10. Rekomendasi Prioritas Perbaikan (✅ Berdasarkan Keputusan yang Disetujui)

| Prioritas | Tindakan | Estimasi | Keputusan |
|---|---|---|---|
| 1 | **Implementasi K1**: verifikasi HMAC untuk Meta, Green API, legacy + fail-closed | 1 hari | ✅ Keputusan #6 |
| 2 | **Merge K2 ke main**: `ProjectStatusPolicy` + `Gate::authorize` | 0.5 hari (sudah siap) | — |
| 3 | **Tambah role `purchasing` ke seeder** + assignment ke akun | 0.5 hari | ✅ Keputusan #4 |
| 4 | **Sesuaikan CustomerPolicy**: ganti assignment-based → PtAccess-based untuk update/delete | 0.5 hari | ✅ Keputusan #2 |
| 5 | **Hapus Enum UserRole** (sudah terverifikasi hanya 1 tempat) | 0.25 hari | ✅ Keputusan #5 |
| 6 | **Review LeadPolicy::view fallback** — kecilkan scope | 0.5 hari | — |
| 7 | **Audit trail untuk Super Admin** — pastikan semua operasi sensitif tercatat | 1 hari | ✅ Keputusan #7 |
| 8 | **Audit UI-only**: pastikan `@can` di Blade punya enforcement backend | 1-2 hari | — |

---

## 11. Riwayat Status K2 dan K1

### K2 — ProjectStatusList Authorization

| Tanggal | Commit | Perubahan | Status |
|---|---|---|---|
| 2026-10-26 | `fafd16a` | Test: uji otorisasi ProjectStatusList (fase merah) | ✅ Lulus |
| 2026-10-26 | `12a644e` | Feat: `ProjectStatusPolicy` + `Gate::authorize` + `findOrFail` + `is_default: boolean` + halaman 403 | ✅ |
| 2026-10-26 | `56d313c` | Test: sesuaikan asersi Livewire 4 (respons 403/404) + soft delete | ✅ |

**Branch:** `feature/security-hardening` (3 commit unik)
**Status integrasi:** **BELUM di-merge ke main** (diverifikasi via Git: `git merge-base --is-ancestor feature/security-hardening main` = TIDAK).
**Test:** 11 test, 80 assertions, semua lulus di branch sec.
**Di main:** `ProjectStatusList` masih tanpa `Gate::authorize`, `ProjectStatusPolicy` belum ada.

### K1 — WhatsApp Webhook (✅ Keputusan Disetujui: Prioritaskan Segera)

> **Keputusan #6:** Implementasi otorisasi segera. Perlu verifikasi signature untuk **Meta, Green API, dan legacy** yang benar-benar didukung + **fail-closed** saat verifikasi gagal. **Jangan anggap satu mekanisme Meta otomatis cocok untuk semua provider.** Tidak ada implementasi K1 dalam batch dokumentasi ini.

| Aspek | Status |
|---|---|
| Route auth | **TIDAK ADA** — hanya `throttle:60,1` |
| Meta signature (HMAC `X-Hub-Signature-256`) | **Sebagian** — hanya untuk payload bentuk Meta |
| Green API signature | **TIDAK ADA** — tanpa verifikasi |
| Legacy `account_code` | **TIDAK ADA** — tanpa verifikasi |
| Fail-closed | **TIDAK** — jika config kosong, verifikasi dilewati |
| `META_WA_APP_SECRET` | **Belum dikonfigurasi** (tidak boleh dicek nilai) |
| Risk | **Critical — tetap terbuka** |

---

## 12. Bukti Kode untuk Temuan Penting

### H1 — WhatsApp webhook tanpa auth

**File:** `routes/web.php:666-667`
```php
Route::get('/api/whatsapp/webhook', [WhatsAppCenterController::class, 'verifyWebhook']);
Route::post('/api/whatsapp/webhook', [WhatsAppCenterController::class, 'webhook'])
    ->middleware('throttle:60,1');
```

**File:** `app/Http/Controllers/WhatsAppCenterController.php:526-537`
```php
public function webhook(Request $request): JsonResponse
{
    $payload = $request->all();
    if (data_get($payload, 'entry.0.changes.0.value') !== null) {
        return $this->verifyMetaSignature($request) // hanya bentuk Meta
            ? $this->handleMetaNotification($payload)
            : response()->json(['error' => 'Invalid signature'], 403);
    }
    return $this->handleNotification($payload); // Green API / legacy — tanpa verifikasi!
}
```

### H2 — ProjectStatusList di main tanpa otorisasi

**File:** `app/Livewire/ProjectStatusList.php` (main branch)
```php
public function save() // tanpa Gate::authorize
{
    $this->validate();
    ProjectStatus::create([...]);
}
public function delete($id) // tanpa Gate::authorize, find() null-unsafe
{
    $status = ProjectStatus::find($id);
    $status->delete(); // Error jika $id tidak ada → HTTP 500
}
```

### H3 — LeadPolicy::view fallback terlalu lebar

**File:** `app/Policies/LeadPolicy.php:36`
```php
// Fallback teknisi/admin agar bisa convert bila sales lupa.
return $user->can('manage-technician') || $user->can('manage-admin');
```
→ Teknisi dan admin bisa melihat **semua lead** meski tidak di-assign.

---

*Dokumen ini adalah draf audit berdasarkan kode aktual. Implementasi rekomendasi harus mengikuti keputusan yang sudah dikonfirmasi pemilik aplikasi.*
