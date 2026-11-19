# SISTEM WEB 3DY APP

> Dokumentasi lengkap arsitektur dan cara kerja sistem web **3DY App** (Tridaya Group).
> Basis kode: versi `main` per 1 September 2026 (Laravel 13.x, PHP 8.3).

---

## Daftar Isi

1. [Ringkasan Sistem](#1-ringkasan-sistem)
2. [Arsitektur & Tech Stack](#2-arsitektur--tech-stack)
3. [Struktur Folder Proyek](#3-struktur-folder-proyek)
4. [Modul & Alur Sistem per Divisi](#4-modul--alur-sistem-per-divisi)
5. [Role, Permission & Akses](#5-role-permission--akses)
6. [Struktur Data & Relasi](#6-struktur-data--relasi)
7. [Alur Penting End-to-End](#7-alur-penting-end-to-end)
8. [Keamanan & Operasional](#8-keamanan--operasional)
9. [Dark Mode & Konvensi UI](#9-dark-mode--konvensi-ui)
10. [Pengembangan (Developer)](#10-pengembangan-developer)

---

## 1. Ringkasan Sistem

**3DY App** adalah aplikasi web internal berbasis CRM & ERP untuk **3DY Group** (Tridaya Group). Aplikasi ini menyatukan alur kerja seluruh divisi dalam **satu platform**:

- **Marketing** — mencatat *lead* (calon pembeli/klien), mengelola *pipeline* hingga menjadi proyek.
- **Sales** — mencatat *meeting* dan *follow-up* dengan calon klien.
- **Teknisi** — mengerjakan proyek instalasi, jadwal kerja, dan dokumentasi lapangan.
- **Admin** — administrasi penagihan: *invoice*, *purchase order* (PO), dan *payment*.
- **Monitoring** — manajemen melihat rekap & progres per customer lintas divisi.
- **Admin Panel** — super admin mengelola user, role, permission, dan data master.

Tagline aplikasi: **"Full Visibility. Zero Guesswork"** — semua divisi melihat kondisi terbaru satu customer, dari lead masuk sampai proyek selesai.

Alur inti bisnisnya satu arah: **Lead (Marketing) → Project (Teknisi) → Invoice/Payment (Admin)**, dengan Monitoring sebagai mata manajemen.

---

## 2. Arsitektur & Tech Stack

Arsitektur: **monolitik** (satu aplikasi Laravel) dengan pemisahan divisi lewat **role & permission**, bukan microservice.

### Backend (`composer.json`)
| Teknologi | Versi | Kegunaan |
|---|---|---|
| PHP | ^8.3 | Bahasa pemrograman |
| Laravel Framework | ^13.8 | Kerangka aplikasi (MVC) |
| spatie/laravel-permission | ^8.3 | Role & permission (RBAC) |
| Livewire | ^4.3 | Komponen interaktif (Project Status List) |
| phpoffice/phpspreadsheet | ^5.9 | Import lead dari Excel/CSV |
| google/apiclient | ^2.19 | Sinkron jadwal teknisi ke Google Calendar |
| Laravel Breeze | ^2.4 (dev) | Starter auth (login, register) |

### Frontend (`package.json` + `vite.config.js`)
| Teknologi | Versi | Kegunaan |
|---|---|---|
| Vite | ^8.0 | Build tool (entry: `app.css`, `app.js`, `teknisi-calendar.js`) |
| Tailwind CSS | ^3.x + `@tailwindcss/forms` | Styling utility + normalisasi form |
| Alpine.js | ^3.4 | Interaktivitas ringan (dropdown, modal, toggle) |
| ApexCharts | ^7.1 | Grafik dashboard (donut pipeline, bar trend) |
| SortableJS | ^1.15 | Drag & drop kanban Pipeline lead |
| FullCalendar | ^6.1 | Kalender jadwal teknisi |

Semua JS global di-register di `resources/js/app.js`:

```js
import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import ApexCharts from 'apexcharts';
window.Alpine = Alpine;
window.Sortable = Sortable;
window.ApexCharts = ApexCharts;
```

### Konfigurasi penting
- **Time zone**: `Asia/Jakarta` (`config/app.php:68`).
- **Dark mode**: `darkMode: 'class'` di `tailwind.config.js` — dikontrol toggle di header.
- **Database**: MySQL (produksi) / SQLite (tes), via `.env`.
- **Storage**: `storage/app/public` disimbolkan ke `public/storage` untuk file unggahan (avatar, dokumen).

---

## 3. Struktur Folder Proyek

```
3dyapp/
├── app/
│   ├── Enums/                  # Enum ProjectStatus
│   ├── Http/Controllers/       # Semua controller per modul
│   │   ├── LeadController.php        # Marketing: lead, pipeline, dashboard, import, convert
│   │   ├── PartnerController.php     # Data partner
│   │   ├── CustomerController.php    # Customer + contact
│   │   ├── ProjectController.php     # Project teknisi
│   │   ├── MeetingController.php     # Sales: meeting
│   │   ├── FollowUpController.php    # Sales: follow-up
│   │   ├── AdminController.php       # Invoice, PO, Payment
│   │   ├── MonitoringController.php  # Rekap per customer lintas divisi
│   │   ├── AdminPanelController.php  # User, role, audit log
│   │   └── ...                       # WorkType, ProjectStatus, AccountManager, dll.
│   ├── Livewire/               # Komponen Livewire (ProjectStatusList)
│   └── Models/                 # 23 model Eloquent
├── config/                     # Konfigurasi Laravel
├── database/
│   ├── migrations/             # Skema tabel (50+ file)
│   └── seeders/                # Seeder role/permission + data contoh
├── resources/
│   ├── css/app.css             # Tailwind
│   ├── js/                     # app.js, teknisi-calendar.js
│   └── views/                  # Blade templates
│       ├── layouts/            # app.blade, sidebar, header, guest
│       ├── components/         # stat-card, modal, datepicker, dll.
│       ├── marketing/          # dashboard marketing
│       ├── leads/              # index, create, edit, show, pipeline, import, dll.
│       ├── partners/           # index, create, edit
│       ├── customers/          # index, create, edit
│       ├── projects/           # index, create, show, edit
│       ├── sales/              # meetings, follow-ups
│       ├── admin/              # invoices, pos, payments
│       ├── monitoring/         # monitoring index
│       └── admin-panel/        # users, roles, audit-log, data master
├── routes/
│   ├── web.php                 # Route utama (semua modul)
│   ├── auth.php                # Auth (Breeze)
│   └── admin.php, marketing.php, ...  # Pembagian route (jika dipisah)
├── tests/                      # PHPUnit (86 test)
└── docs/                       # Dokumentasi (PRD, ERD, handover, dll.)
```

---

## 4. Modul & Alur Sistem per Divisi

### 4.1 Marketing (Lead, Partner, Dashboard)

**Menu sidebar** (guard `@can('view-marketing')`):
Dashboard, Lead/Opportunity, Pipeline, Data Partner, Log Aktivitas, Monitoring (khusus `monitor-marketing`).

#### Lead (Opportunity)
- **Status pipeline**: `new → contacted → qualified → proposal → won / lost` (`LeadController::STATUSES`).
- **Sumber lead**: WhatsApp, email, telpon, canvasing, event, website, referral, social media, other.
- **Segmentasi**: End User, Vendor, System Integrator, Kontraktor, Gov, Principle, Distributor, other.
- **PT Group**: NTI, MGK, TPS, WANI — entitas grup yang menangani lead.

**Halaman & fitur lead:**
| Halaman | Isi |
|---|---|
| `leads.index` | Tabel semua lead: customer, status (badge warna), sumber, partner, kebutuhan, tanggal masuk, aksi |
| `leads.create` | Form tambah lead: info umum, data customer (baru/lama), detail kebutuhan/solusi/progress, penugasan sales, lampiran (BOQ) |
| `leads.edit` | Edit lead — data customer terkait langsung terisi & bisa diubah (nama, PIC, alamat, telp, WA, email) |
| `leads.show` | Detail lengkap + lampiran + dokumentasi instalasi project terkait + tombol Konversi ke Project |
| `leads.pipeline` | Kanban 6 kolom status, drag & drop → simpan batch (`leads.batch-status`) |
| `leads.import` | Import banyak lead dari Excel/CSV (phpspreadsheet) |
| `leads.activities` | Log semua aktivitas lead (siapa mengubah apa) |
| `leads.monitoring` | Rekap lead per anggota tim Marketing/Sales |

**Lampiran lead**: file BOQ/spesifikasi di `storage/app/public/leads/{lead_id}/`, record di `lead_documents`, bisa preview/download/hapus dengan audit log.

#### Dashboard Marketing (`marketing.dashboard`)
- Filter rentang tanggal + preset (Bulan Ini / 3 Bulan / 6 Bulan).
- Statistik: Lead bulan ini (vs bulan lalu), total lead, lead aktif, won, lost, conversion rate.
- Grafik: trend lead masuk per bulan, lead per sumber (bar), pipeline per status (donut ApexCharts), ringkasan per status (klik → filter tabel).

#### Partner
Data partner eksternal (supplier, vendor, kontraktor, partner, distributor). Di-relasikan ke lead (`partner_id`).

#### Convert Lead → Project
Saat lead `won`, marketing klik **"Konversi ke Project"** → isi nama project + status project + tipe pekerjaan → otomatis membuat `Project` dan menandai lead `won` + log aktivitas.

### 4.2 Sales (Meeting & Follow-Up)

- **Tracker Meeting** (`sales.meetings.*`): catatan pertemuan dengan customer (tanggal, peserta, kebutuhan user, keluhan, sistem existing, catatan).
- **Follow Up** (`sales.follow-ups.*`): tindak lanjut setelah meeting (deskripsi + tanggal). Bisa dibuat langsung dari customer (`follow-ups.create-with-customer`).
- Meeting → FollowUp (relasi `meeting_id`).

### 4.3 Teknisi (Project, Jadwal, Dokumentasi)

- **Project** (`projects.*`): milik customer, punya status project, work type, PIC engineer, teknisi pendukung, progres, tanggal mulai/selesai, deskripsi.
- **Project Task** (`project-tasks.*`): task pengerjaan.
- **Project Support** (`project-supports.*`): dukungan teknis.
- **Project Document** (`project-documents.*`): dokumentasi instalasi, dikategorikan (DocumentCategory), bisa preview/download. Juga tampil read-only di `leads.show`.
- **Project Activity** (`project_activities`): log aktivitas pengerjaan.
- **Jadwal Teknisi** (`teknisi.jadwal`, `technician_schedules`): jadwal kerja teknisi dengan **sinkron Google Calendar satu arah** (teknisi connect akun Google, jadwal aplikasi → Google). Status: scheduled, on progress, completed, cancelled.
- **Kalender**: FullCalendar untuk tampilan jadwal.

### 4.4 Admin (Invoice, PO, Payment)

- **Invoice** (`admin.invoices.*`): penagihan ke customer (nomor, jumlah, status unpaid/paid/cancelled, jatuh tempo). Relasi ke project & payment.
- **Purchase Order** (`admin.pos.*`): status draft/diproses/selesai/dibatalkan.
- **Payment** (`admin.payments.*`): pembayaran yang masuk terhadap invoice.
- **Trash** (`trash.*`): customer/project yang di-soft-delete → bisa restore atau hapus permanen.

### 4.5 Monitoring (Manager & Super Admin)

`monitoring.index` — rekap per customer lintas divisi:
- Statistik agregat: lead masuk, meeting bulan ini, invoice outstanding, instalasi proses/selesai, PO menunggu.
- Tabel progres customer: setiap customer punya `latest_activity` (dari divisi mana terakhir di-update), `overall_status`, `latest_divisi` + link ke halaman detail masing-masing.
- Dihitung dari relasi customer ke: leads, meetings, followUps, projects, invoices, purchaseOrders.

### 4.6 Admin Panel (Super Admin)

- **User Management**: buat/ubah/hapus user, assign role.
- **Roles**: kelola role & permission (Spatie).
- **Account Manager**: master data AM.
- **Work Type**: master tipe pekerjaan.
- **Document Category**: master kategori dokumen.
- **Project Status**: master status project (Livewire).
- **Audit Log**: log aktivitas admin.

---

## 5. Role, Permission & Akses

Package: **spatie/laravel-permission**. Permission dibuat dengan pola `manage-{divisi}` dan `view-{divisi}`.

### Daftar permission (`RoleAndPermissionSeeder`)
```
manage-marketing, view-marketing,
manage-sales,     view-sales,
manage-admin,     view-admin,
manage-teknisi,   view-teknisi,
manage-monitoring, view-monitoring,
view-customer, view-trash, monitor-marketing
```

### Daftar role & hak akses
| Role | Permission utama |
|---|---|
| **super-admin** | Semua permission |
| **manager** | view-marketing, view-sales, view-admin, view-teknisi, view-monitoring |
| **marketing** | manage + view marketing, view-customer, view-trash |
| **marketing-lead** | Sama seperti marketing + `monitor-marketing` |
| **sales** | manage + view sales, view-customer, view-trash |
| **admin** | manage + view admin, view-customer, view-trash |
| **teknisi** | manage + view teknisi, view-customer, view-trash |

### Cara pemakaian
- Middleware route: `Route::middleware('permission:manage-marketing')` dsb. (lihat `routes/web.php:184-226`).
- Guard view: `@can('manage-marketing')`, `@can('view-marketing')` di Blade (sidebar `layouts/partials/sidebar.blade.php:80-125`).
- User `id=1` otomatis jadi super-admin saat seeder dijalankan.

---

## 6. Struktur Data & Relasi

### Relasi inti
```
User ──assigned_to──→ Lead ──customer_id──→ Customer ──→ Project
                         │                        │
                         ├──→ LeadActivity        ├──→ ProjectTask
                         ├──→ LeadDocument        ├──→ ProjectDocument
                         └──partner_id──→ Partner ├──→ ProjectSupport
                                                   ├──→ ProjectActivity
Customer ──→ Meeting ──→ FollowUp
Customer ──→ Invoice ──→ Payment
Customer ──→ PurchaseOrder
Project   ──→ TechnicianSchedule ──→ User (teknisi)
```

### Tabel utama (contoh kolom kunci)
| Tabel | Kolom penting |
|---|---|
| `users` | name, email, avatar, role (legacy) |
| `customers` | name, company, contact_person, address, phone, whatsapp, email, status, deleted_by |
| `leads` | customer_id, partner_id, pt_group, segment, status, source, kebutuhan, solusi, progress_notes, notes, incoming_date, assigned_to |
| `lead_activities` | lead_id, user_id, action, changes (JSON) |
| `lead_documents` | lead_id, file_name, file_path, mime_type |
| `partners` | name, type, contact_person, phone, email, address |
| `projects` | customer_id, account_manager_id, work_type_id, project_status_id, pic_engineer, support_technicians, project_name, project_code, quotation_number, progress, start_date, end_date |
| `meetings` | customer_id, meeting_date, participants, user_needs, user_complaints, existing_system |
| `follow_ups` | customer_id, meeting_id, description, follow_up_date |
| `invoices` | invoice_number, customer_id, project_id, amount, status, issue_date, due_date |
| `purchase_orders` | status: draft/diproses/selesai/dibatalkan |
| `payments` | jumlah pembayaran |
| `technician_schedules` | user_id, project_id, technician_user_id, title, start_at, end_at, status, google_event_id, google_sync_status |

### Soft Delete
`Customer`, `Project`, `Lead`, `User`, `Partner`, dan tabel anaknya memakai `SoftDeletes`. Data yang dihapus masuk **Trash** (restore/hapus permanen) kecuali Lead yang dihapus permanen.

---

## 7. Alur Penting End-to-End

### 1. Lead masuk (Marketing)
```
Tambah lead (baru: buat customer; lama: pilih customer existing)
→ lampirkan BOQ
→ status default 'new'
→ muncul di tabel & dashboard
```

### 2. Follow-up & pipeline
```
Sales follow-up via WhatsApp/meeting
→ update status: contacted → qualified → proposal
→ (opsional) geser kartu di Pipeline (drag & drop → save batch)
→ semua perubahan tercatat di Log Aktivitas
```

### 3. Menang (Won) → Project
```
Status won / tombol "Konversi ke Project"
→ isi nama project + status + tipe pekerjaan
→ Project dibuat (teknisi yang handle), lead jadi 'won'
```

### 4. Pengerjaan (Teknisi)
```
Project: task, support, dokumentasi instalasi (unggah per kategori)
→ jadwal teknisi + sinkron Google Calendar
→ dokumentasi bisa dilihat juga dari detail lead customer tsb
```

### 5. Penagihan (Admin)
```
Invoice dibuat per customer/project
→ PO menunggu proses
→ Payment masuk → invoice paid
```

### 6. Monitoring (Manajemen)
```
Cek monitoring.index: rekap semua customer + aktivitas terakhir lintas divisi
→ klik detail → lihat halaman aslinya (lead/meeting/project/invoice)
```

---

## 8. Keamanan & Operasional

### Keamanan
- **Auth**: Laravel Breeze (login, forgot password, verify email).
- **RBAC**: middleware `permission:` + guard `@can` di tiap view.
- **Validasi input** di semua controller (`$request->validate`).
- **File upload** dibatasi ekstensi & ukuran (max 10MB).
- **Akses file**: dokumen dicek ownership (misal `$document->project->customer_id !== $lead->customer_id → abort(404)`).
- **Password**: di-hash (`hashed` cast).
- **CSRF** default Laravel di semua form.

### Operasional & multi-sesi
- Website dilayani dari folder `main` (`/var/www/3dyapp`).
- Pengembangan pakai **git worktree** — satu divisi satu folder & branch:
  `3dyapp` (main), `3dyapp-mkt`, `3dyapp-cus`, `3dyapp-tek`, `3dyapp-user`, `3dyapp-mon`.
- Alur: fitur di branch divisi → merge ke `main` → `git pull` semua worktree → `npm run build` jika ada perubahan CSS/JS.
- Aturan lengkap: `AGENTS.md` (konvensi warna tombol, larangan commit `.env`, dll.).

### Perintah penting
```bash
# Test
php artisan test

# Cache view (deteksi error blade)
php artisan view:cache && php artisan view:clear

# Build frontend
npm run build

# Seed ulang role & permission
php artisan db:seed --class=RoleAndPermissionSeeder
```

---

## 9. Dark Mode & Konvensi UI

### Dark mode
- Mode: `class` (`tailwind.config.js`), toggle di header (`header.blade.php:35`).
- Setiap elemen yang tampil di dark harus punya pasangan `dark:bg-*` / `dark:text-*` / `dark:border-*`.
- Contoh pola input (sudah dipakai di `datepicker`):
  `bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700`.

### Konvensi warna tombol (AGENTS.md bagian 7)
| Aksi | Kelas |
|---|---|
| Simpan / Tambah / Submit | `bg-blue-600 hover:bg-blue-700 text-white` |
| Lihat / detail | `bg-indigo-50 hover:bg-indigo-100 text-indigo-700` |
| Edit | `bg-blue-100 hover:bg-blue-200 text-blue-700` |
| Hapus | `bg-red-100 hover:bg-red-200 text-red-700` |
| Aksi positif (convert/approve) | `bg-green-100 hover:bg-green-200 text-green-700` |
| Batal / Kembali / Reset | `bg-blue-500 hover:bg-blue-600 text-white` |

**Larangan**: jangan pakai `bg-slate-*`/`bg-gray-*`/`bg-white` sebagai background tombol (override dark-mode membuat tombol menyatu).

### Badge status lead (konsisten light + dark)
```
new:        bg-blue-100 text-blue-800       dark:bg-blue-900/40 dark:text-blue-300
contacted:  bg-yellow-100 text-yellow-800   dark:bg-yellow-900/40 dark:text-yellow-300
qualified:  bg-purple-100 text-purple-800   dark:bg-purple-900/40 dark:text-purple-300
proposal:   bg-orange-100 text-orange-800   dark:bg-orange-900/40 dark:text-orange-300
won:        bg-green-100 text-green-800     dark:bg-green-900/40 dark:text-green-300
lost:       bg-red-100 text-red-800         dark:bg-red-900/40 dark:text-red-300
```

---

## 10. Pengembangan (Developer)

### Dokumentasi terkait (folder `docs/`)
| Topik | File |
|---|---|
| Kebutuhan produk | `docs/PRD.md` |
| Skema DB & ERD | `docs/database-v1.md`, `docs/erd-v1.md` |
| Desain sistem | `docs/system-design-v1.md` |
| Frontend & wireframe | `docs/Frontend_Plan_Wireframe.md` |
| Sistem dark mode | `docs/DARK_MODE_SYSTEM.md` |
| Google Calendar | `docs/GOOGLE_CALENDAR.md` |
| Serah terima antar sesi | `docs/HANDOVER.md` |

### Tumpukan teknis per halaman
- **Route**: `routes/web.php` (+ pemisahan per divisi bila ada).
- **Controller**: `app/Http/Controllers/`.
- **View**: `resources/views/{modul}/`.
- **Model**: `app/Models/`.
- **JS interaktif**: Alpine.js (`x-data`, `x-show`), ApexCharts (donut), SortableJS (kanban).

### Test
- Framework: PHPUnit (`phpunit`).
- Cakupan: 86 test (auth, leads, customers, projects, attachments, pipeline).
- Perintah: `php artisan test`.
