# PROJECT SPEC AUDIT — 3DY App (PT Tridaya)

> Hasil bedah codebase langsung per 15 Okt 2026. Branch `main`, workdir `/var/www/3dyapp`.
> Laravel **13.34.0** (constraint `^13.8`), PHP **8.3.6**, DB default **sqlite** (`.env.example`: `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=file`).
> File ini untuk AI konsultan eksternal: efisiensi fitur, arsitektur data, kompatibilitas sistem.

---

## 1. RINGKASAN PROYEK & TUJUAN

### 1.1 Gambaran umum / tujuan bisnis
**3DY App** = ERP/ops internal grup Tridaya (4 PT: **NTI, MGK, TPS, WANI** — konstanta `Lead::PT_GROUPS`, `WhatsappAccount::GROUPS`).
Mengelola seluruh siklus **lead → penawaran → proyek teknik → penagihan** plus kanal WhatsApp dan asisten AI, dalam satu monolit Blade + Alpine + React-islands.

Entitas inti: `Customer` (perusahaan) → `Lead` (peluang per PT) → `SalesSchedule` / `FollowUp` / `Poc` / `TechnicalRequest` / `Proposal (+ProposalItem)` → `Project` (+`Survey`, `SizingProject`, `RequestHarga`, `Instalasi`, `ProjectTask/Support/Document/Activity`) → `Invoice` → `Payment`, `PurchaseOrder`. Kanal masuk: 4 akun WA company (`WhatsappAccount` + `WhatsappMessage` + `WhatsappConversation`) yang bisa di-convert jadi `Lead`.

### 1.2 Target pengguna / role (sumber: `RoleAndPermissionSeeder.php` + `CompanyUserSeeder.php`)
Pola permission: `manage-{divisi}` (tulis) vs `view-{divisi}` (baca), + izin khusus. `Gate::before` di `AppServiceProvider`: `super-admin` bypass semua.

| Role | Permission | Catatan / user seeded |
|---|---|---|
| `super-admin` | semua | Yeski (`yehezkielmayogi.ptnti@gmail.com`/id 1) + Deka (`technician`+`super-admin` sementara) |
| `management` | `manage-sales-leads`, `view-marketing/sales/technician/admin/monitoring`, `monitor-marketing` | Ardian (+`lead-technician`+`sales`), Christina, Yanita, Ayu |
| `ceo` | semua `view-*` saja | Victor GM |
| `lead-marketing` | `manage/view-marketing`, `monitor-marketing`, `view-technician` | Syifa |
| `marketing` | `manage/view-marketing` | Anggie |
| `sales` + `sales-{nti,mgk,tps,wani}` | `manage/view-sales`, `view-admin` | Adi Santosa, Hendry, Irfan, Deby, Audi, Dini; scoping tulis per-PT via `App\Support\PtAccess` (tanpa role PT = tanpa batas) |
| `inside-sales` | `manage-inside-sales` saja | hanya task yang di-assign (lead terlihat bila ada task) |
| `admin` | `manage/view-admin`, `view-sales` | Hanifah, Vanesha, April |
| `technician` | `manage/view-technician` | Khairil, Gilar, Ardi, Fanuel, Zero, Naufal, Ryan |
| `lead-technician` | teknisi + `monitor-technical`, `view-sales`, `view-admin` | Amir, Ardian |
| `prakerin-technician` / `prakerin-admin` | `view-technician` / `view-admin` saja | — |
| `management` hub | prefix `manage-sales.*` + `manage.*` placeholder | assign lead ke sales + activity log |

Password awal seeded `3dy@11830` (`CompanyUserSeeder`), kolom legacy `users.role` diselaraskan dengan role Spatie pertama.

### 1.3 Alur kerja operasional utama teknisi (end-to-end, sebagaimana dikodekan)
1. **Tiket masuk = `TechnicalRequest`** (`sales.technical-requests.store`, oleh sales/inside-sales; `status=waiting_lead`). Wajib `lead_id` + `request_type` (`survey|estimate|installation|troubleshooting|consultation|other`).
2. **Review lead-teknisi** (`review` → `accepted/rejected`), lalu **assign** (`assigned_technician_id`, → `assigned`), **progress** (→ `in_progress`), **result** (isi `technical_result`), **complete** (→ `completed`, set `completed_at`). Notifikasi: `TechnicalRequestCreated/Reviewed/WorkCompletedNotification`. `TechnicalRequestController::ensureSurvey()` otomatis membuat `Survey` bila tipe `survey` dan belum ada (maks 1 via `surveys.technical_request_id` unique).
3. **Survey lapangan** (`teknisi.surveys.*`, `SurveyController` 376 baris): isi `survey_data`, `documentation` (JSON foto), upload PDF laporan (`report_file` di disk `private`, max validasi di controller, `viewReport`/`downloadReport` via `SecureFile::serverMime`). Status `completed` diakui konstanta `Survey::STATUS_COMPLETED`. Sales yang memegang project bisa baca (guard `guardRead`, `isSalesRole`).
4. **Sizing** (`sizing-projects`): `customer_needs`, `recommendation`, `specifications`, `quantity`, `topology` → draf desain.
5. **Request Harga** (`request-hargas`): `items` JSON + `attachments` JSON.
6. **Proposal kolaborasi sales↔teknisi** (state machine `ProposalController` 503 baris, status: `draft → pending_technical → ready → sent → viewed → revision → accepted(WON)/rejected/expired/cancelled`): sales `store/update/submit-technical`, teknisi `return-technical/complete-technical`, sales `markReady/send/markViewed/revise/respond/cancel/uploadPo`. `ExpireProposals` (`proposals:expire` tiap 01:30) menandai `expired` bila lewat `valid_until`. `convertToProject` membuat `Project` (`proposal_id` balik) + `LeadActivity proposal_converted`.
7. **Project eksekusi** (`ProjectService::create/update/delete`): `progress` integer, `project_status_id` (Open/On Progress/Pending/Hold/Done/Cancelled/Warranty/Maintenance — `ProjectStatus::BADGE_COLORS`), `pic_engineer` string bebas + `support_technicians` CSV string (bukan FK — lihat §6). Sub-modul: `ProjectTask` (tugas), `ProjectSupport` (tiket support), `ProjectDocument` (kategori via `document_categories`), `Instalasi` (checklist JSON + `job_status` + `documentation` JSON), `ProjectActivity` (audit via `ProjectObserver::created/updated`).
8. **Jadwal & kalender** (`TechnicianScheduleController` 399 baris + `GoogleCalendarService` 670 baris): CRUD `technician_schedules` (`status`: `scheduled|on_progress|completed|cancelled`; sync: `not_connected|syncing|synced|error`), OAuth per-user (`google_credentials`, token encrypted), kredensial app global (`google_app_credentials`, `client_secret`+`api_key` encrypted), sinkron 2-arah (`gcal:sync` tiap 5 mnt, `withoutOverlapping`), reminder (`schedules:remind` tiap menit, `reminder_minutes` + `reminder_sent_at`), push ke GCal (`pushEvent/buildPayload/buildReminders`).
9. **Validasi/selesai & laporan**: status project → Done, dokumen BAST/Report di `ProjectDocument`, `Invoice/PO/Payment` oleh admin (`AdminService`), progress per customer di `MonitoringController` (fase subquery `withMax` + fase eager-load 15/halaman), recap di `TechnicianDashboardController` + `DashboardController`.

---

## 2. TECH STACK & DEPENDENCIES

### 2.1 Backend (`composer.json` + `composer.lock` terverifikasi)
| Paket | Versi terpasang | Fungsi di kode |
|---|---|---|
| `php` | `8.3.6` CLI (constraint `^8.3`) | runtime |
| `laravel/framework` | **`13.34.0`** (constraint `^13.8`) | monolit, Blade, queue DB, scheduler |
| `livewire/livewire` | `^4.3` | 2 komponen: `ProjectStatusList`, `LeadTaskChat` (+chat task) |
| `spatie/laravel-permission` | `^8.3` | RBAC penuh (roles/permissions, `@can`, `permission:` middleware, `User::role()` scope) |
| `google/apiclient` | `^2.19` | GCal OAuth + `Calendar` service (`GoogleCalendarService::calendarService`) |
| `laravel-notification-channels/webpush` | `^13.0` | Web Push VAPID (`HasPushSubscriptions`, `PushSubscriptionController`, `push_subscriptions`) |
| `laravel/ai` | `^0.8.0` | `HasConversations`, `OfficeAssistant` agent + 7 Tools + `WhatsappSalesBot`, `LeadNeedsSummarizer` |
| `phpoffice/phpspreadsheet` | `^5.9` | import lead XLSX (`LeadController@import`, throttle `6,1`) + export `my-leads`/`dar` (`ManageSalesController`) |
| `laravel/breeze` | `^2.4` (dev) | Auth scaffolding (`routes/auth.php`, `AuthenticatedSessionController` dkk) |
| `laravel/pail`, `pao`, `pint`, `phpunit ^12.5`, `faker`, `collision`, `mockery` | dev | log viewer, pint format, test (`tests/Feature` ~30+ file) |
| **Tidak ada** | — | Tidak ada Filament, Sanctum (API file tidak di-load — lihat §3), DomPDF/TCPDF/Maatwebsite — PDF hanya file upload, Excel via PhpSpreadsheet mentah |

### 2.2 Frontend (`package.json` + `vite.config.js`)
| Paket | Versi | Dipakai di |
|---|---|---|
| `tailwindcss ^3.1` + `@tailwindcss/forms`, `autoprefixer`, `postcss` | 3.x | `resources/css/app.css`, `sidebar-nav.css`, `themes/`; entry Vite `app.css` |
| `alpinejs ^3.4` | 3.x | `resources/js/app.js` (interaksi Blade), chunk-split `alpine` |
| `vite ^8.0` + `laravel-vite-plugin ^3.1`, `@vitejs/plugin-react ^6.1` | 8.x | build; input: `app.css`, `app.js`, `teknisi-calendar.js`, `pipeline-dnd.js`, `rubber-segment.jsx`, `glide-select.jsx`; code-splitting manual: `alpine/charts(markdown)/dnd/calendar/react` |
| `react ^19.3` + `react-dom`, `motion ^13.4`, `ogl ^1.0` | 19.x | islands `rubber-segment.jsx`, `glide-select.jsx`, efek `galaxy.js` |
| `@fullcalendar/{core,daygrid,interaction,timegrid} ^6.1` | 6.x | `teknisi-calendar.js` + `teknisi/jadwal`, `sales/schedules/api` |
| `sortablejs ^1.15` | — | drag-drop pipeline lead (`pipeline-dnd.js`) |
| `marked ^18` + `dompurify ^3.4` | — | render Markdown AI assistant (`ai/assistant`, chunk `markdown`) |

192 Blade files (`resources/views/`), layout `layouts/`, komponen `x-icon-button` (konvensi tombol putih+border, hover accent — wajib per `AGENTS.md §7`).

### 2.3 Infra / runtime
DB default sqlite, queue `database` (worker via `Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()` — tanpa supervisor), session `database`, cache `file`. Scheduler: `schedules:remind` tiap menit, `followups:remind` harian, `proposals:expire` 01:30, `gcal:sync` tiap 5 mnt, `whatsapp:receive` + `whatsapp:bot` tiap 30 dtk (terdaftar di `bootstrap/app.php withSchedule`). Storage privat: disk `private` untuk NPWP, lampiran TR, report survey, bukti payment, PO proposal (`MigrateFilesToPrivate`, `SecureFile::serverMime`). Trust proxy `*`, CSRF kecuali `api/whatsapp/webhook`, middleware web `SetLocale` + `SetSecurityHeaders` + Spatie alias.

---

## 3. DAFTAR FITUR & STRUKTUR ROUTES

> Kebenaran penting: `bootstrap/app.php` hanya me-load **`routes/web.php`** (+`auth.php` via require) — file `routes/api.php` **dikomentari/disabled** (`// api: ...`), dan `routes/{admin,admin-panel,monitoring,marketing,teknisi}.php` **tidak pernah di-require** (duplikat legacy dari `web.php`; isinya subset yang sama). Webhook WA didaftar di bawah `web.php` (`/api/whatsapp/webhook` GET+POST, CSRF-except + throttle `60,1`).
> Total efektif ≈ 200 route di `web.php`. Di bawah ini daftar lengkap dikelompokkan (Metode — URI — Nama — Controller@Method — Middleware).

### 3.1 Root / Auth / Umum
| M | URI | Nama | Controller@Method | Middleware |
|---|---|---|---|---|
| GET | `/` → `/login` | — | redirect | — |
| GET | `/dashboard` | `dashboard` | `DashboardController@index` | `auth,verified` |
| GET/POST | `/login`, POST `/logout`, forgot/reset/verify/confirm/password | `login,logout,password.*,verification.*` | `Auth\*` (Breeze) | `guest` / `auth` |

### 3.2 Profile / Settings / Notifikasi / Push (`auth`)
| M | URI | Nama | C@M | Ket |
|---|---|---|---|---|
| GET/PATCH/DELETE | `/profile` | `profile.edit/update/destroy` | `ProfileController` | avatar+cover dinormalisasi (`storeNormalizedAvatar/Cover`, GD), hapus terpisah |
| GET/PATCH/POST | `/settings`, `/settings/appearance`, `/settings/advanced`, `/settings/advanced/confirm` | `settings.*` | `SettingsController` | preferensi+locale, confirm throttle `10,1` |
| POST/GET/DELETE | `/notifications/read-all`, `/notifications/status`, `/notifications/{n}/read`, `/notifications/{n}` | `notifications.*` | `NotificationController` | DB notifications, 15 tipe (lihat §5) |
| POST/DELETE | `/push-subscriptions` | `push-subscriptions.*` | `PushSubscriptionController` | VAPID per browser |

### 3.3 AI Assistant + Knowledge (`auth` + `permission:manage-admin`)
| M | URI | Nama | C@M |
|---|---|---|---|
| GET/POST | `/ai/assistant` (POST throttle `30,1`) | `ai.assistant.index/send` | `OfficeAssistantController@index/send` (upload 30MB, `storeAttachments`, 7 Tools user-scoped) |
| GET/PATCH/DELETE | `/ai/assistant/conversations/{conversation}` | `ai.assistant.conversations.*` | show/rename/destroy |
| GET/POST/DELETE | `/knowledge-base`, `/knowledge-base/sync`, `/knowledge-base/{document}` | `knowledge-base.*` | `KnowledgeBaseController` (RAG file umum, `knowledge_base_documents`) |
| GET/POST/PATCH/DELETE | `/product-knowledge`, `/import`, `/products/bulk`, `/products/{product}`, `/publish`, `/reject`, `/sources/{source}`, `/refetch` | `product-knowledge.*` | `ProductKnowledgeController` (pipeline crawl Firecrawl → `products/product_sources/knowledge_documents/knowledge_chunks`, job `ImportProductSource/PollProductCrawl`) |

### 3.4 Sales — Customer & Kontak
| M | URI | Nama | C@M | Middleware |
|---|---|---|---|---|
| GET/POST | `/customers/create`, `/customers` | `customers.create/store` | `CustomerController` | `manage-sales` |
| GET/PUT/DELETE | `/customers/{customer}/edit`, `/{customer}`, `/{customer}` | `customers.edit/update/destroy` | soft-delete → Trash (observer cascade) |
| GET/POST | `/customers/{customer}/contacts/create`, `/contacts` | `customer-contacts.create/store` | `CustomerContactController` |
| GET/PUT/DELETE/PATCH | `/customer-contacts/{cc}/edit`, `PUT`, `DELETE`, `/{cc}/primary` | `customer-contacts.*` | toggle primary |
| GET | `/customers`, `/customers/{customer}`, `/customers/{customer}/npwp` | `customers.index/show/npwp` | `view-sales\|manage-sales\|view-customer` |

### 3.5 Sales — FollowUp & PoC
`prefix sales`, `manage-sales`: `resource follow-ups` (except show + explicit show), `resource pocs`, `POST follow-ups/{f}/{complete,reopen,snooze}`, `GET follow-ups/create/{customer?}`. Read: `GET sales/follow-ups`, `sales/pocs` (`view-sales|manage-sales`). Service `SalesService` (filter own-lead untuk sales-like, reminder `followups:remind`, `reminder_sent_at`, `completed_at`, `next_follow_up_date`). Tipe: `CONTACT_TYPES=[call,whatsapp,email,note]`, `LOG_TYPES=[whatsapp,call,email,meeting,proposal]`.

### 3.6 Sales Flow — Jadwal / TechnicalRequest / Proposal / Produk
| M | URI | Nama | C@M | Middleware |
|---|---|---|---|---|
| GET/POST/PUT | `sales/schedules/create`, ``, `/{schedule}/edit`, `/{schedule}` | `sales.schedules.create/store/edit/update` | `SalesScheduleController` | `manage-sales\|manage-inside-sales` |
| POST | `sales/schedules/{s}/{complete,cancel}` | `sales.schedules.*` | idem |
| GET/POST | `sales/technical-requests/create`, `` | `sales.technical-requests.create/store` | `TechnicalRequestController` | idem |
| POST | `sales/technical-requests/{tr}/cancel` | `.cancel` | sales batalkan |
| GET/POST/PUT | `sales/proposals/create`, ``, `/{p}/edit`, `/{p}` | `sales.proposals.create/store/edit/update` | `ProposalController` | idem |
| POST | `sales/proposals/{p}/{ready,send,viewed,revise,respond,cancel,submit-technical,po,convert}` + `GET preview`, `GET po` (download) | `sales.proposals.*` | `markReady/send/markViewed/revise/respond/cancel/submitTechnical/uploadPo/convertToProject/downloadPo` |
| POST | `sales/proposals/{p}/{return-technical,complete-technical}` | `.return-technical/.complete-technical` | `manage-technician\|manage-admin` |
| GET/POST/PUT/DELETE | `products/create`, ``, `/{product}/edit`, `/{product}`, `/{product}` | `products.*` | `ProductController` (katalog BOQ: `sku,category(barang/jasa/software),unit,default_price,is_active`) | tulis `manage-sales\|manage-admin`, baca `view-sales\|manage-sales\|view-admin\|manage-admin` (+`picker`) |
| GET | `sales/schedules`, `schedules/api`, `schedules/{schedule}`, `technical-requests/{tr}`, `.../attachment`, `proposals`, `proposals/{p}`, `.../preview`, `.../po` | `sales.schedules.index/api/show`, `.technical-requests.show/attachment`, `.proposals.index/show/preview/download-po` | baca luas `view-sales\|manage-sales\|manage-inside-sales\|manage-technician` |
| POST | `sales/technical-requests/{tr}/{review,assign,progress,result,complete}` | `.technical-requests.*` | `manage-technician` (lead-teknisi) |

### 3.7 Teknisi — Project & anak (`manage-technician` tulis, `view-technician|manage-technician|view-sales` baca)
Tulis: `GET/POST /projects/create|/`, `GET/PUT/DELETE /projects/{p}/edit|/|\` (`ProjectController` via `ProjectService`); supports (`/projects/{p}/supports/create|/` + `DELETE /project-supports/{ps}`); tasks (`/projects/{p}/tasks/create|/` + `DELETE /project-tasks/{pt}`); documents (`POST /projects/{p}/documents`, `DELETE /project-documents/{d}`, `GET .../preview`). Baca: `GET /projects`, `/projects/{p}`, `/projects/{p}/documents`, `GET /project-documents/{d}/download`.

### 3.8 Teknisi — Dashboard/Kalender/Jadwal (`teknisi.*`)
Baca (`view-technician|manage-technician`): `GET teknisi/dashboard` (`TechnicianDashboardController@index`), `GET teknisi/kalender/events`, `/kalender/status`, `/schedules/api`, `/jadwal/google-events` (`CalendarController@googleEvents`), `/jadwal`. Tulis (`manage-technician`): `POST /schedules`, `PUT /schedules/{s}`, `PATCH /schedules/{s}/complete`, `DELETE`, `POST /kalender/credentials|/sync`, `GET /kalender/connect|/callback`, `DELETE /kalender/disconnect`.

### 3.9 Teknisi — Workflow Survey/Sizing/RequestHarga/Instalasi/Dokumen (`teknisi.*`)
Baca: `GET surveys/create` + sizing/request/instalasi `index/create/show` + `documents` (`DocumentRepositoryController@index`, lintas project). Baca survey diperluas ke sales (`view-technician|manage-technician|view-sales`): `GET surveys`, `/{survey}`, `/{survey}/report`, `.../report/download`. Tulis (`manage-technician`): `POST surveys`, `POST .../report`, `PUT/DELETE surveys`, `GET edit`, + CRUD penuh `sizing-projects/request-hargas/instalasis` (`store/update/destroy/edit`).

### 3.10 Admin — Trash / Invoice-PO-Payment
`permission:view-trash`: `GET /trash` + `PATCH /trash/{customers,projects}/{id}/restore` + `DELETE .../delete` + `DELETE /trash/clear` (`TrashController`, owner-map + `PtAccess`, Policy `ownsTrash`). `manage-admin prefix admin`: invoice/PO `create/store/edit/update/destroy`, payment `create/store/destroy` (+`proof` terpisah karena nomor rekening); baca `view-admin|manage-admin`: `invoices/pos/payments index/show` (`AdminController` + `AdminService::generateInvoiceNumber/generatePoNumber/assertProjectBelongsToCustomer`).

### 3.11 Marketing — Lead/Partner + WA Center + Monitoring + Manage
Tulis (`manage-marketing`): `leads create/store/edit/update/destroy`, `leads/import` (GET form + POST throttle `6,1` PhpSpreadsheet), `partners` CRUD. Convert/status/outcome (`manage-marketing|manage-sales|manage-technician|manage-admin`): `PATCH leads/{l}/convert` (buat Project), `/status`, `/batch-status`, `/outcome` (`lost_reason` enum: `price|competitor|budget|requirement_changed|no_response|other`). Khusus: `GET leads/monitoring` (`monitor-marketing`), `GET leads/pipeline` (`view-sales|manage-sales`, own-lead + kolom virtual New + filter won/lost minggu berjalan), baca (`view-marketing|manage-marketing`): `GET leads`, `marketing/dashboard`, `leads/activities`, `leads/{l}/documents/{d}/{preview,download}`, `leads/{l}/attachments/{doc}/{show,download,destroy,category}`, `GET partners`. Detail `GET leads/{lead}` **paling bawah** (agar tidak memangsa `/activities` dkk), middleware luas 7 permission + `LeadPolicy::view` (sales own-lead, inside-sales via task).
WA Center (`whatsapp-center.*`, baca `view-marketing|manage-marketing`): `GET /`, `/status`, `/{account}/conversations|/contacts|/messages/{sender}|/check-status`, `POST .../messages/{sender}/read`, `POST .../conversations/{sender}/preference`; tulis (`manage-marketing`): `POST .../messages/{sender}` (reply), `.../takeover|/release`, `POST .../convert/{sender}` (WA→Lead), `POST .../contacts`, `POST .../simulate`, `PUT .../credentials|/bot-settings`. Webhook publik `GET|POST /api/whatsapp/webhook` (verify + `handleNotification/handleMetaNotification`, signature Meta, `notifyInbound`, polling fallback `whatsapp:receive`).
Manage (`manage-sales-leads`): `GET manage-sales`, `/activity-log`, `/{lead}/edit`, `PUT /{lead}`, `POST /{lead}/assign` (`ManageSalesController`, `trackAssignment`, notif `LeadAssigned/NewLead`, export `sales/my-leads/export`, `dar/export`); placeholder `GET manage/{marketing,technical,admin}` (view `manage.placeholder` — stub). Sales milik: `GET sales/dashboard|/my-leads` (`view-sales|manage-sales`).
Lead-tasks (`lead-tasks.*`, `view-sales|manage-sales|manage-sales-leads|manage-inside-sales`): `GET /|/create|/{task}|/{task}/edit`, `POST /`, `PUT /{task}`, `DELETE`, `POST /{task}/comments` (+Livewire `LeadTaskChat`).
Monitoring: `GET /monitoring` (`view-monitoring|manage-monitoring`, `MonitoringController` 2-fase). Admin-panel (`manage-monitoring`, `admin-panel.*`): `GET /`, users CRUD, roles CRUD (+guard super-admin terakhir), `audit-log`, `account-managers` (+resource), `work-types` (+resource), `document-categories` (+restore), `project-statuses` (Livewire `ProjectStatusList` + CRUD).

---

## 4. SKEMA DATABASE & MODEL RELASIONAL

### 4.1 Daftar tabel utama + kolom kunci (dari `database/migrations/*.php`)
> Konvensi: hampir semua tabel bisnis `softDeletes`; FK `cascadeOnDelete` ke parent, `nullOnDelete` ke user opsional. Index performa ditambah `2026_10_06_000003` (`leads(status,incoming_date,closed_at,assigned_to+status)`, `customers(pt_group,name)`, `follow_ups`, `invoices/pos/payments`, `projects`, `lead_activities`).

| Tabel | Kolom kunci / FK / enum |
|---|---|
| `users` | `name,email(unique),password,role(legacy string, default guest→migrasi),avatar,cover,preferences(JSON),phone,last_activity,password_changed_at,email_verified_at,softDeletes` + Spatie `roles/permissions`, `push_subscriptions`, AI `conversations` |
| `customers` | `name,contact_person,company,pt_group(NTI/MGK/TPS/WANI),address,phone,whatsapp,email,npwp,npwp_document_path(private),notes,status(lead/deal),deleted_by,whatsapp_account_id→whatsapp_accounts` |
| `customer_contacts` | `customer_id→customers, name,position,phone,whatsapp,email,is_primary,softDeletes` |
| `partners` | `name,type(supplier/vendor/kontraktor/partner/distributor),contact_person,phone,email,address,notes,softDeletes` |
| `leads` | `customer_id→customers(cascade),partner_id?,whatsapp_account_id?,pt_group,segment,status(cool/warm/hot/won/lost — migrasi `replace_lead_statuses_with_cool_warm_hot`),source(string nullable, dulu enum),kebutuhan,solusi,progress_notes,notes,incoming_date(date, dulu expected_close_date),assigned_to→users,assigned_at,acknowledged_at,lost_reason,lost_note,closed_at,closing_note,softDeletes` |
| `lead_activities` | `lead_id,user_id,action(created/updated/converted/status_changed/assigned/meeting_created/proposal_*/followup_*/task_*),changes(JSON)` |
| `lead_documents` | `lead_id,file_name,file_path,ordered?category(proposal/quotation/company_profile/technical/requirement/supporting/other),mime` (lampiran BOQ awal) |
| `lead_tasks` | `lead_id→leads, title,description,assigned_to→users,due_date,priority(low/medium/high),status(todo/in_progress/review/done),created_by,softDeletes` + `lead_task_comments(lead_task_id,user_id,body)` |
| `follow_ups` | `customer_id,lead_id?,description,type(call/whatsapp/email/note + legacy follow_up/meeting; LOG_TYPES whatsapp/call/email/meeting/proposal),follow_up_date,activity_time,location,next_follow_up_date,reminder_sent_at,completed_at,created_by,softDeletes` (kolom `meeting_id` sudah di-drop bersama tabel `meetings/meeting_drafts` di `2026_10_12_141500`) |
| `pocs` | `customer_id,lead_id?,type(poc/demo),scheduled_date,location,status(scheduled/done/cancelled),result_notes,created_by,softDeletes` |
| `sales_schedules` | `lead_id,assigned_to,created_by,title,type(meeting/call/visit/presentation/follow_up/quotation/internal/other),start_at,end_at,location,description,status(scheduled/completed/cancelled/rescheduled),reminder_at,softDeletes` + idx `(assigned_to,start_at)`, `(lead_id,status)` |
| `technical_requests` | `lead_id,requested_by,title,request_type(survey/estimate/installation/troubleshooting/consultation/other),requirement,problem_description,scope,priority(normal/urgent),target_date,attachment_path(private),notes,status(waiting_lead/accepted/rejected/assigned/in_progress/completed/cancelled),assigned_technician_id,technical_result,completed_at,softDeletes` + idx |
| `surveys` | `title?,project_id,customer_id?,sales_user_id?,technical_request_id?(unique, maks 1),sales_request,survey_date,location,pic,survey_data(text),documentation(JSON foto),survey_report(text),report_file(private PDF),report_file_name,status(draft/completed),notes,softDeletes` |
| `sizing_projects` | `project_id,sales_pic,customer_needs,recommendation,specifications,quantity(int),topology,technical_notes,attachments(JSON),status,softDeletes` |
| `request_hargas` | `project_id,items(JSON),notes,attachments(JSON),status,softDeletes` |
| `instalasis` | `project_id,location,technician_pic(string),schedule_date,job_status,checklist(JSON),documentation(JSON),installation_report,notes,attachments(JSON),status,softDeletes` |
| `proposals` | `lead_id,proposal_number(unique, `generateNumber()`),created_by,proposal_date,valid_until,customer(string snapshot),subtotal/discount/tax/grand_total(15,2),notes,po_document_path,status(draft/pending_technical/ready/sent/viewed/revision/accepted/rejected/expired/cancelled),sent_at,sent_by,softDeletes` |
| `proposal_items` | `proposal_id,product_id?→products,description,quantity,unit,unit_price,discount,subtotal` |
| `products` | **dipakai ganda**: KB (`brand,name,model,sku,category,status review/approved/published/rejected`) + BOQ (`unit,default_price,is_active`) — ditambah aditif di `proposal_module_phase1`; `SALE_CATEGORIES=[barang,jasa,software]` |
| `product_sources` | `product_id,url,source_type(product_page/datasheet/documentation/catalog),status(queued/processing/success/failed/needs_review/duplicate/published),error,last_fetched_at,content_hash,batch_id` |
| `knowledge_documents` | `source_id→product_sources,product_id?,title,content(longText),source_url(2048),version,content_hash,status(draft/published/superseded/rejected)` + `knowledge_chunks(document_id,chunk_index,content,metadata; FTS pgsql `to_tsvector` else LIKE)` |
| `knowledge_base_documents` | `original_name,category(enum `KnowledgeBaseCategory`),project_id?,user_id?,store_id,provider_file_id,provider_document_id,mime,size,status(indexing/…)` (RAG umum laravel/ai) |
| `projects` | `customer_id(cascade),sales_user_id?,teknisi_user_id?,account_manager_id?(nullOnDelete),work_type_id,project_status_id,pic_engineer(string!),support_technicians(string CSV!),project_name,project_code(unique?),quotation_number,proposal_id?(nullOnDelete),progress(int),start_date,end_date,description,deleted_by,softDeletes` (kolom `status` enum lama sudah di-drop `2026_08_06_090104`; `pic_engineer_id` → string `2026_07_08_022600`) |
| `project_statuses` | `name,color,is_default(bool),sort_order,is_active(bool),softDeletes`, `BADGE_COLORS` 8 status |
| `project_tasks` | `project_id,task_name,assigned_to(string?, bukan FK ketat),due_date,status,priority,description,softDeletes` |
| `project_supports` | `project_id,title,description,status,priority,assigned_to,resolved_at,resolved_by,softDeletes` |
| `project_documents` | `project_id,file_name,file_path(private),notes,uploaded_by→users,document_category_id→document_categories,softDeletes` + `document_categories(name,softDeletes)` |
| `project_activities` | `project_id,user_id,activity_date,type,title,description,metadata(JSON)` |
| `account_managers/work_types` | `name(+phone,email)` / `name` |
| `technician_schedules` | `user_id(owner),project_id?,technician_user_id?,title,description,location,start_at,end_at,status(scheduled/on_progress/completed/cancelled),reminder_minutes,reminder_sent_at,google_event_id,google_calendar_id,google_sync_status,google_sync_error,softDeletes` + `google_credentials(user_id unique,access_token(enc),refresh_token(enc),expires_at,calendar_id)` + `google_app_credentials(client_id,client_secret(enc),api_key(enc→text),calendar_id)` |
| `invoices` | `invoice_number(unique),customer_id,project_id?,amount,status(unpaid/paid/cancelled),issue_date,due_date,notes,created_by,softDeletes` |
| `purchase_orders` | `po_number(unique),customer_id,project_id?,items(text),amount,status(draft/diproses/selesai/dibatalkan),issue_date,notes,created_by,softDeletes` |
| `payments` | `invoice_id→invoices(cascade),amount,payment_date,payment_method,proof_file(private, berisi norek — route proof `manage-admin` only),notes,created_by,softDeletes` |
| `whatsapp_accounts` | `name,phone_number,account_code(unique: wa_nti/wa_wani/wa_mgk/wa_tps),gateway_type(green_api/meta),gateway_instance,gateway_token(enc,hidden),gateway_status,assigned_to,is_active,bot_enabled,bot_instructions,softDeletes` |
| `whatsapp_messages` | `whatsapp_account_id,sender_number(dinormalisasi digit),sender_name,message_body,direction(inbound/outbound),status,is_bot(bool),wa_message_id(unique?),gateway_message_id,lead_id?,read_at,idx(account,sender)` |
| `whatsapp_conversations` | `whatsapp_account_id,sender_number,mode(bot/human),handled_by,bot_turns,needs_summary,taken_over_at,reminded_at,unique(account,sender)` + `whatsapp_conversation_preferences(user_id,account,sender,is_pinned/muted/archived)` |
| Spatie | `roles,permissions,model_has_roles,model_has_permissions,role_has_permissions` (+`notifications,cache,cache_locks,jobs,job_batches,failed_jobs,sessions,agent_conversations`) |

### 4.2 Relasi antar model (`app/Models/*.php`)
- `User`: `HasRoles, HasConversations, HasPushSubscriptions, SoftDeletes`; helper `preference()`, `hasSecurePassword()`, `deletionBlockReason()` (kunci super-admin terakhir + `@tridayaapp.com`); **tanpa relasi Eloquent eksplisit ke lead/project/schedule** (diakses via query `assigned_to`).
- `Customer`: `hasMany(contacts,projects,followUps,invoices,purchaseOrders,leads)`, `belongsTo(whatsappAccount)`, `belongsTo(deleter)`, `trashOwnerId()` (1 sales unik via `leads.assigned_to` else null → super-admin only), `waLink()`.
- `CustomerContact`: `belongsTo(customer)`.
- `Lead`: `belongsTo(customer+withTrashed, assignee, partner, whatsappAccount)`, `hasMany(activities,documents,followUps,tasks,salesSchedules,technicalRequests,proposals)`; **`projects()` = `$this->customer->projects()` (delegasi, bukan FK langsung — NPE bila customer null, lihat §6)**; konstanta `PT_GROUPS/LOST_REASONS/PT_COLORS`, `salesStatusLabel()` (`cool`→`New`), `statusBadgeClasses()`.
- `Project`: `belongsTo(customer,accountManager,workType,status,proposal,deleter)`, `hasMany(tasks,activities,documents,supports,surveys,sizingProjects,requestHargas,instalasis)`, `trashOwnerId()` ikut customer.
- `TechnicalRequest`: `belongsTo(lead,requester,technician)`, `hasOne(survey)`; `Survey`: `belongsTo(project,customer,sales,technicalRequest)`.
- `Proposal`: `belongsTo(lead,creator,sender)`, `hasMany(items)`, `hasOne(project)`, `recalculate()` (`sum(subtotal)`, clamp discount); `ProposalItem`: `belongsTo(proposal,product)`.
- `Invoice`: `belongsTo(customer,project,creator)`, `hasMany(payments)`; `Payment`: `belongsTo(invoice,creator)`; `PurchaseOrder`: `belongsTo(customer,project,creator)`.
- `TechnicianSchedule`: `belongsTo(owner,project,technician)`, `scopeBetween`; `SalesSchedule`: `belongsTo(lead,assignee,creator)`; `FollowUp/Poc`: `belongsTo(customer+withTrashed,lead,creator)`.
- WA: `WhatsappAccount hasMany(messages) belongsTo(assignee)`; `WhatsappMessage belongsTo(account,lead)`; `WhatsappConversation belongsTo(account,handler)`.
- KB/Produk: `Product hasMany(sources,documents,publishedDocuments,proposalItems)`; `ProductSource belongsTo(product) hasMany(documents)`; `KnowledgeDocument belongsTo(source,product) hasMany(chunks)`; `KnowledgeBaseDocument belongsTo(uploader,project)`.
- `ProjectObserver`: created → `ProjectActivity` + customer `lead→deal`; updated(status) → activity; deleted → `saveQuietly(deleted_by)` + cascade soft-delete tasks/supports/documents; restored → restore anak. `CustomerObserver`: deleted → cascade projects+contacts; restored → restore.

### 4.3 Default role/user/permission (seeder)
Lihat tabel §1.2. `RoleAndPermissionSeeder`: 10 `manage/view-*` + `view-customer/view-trash/monitor-marketing/monitor-technical/manage-sales-leads/manage-inside-sales`; mapping legacy (`teknisi→technician`, `manager→ceo`, `manage-*→management`), `personMap` Syifa/Hanifah, proteksi super-admin (ingat `prevSuperAdmins`, restore by `role` column). `DatabaseSeeder` urutan: role → master (AM, WorkType, 1 Customer Telemaju, 5 DocumentCategory, `WhatsappAccountSeeder` 4 akun + 6 pesan contoh) → `SampleDataSeeder` → `CompanyUserSeeder` (24 akun riil, password awal sama, `syncRoles`, restore soft-delete). `ProductSeeder`: 16 SKU awal (CCTV/jaringan/PABX/jasa, `status=approved,is_active=true`). `DocumentCategorySeeder/WorkTypeSeeder` duplikat ringan dari DatabaseSeeder.

---

## 5. ARSITEKTUR CONTROLLER & LOGIKA BISNIS KRUSIAL

| Komponen | Lokasi / ukuran | Tanggung jawab & alur data |
|---|---|---|
| `LeadController` (god, **1106 baris**) | `app/Http/Controllers/LeadController.php` | `index` (eager `customer,assignee,partner`, filter search/status/source/pt/date, paginate 15), `pipeline` (own-lead + virtual New via `acknowledged_at`, filter won/lost mingguan in-memory), `updateStatus/batchUpdateStatus` (auth `authorizeLeadStatus` + `guardSalesAssignee`), `store/update` (validasi + `saveAttachments/syncAttachments` private disk + `logActivity` + `notifyAssignee`), `convert` (lead→Project + activity), `saveOutcome/categorizeAttachment`, `import` (PhpSpreadsheet, `normalizeImportRow`), `monitoring/activities`, `preview/downloadDocument` |
| `ManageSalesController` (481) | `ManageSalesController.php` | Hub management: `index` (semua lead + filter), `assign/update` + `trackAssignment` + `notifyAssignee/clearLeadNotifications/logActivity`, `dashboard` (KPI sales), `myLeads` + `exportMyLeads/exportDar` (PhpSpreadsheet), `activityLog` (paginate 20 `lead_activities`) |
| `ProposalController` (503) | `ProposalController.php` | State machine §1.3 + `authorizeProposal/Edit/Technical` (sales own-lead vs teknisi), `generateNumber()`, `itemRules/persistItems` (BOQ picker `products` + `subtotalFor`), `recalculate`, `uploadPo/downloadPo` (private), `convertToProject` (transaksi lead.won + project + activity). Notif `ProposalStatusNotification` |
| `TechnicalRequestController` (299) | `TechnicalRequestController.php` | `store` (sales, lampiran private) → `review/assign/progress/result/complete/cancel` (teknisi, `authorizeWork`), `ensureSurvey` (auto-survey tipe survey), `log`, `downloadAttachment`. Notif 3 tipe TR + `SurveyCreated/ReportUploaded` |
| `SalesScheduleController` (303) | `SalesScheduleController.php` | `ownScope` (sales-like hanya miliknya), `authorizeSchedule/Manage`, FullCalendar `getSchedulesApi` (range + overlap check), `complete/cancel`, `customerOptions/leadItems` (duplikat — §6). Notif `SalesScheduleAssigned` |
| `ProjectController` (157) + `ProjectService` (63) | `ProjectController/Service/Project` | `index` (`with customer,workType,status`, paginate 15 + count aktif via `whereHas status`), `store/update` via service (generate `project_code`? — cek service: create/update/delete + observer), `show` (`load customer,workType,accountManager,tasks,documents,supports,activities`), `destroy` (soft → Trash) |
| `TechnicianScheduleController` (399) + `GoogleCalendarService` (670) + `CalendarController` | teknisi kalender | Tersulit: OAuth (`connect/callback/disconnect`, `saveCredentials`, `clientFor/handleCallback`), `syncNow/syncTwoWay/syncFromGoogle/pushLocalChanges` (idempoten via `google_event_id`, `parseEventTime`, `buildPayload/Description/Reminders`), `events/getSchedulesApi/schedulesForRange/validateSchedule`, `complete/store/update/destroy` + `notifyTechnician`, `autoSyncSoon/canManageApi`, `SyncGoogleCalendar(gcal:sync)`, `SendScheduleReminders` |
| `SurveyController` (376) | `SurveyController.php` | `index` (filter project/status), `store/update` (JSON `documentation`), `uploadReport/viewReport/downloadReport` (`Storage::disk('private')`, `SecureFile::serverMime`, `reportFileName`, `guardRead/isSalesRole`), `destroy` |
| `Sizing/RequestHarga/InstalasiController` (~95) + `DocumentRepositoryController` (27) | teknisi workflow | CRUD tipis `with('project.customer')`, select project `limit 200`; repo dokumen lintas project (filter kategori/search) |
| `WhatsAppCenterController` (**993**, terbesar) + `WhatsappGateway` (228) + `WhatsappBot` (316) | WA Center | `index/status/checkStatus/conversations(senderUnreads)/messages/contacts/saveContact/markRead/preference/store reply/takeover/release/convert(WA→Lead+prefill)/verifyWebhook/webhook/handleNotification/handleMetaNotification/handleIncoming/handleOutgoingStatus/storeInbound/updateCredentials/updateBotSettings/simulate/visibleAccounts`; gateway dual Green/Meta (`sendText*/getState*/receive/acknowledge`, token encrypted); bot (`replyPending/pendingSenders/replyTo/takeover/release/remindWaitingHumans/generateReply/transcript/notifyHandoff`, `WhatsappSalesBot` agent + `LeadNeedsSummarizer`); command `whatsapp:receive/bot` tiap 30 dtk; notif `WhatsappInbound/Handoff` |
| `AdminController` (231) + `AdminService` (168) | admin keuangan | `invoices*/pos*/payments*` (numbering, `assertProjectBelongsToCustomer`, proof private). `Customer/CustomerContactController` (190/121, NPWP private `storeNpwp/downloadNpwp`, `togglePrimary`) |
| `MonitoringController` (265) | monitoring | 2-fase anti-N+1: fase1 `withMax` 5 timestamp + sort in-memory + paginate manual 15, fase2 `with(leads,followUps,projects.documents+status,invoices,pos)` + `getLatestActivity/getStats/getAllStatuses` |
| `Dashboard/TechnicianDashboardController` (49/110) | dashboard | `DashboardController@index` (KPI per role), teknisi (`splitTechniciansByActivity`, running projects, heartbeat di file legacy `teknisi.php` — tidak ada di `web.php`) |
| `FollowUp/PocController` (242/170) + `SalesService` (163) | sales tracker | CRUD + `complete/reopen/snooze/reschedule`, `scopeToOwnLeads`, `leadOptions/customerOptions/customerLabel/leadItems/assertConsistentRelations` (duplikat 3 controller) |
| `OfficeAssistantController` (322) + `Ai/Agents/Tools` | AI | `index/send` (throttle 30, upload 30MB `storeAttachments`, `promptResilient`, `messagePayload/conversationPayload`), agent `OfficeAssistant(Conversational,HasTools)` + 7 Tools user-scoped (`GetMyTasks/LatestProjects/ProjectDetails/Progress/LeadDetails/SalesSummary/ProductKnowledge`) |
| `KnowledgeBase/ProductKnowledgeController` (190/237) + `ProductKnowledgePipeline` (330) + Jobs | RAG | import URL/file → `FirecrawlService/ProductExtractor` → `matchOrCreateProduct/storeDocument/buildDescription/chunk/cleanContent/contentHash` → `knowledge_documents/chunks` (publish→AI), job `ImportProductSource/PollProductCrawl`, command `knowledge-base:*` |
| `TrashController` (268) + Policies | trash | `index/ownTrash/allTrash/restore/destroy/clear/findTrash/customerOwnerMap/owned*/ptWritable*/ownerMaps/ownerFilterUsers`; `Customer/Project/Lead/MonitoringPolicy` + `PtAccess::userPts/isSalesLike/canWritePt` |
| `AdminPanelController` (245) + `Account/WorkType/DocumentCategory/ProjectStatusController` + Livewire | super-admin | users/roles CRUD (+`guardSuperAdminGrant/LastSuperAdminDemote`), `auditLog`, master CRUD; `ProjectStatusList` (Livewire tabel+sort) |
| `NotificationController/PushSubscription/Settings/ProfileController` | util | 15 notifikasi (`LeadAssigned/NewLead/FollowUpOverdue/LeadTask/ProposalStatus/SalesSchedule*/Survey*/Technical*/TechnicianSchedule*/Whatsapp*`), VAPID, locale/appearance/advanced, avatar/cover GD normalize |
| Console | `app/Console/Commands` (9) | `schedules:remind, followups:remind, proposals:expire, gcal:sync, whatsapp:receive/bot, knowledge-base:init-store/sync-status, files:migrate-private` + `routes/console.php` scheduler |

---

## 6. POTENSI FITUR MASALAH / BELUM EFISIEN (INITIAL CHECK — temuan kode, bukan tuduhan)

> Semua poin di bawah hasil baca kode (`file:line` pendekatan). Prioritas `P0`=betulkan dulu, `P1`=refactor dekat, `P2`=higiene.

1. **`P0 — `Lead::projects()` NPE bila customer null** (`app/Models/Lead.php:115-118`: `return $this->customer->projects();`). Relasi delegasi tanpa null-guard; pemanggil yang mengira relasi Eloquent akan fatal bila `customer_id` yatim. Perbaiki: `return $this->hasManyThrough(Project::class, Customer::class, 'id', 'customer_id', 'customer_id', 'id')` atau guard + tes.
2. **`P0 — File route duplikat tak ter-load** (`routes/{admin,admin-panel,monitoring,marketing,teknisi}.php` tidak di-require di `bootstrap/app.php`; `api.php` dikomentari). Risiko: developer edit file salah → perubahan "hilang". Perbaiki: hapus file legacy ATAU require + hapus duplikat di `web.php`; tambah test `route:list` snapshot.
3. **`P0 — `support_technicians` CSV + `pic_engineer` string bebas** (`projects` migrasi `add_support_technicians`, `change_pic_engineer_to_string`; `ProjectController@store` `implode(', ')`). Tidak bisa join/filter/schedule-notify per teknisi pendukung, rawan typo. Perbaiki: tabel pivot `project_technician(user_id,role)` + migrasi data CSV.
4. **`P1 — God controllers**: `WhatsAppCenterController` 993 baris, `LeadController` 1106, `ProposalController` 503, `ManageSalesController` 481, `GoogleCalendarService` 670. Webhook+inbox+convert+kredensial+bot-setting campur di 1 controller; `LeadController` campur CRUD+pipeline+import+attachment+dokumen. Perbaiki: pecah `Whatsapp/{Inbox,Webhook,Convert,Credential}Controller`, `Lead/{Crud,Pipeline,Import,Attachment}Controller`, FormRequest + Action class.
5. **`P1 — Duplikat logic opsi lead/customer** (`FollowUpController::leadOptions/customerOptions`, `PocController::leadOptions/scopeToOwnLeads`, `SalesScheduleController::customerOptions/leadItems`, `Sizing/RequestHargaController` select `limit 200` identik). Perbaiki: satu `LeadOptionService` + `scopeOwnLead($q,$user)`.
6. **`P1 — N+1 & pagination in-memory yang kentara**:
   - `LeadController@pipeline` (`:69-99`): `get()` semua own-lead lalu `filter` New + won/lost mingguan di PHP — berat bila sales >ribu lead. Dorong ke SQL (`where acknowledged_at` + `where closed_at`).
   - `LeadController@show` (`:487-523`): `load` 11 relasi + `flatMap->documents` semua project customer + timeline/schedule/request/proposal di-sort di PHP. Batasi kolom + paginate timeline.
   - `ManageSalesController@dashboard` (`:159-253`): 5× `limit(5)->get()` + agregasi PHP; gabung ke 1 query grup.
   - `WhatsAppCenterController@conversations` (`:88-128`): `get()` pesan + `Customer::whereIn->get()->keyBy` + `senderUnreads` query per akun; tambah counter cache `unread_count`.
   - `ProjectController@index`: 3 query (`paginate` + `count()` + `whereHas count`) — gabung via `withCount`/cache 60 dtk.
   - Positif: `MonitoringController` sudah 2-fase (`withMax` + eager 15) — jadikan pola baku.
7. **`P1 — Queue via scheduler tiap menit** (`routes/console.php:20` `queue:work --stop-when-empty --max-time=50` everyMinute + komentar "tanpa supervisor"). Overlap worker, notif (`NewLeadNotification` dkk) bisa telat/duplikat, log bising. Perbaiki: supervisor `queue:work --daemon` / Horizon + `withoutOverlapping` + alert gagal.
8. **`P1 — Upload & storage privat belum seragam**: Survey/NPWP/TR-attachment/PO/payment-proof memakai `Storage::disk('private')` (bagus) tapi validasi size/mime tersebar di controller, `report_file_name` + `report_file` ganda, `MigrateFilesToPrivate` sekali jalan tanpa re-run guard. Perbaiki: `SecureUpload` action + FormRequest + test mime-spoof.
9. **`P1 — Proposal numbering & total tanpa lock** (`ProposalController@generateNumber`, `Proposal::recalculate` `sum('subtotal')` lalu `save`). Race 2 sales bersamaan → nomor ganda/total selisih. Perbaiki: `DB::transaction` + `lockForUpdate` + unique retry.
10. **`P2 — `Lead::projects()` + `Project::status()` naming**: `status()` padahal FK `project_status_id` (Eloquent mengira kolom `status_id` bila dipakai sembarang); `scopeActive` benar (`is_active` ada) tapi tak dipakai konsisten. Standarkan `status()` + scope `ordered`.
11. **`P2 — Meeting tables di-drop tapi referensi sisa**: `2026_10_12_141500_drop_meeting_tables` drop `meetings/meeting_drafts/follow_ups.meeting_id`; `down()`-nya membuat ulang struktur tanpa data + `add_performance_indexes::down()` masih `dropIndex` meetings (gagal bila rollback setelah drop). Tambah guard `Schema::hasTable`.
12. **`P2 — Produk dipakai ganda KB↔BOQ** (`products` + `proposal_module_phase1` menambah `unit/default_price/is_active` ke tabel AI-KB). Perubahan KB (auto-publish/crawl) bisa mengubah katalog harga. Perbaiki: pisah `catalog_products` ATAU kolom `origin(kb/manual)` + policy tulis terpisah.
13. **`P2 — Policy vs route mismatch**: `LeadPolicy::update/delete` hanya `manage-marketing`, tapi route `leads.update-status/batch-status/outcome/convert` dibuka ke sales/teknisi/admin (`authorizeLeadStatus` di controller menambal — tersebar). Satukan di Policy (`updateStatus`, `convert`) + test matriks role×route.
14. **`P2 — Scheduler/catalog fragility**: `gcal:sync` everyFiveMinutes + `whatsapp:*` everyThirtySeconds (butuh scheduler precision + `withoutOverlapping` OK) tapi tanpa monitoring gagal; `ExpireProposals` + `SendFollowUpReminders` tanpa idempotency key selain `reminder_sent_at`. Tambah `failed_job` alert + health `/up`.
15. **`P2 — i18n campur**: `LeadActivity::actionLabel`, `Proposal::statusLabel`, `FollowUp::typeLabel` memakai `__()` sebagian, view lama Indonesia (catatan `AGENTS.md §7`: rapikan bertahap + tombol English via `__()`). Konsistenkan + tambah `tests/Feature/LocaleTest`.
16. **`P2 — Tes ada tapi tak gate merge**: `tests/Feature` (~30 file: LeadLifecycle/Pipeline/Notification/InsideSales/DAR export/dll) bagus, tapi `AGENTS.md` mewajibkan `php artisan test` lulus sebelum merge — pastikan CI menjalankan + coverage untuk 5 flow §1.3.

### Lampiran kompatibilitas (untuk konsultan)
- **Butuh `.env`**: `APP_URL`, `DB_*` (sqlite file path prod), `GOOGLE_*` (client id/secret), `WHATSAPP_*` (Green/Meta token — kini encrypted di DB), `VAPID_*`, `AI_*` (laravel/ai provider key), `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`. Tanpa kredensial GCal/WA/AI, modul terkait graceful-degrade (`isApiReachable/configured/hasOAuthConnection`).
- **Build**: `npm run build` **hanya dari `/var/www/3dyapp` (main)** — `public/build` worktree di-symlink (peringatan `AGENTS.md §5`); Tailwind JIT perlu rebuild tiap tambah kelas.
- **Batasan Letsencrypt/proxy**: `trustProxies('*')` + `bindRequestScheme()` mengandalkan `X-Forwarded-Proto`; pastikan nginx mengirim header tersebut.

> Akhir dokumen. File digenerate dari baca langsung: `composer.json/lock`, `package.json`, `vite.config.js`, `bootstrap/app.php`, `routes/*.php`, `app/Models/*.php`, `app/Http/Controllers/*.php`, `app/{Services,Observers,Policies,Support,Ai,Console}/*`, `database/{migrations,seeders}/*`, `resources/views` (192 blade), `tests/Feature`.
