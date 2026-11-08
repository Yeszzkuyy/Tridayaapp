# PROJECT SPEC AUDIT — 3DY App (PT Tridaya)

> Hasil bedah codebase menyeluruh dan konsolidasi arsitektur per **November 2026** (pembaruan audit dari versi 15 Oktober 2026).
> Branch `main`, workdir `/var/www/3dyapp`.
> Framework: Laravel **13.34.0** (constraint `^13.8`), PHP **8.3.6**, DB default **sqlite** (`.env.example`: `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=file`).
> Dokumen spesifikasi teknis ini ditujukan untuk tim engineer internal dan konsultan arsitektur eksternal: memetakan model data, alur bisnis, integrasi modul, efisiensi fitur, dan status implementasi terkini.

---

## 1. RINGKASAN PROYEK & TUJUAN BISNIS

### 1.1 Gambaran Umum & Lingkup Sistem
**3DY App** adalah sistem ERP operasional terpadu untuk grup **PT Tridaya** yang menaungi 4 entitas perseroan:
1. **NTI** (`PT Nusantara Tridaya Inovasi`)
2. **MGK** (`PT Mega Gada Kencana`)
3. **TPS** (`PT Tridaya Piranti Selaras`)
4. **WANI** (`PT Wiskira Adinata Indonesia`)
*(didefinisikan pada konstanta `Lead::PT_GROUPS` dan `WhatsappAccount::GROUPS`)*.

Sistem mengelola seluruh rantai nilai bisnis B2B end-to-end dalam arsitektur monolit modern (Blade + Alpine.js 3.4 + TailwindCSS 3.1 + React 19 Islands + Laravel Reverb WebSocket):
- **Akuisisi & Komunikasi:** Multi-inbox WhatsApp (4 nomor PT resmi) terintegrasi bot AI laravel/ai dan handoff human agent.
- **Manajemen Prospek (CRM):** Customer, Kontak PIC primary, Pipeline Lead berbasis tahapan *Cool / Warm / Hot / Won / Lost*, Sales Follow Up & Meeting Minutes (MOM + foto bukti), target sales berkala, dan penugasan presales (Technical Request).
- **Kolaborasi Presales & Penawaran:** Survey lapangan, Sizing perangkat, Request Harga & Price Request (chat/diskusi sales-teknisi), kalkulasi margin keuntungan & guard anti-Rp 0, hingga konversi Quotation deal (WON) berdokumen Customer PO resmi.
- **Pengadaan & Logistik (Paket 3 Procurement):** Handoff Sales → Admin/Purchasing terverifikasi (`po_verified_at`), Supplier PO terpisah dari Customer PO, Penerimaan Barang (Goods Receipt) dengan QC & nomor seri, serta Surat Jalan (Goods Delivery) ke pelanggan.
- **Eksekusi Teknik & Penutupan (Closing):** Manajemen Proyek, Task & Support tiket, Work Result (Instalasi & Survey), Berita Acara Serah Terima (BAST elektronik/fisik), dan garansi pemeliharaan.
- **Penagihan & Keuangan (Finance AR/AP):** Penagihan pelanggan via Invoice resmi ber-item dinamis dan faktur pajak, pelunasan bertahap/cicilan berbukti transfer rekening, serta pencatatan Hutang Usaha (Supplier Invoice AP) dan histori pembayaran ke pemasok.
- **Pusat Dokumen Terpadu (Document Center):** Pengindeksan dokumen lintas modul (8 cabang sumber data) dengan preview PDF instan tanpa unduh.

---

### 1.2 Target Pengguna & Matriks Hak Akses (RBAC)
Sistem menggunakan `spatie/laravel-permission` v8.3 yang dikelola melalui `RoleAndPermissionSeeder.php` dan `CompanyUserSeeder.php`. Pola hak akses dibagi menjadi izin aksi (`manage-{divisi}`), izin baca (`view-{divisi}`), serta izin domain sensitif (`view-admin-hub`, `view-supplier-pos`, `view-supplier-invoices`, `view-payment-proofs`, `view-goods-receipts`, `view-customer-finance`).

Super Admin memiliki bypass penuh melalui `Gate::before` di `AppServiceProvider`.

| Role | Permissions & Hak Akses Utama | Ruang Lingkup Operasional / User Seeded |
|---|---|---|
| `super-admin` | Seluruh permission sistem (bypass total) | Pemeliharaan sistem, developer, konfigurasi master, dan audit darurat (`yehezkielmayogi.ptnti@gmail.com`). |
| `management` | `manage-sales-leads`, `view-marketing`, `view-sales`, `view-technician`, `view-admin`, `view-monitoring`, `monitor-marketing`, seluruh `view-*-admin` | Pimpinan operasional: alokasi/assign lead ke tim sales, pemantauan Team Activity, Activity Log, target omzet, dan rekap seluruh divisi (Ardian, Christina, Yanita, Ayu). |
| `ceo` | Seluruh `view-*` (read-only lintas seluruh modul tanpa hak modifikasi) | Victor GM: Pengawasan eksekutif, rekapitulasi performa finansial, proyek, dan pipeline tanpa risiko perubahan data tidak sengaja. |
| `lead-marketing` | `manage-marketing`, `view-marketing`, `monitor-marketing`, `view-technician`, `view-customer`, `view-trash` | Syifa: Supervisi kanal masuk prospek, distribusi pesan WA Center, master partner/vendor marketing, dan akses foto instalasi lapangan. |
| `marketing` | `manage-marketing`, `view-marketing`, `view-customer`, `view-trash` | Anggie: Entri lead baru, pengelolaan partner, broadcast/chat WhatsApp Center, pemantauan status prospek. |
| `sales` / `sales-{pt}` | `manage-sales`, `view-sales`, `view-admin`, `view-customer`, `view-trash` *(khusus role `sales-{nti,mgk,tps,wani}`, hak tulis dibatasi via `App\Support\PtAccess`)* | Tim Sales (Adi Santosa, Hendry, Irfan, Deby, Audi, Dini): Pengelolaan customer, kontak PIC, jadwal meeting/kunjungan, request presales/harga, penyusunan Quotation, upload Customer PO, dan monitoring invoice deal-nya. |
| `inside-sales` | `manage-inside-sales`, `view-customer`, `view-trash` | Asisten sales: Pengerjaan tugas internal yang didelegasikan oleh sales (`lead_tasks`), perbantuan penyiapan berkas tanpa hak melihat keseluruhan pipeline sales lain. |
| `admin` | `manage-admin`, `view-admin`, `view-sales`, seluruh izin `view-*-admin`, `view-customer`, `view-trash` | Hanifah, Vanesha, April: Verifikasi kelengkapan Customer PO, penerbitan invoice penagihan pelanggan, validasi bukti transfer, manajemen Supplier PO, goods receipt, delivery order, dan rekapitulasi BAST. |
| `purchasing` | `manage-admin`, `view-admin`, `view-sales`, `view-admin-hub`, `view-supplier-pos`, `view-supplier-invoices`, `view-goods-receipts`, `view-customer-finance`, `view-customer`, `view-trash` | Divisi Pengadaan: Handoff PO deal, pemesanan barang ke supplier (`supplier_purchase_orders`), kontrol jadwal pengiriman barang dari vendor, pengecekan surat jalan/QC penerimaan. Dibatasi dari bukti rekening pribadi pelanggan (`view-payment-proofs`). |
| `finance` | `view-admin`, seluruh `view-*-admin`, `view-customer`, `view-trash` (read-only terhadap procurement, tanpa `manage-admin`) | Divisi Keuangan: Verifikasi tagihan pemasok (AP), pembayaran supplier, kontrol faktur pajak, kontrol piutang invoice pelanggan (AR), dan verifikasi rekening bank. |
| `lead-technician` | `manage-technician`, `view-technician`, `monitor-technical`, `view-sales`, `view-admin`, `view-customer`, `view-trash` | Amir, Ardian: Review tiket permintaan teknik (Technical Request), penugasan teknisi lapangan, supervisi jadwal kalender kerja, dan verifikasi laporan pekerjaan. |
| `technician` | `manage-technician`, `view-technician`, `view-customer`, `view-trash` | Khairil, Gilar, Ardi, Fanuel, Zero, Naufal, Ryan: Pelaksanaan survey lapangan, penyusunan sizing/topologi, request harga vendor, pengisian checklist instalasi, upload laporan kerja PDF, dan sinkronisasi Google Calendar. |
| `prakerin-*` | `view-technician` / `view-admin` + common read | Akun magang/prakerin: Read-only sesuai divisi untuk kebutuhan pembelajaran tanpa izin modifikasi data transaksi. |

---

### 1.3 Alur Kerja Operasional Utama (End-to-End Business Life-Cycle)

```
[Inbound WA / Marketing] ──> [Lead Ingestion & Assignment] ──> [Sales Presales Collaboration]
                                                                        │
┌───────────────────────────────────────────────────────────────────────┘
│
├──> [Technical Request / Sizing / Survey Lapangan / Request Harga]
│
└──> [Penyusunan Quotation / Proposal] (Margin & Cost Guard, Anti-Rp 0)
            │
            └──> [Customer PO Deal (WON)]
                        │
                        ▼ (Handoff Sales → Admin/Purchasing)
            [Admin PO Verification Gate] ───(Incomplete)───> [Returned to Sales with Notes]
                        │ (Verified)
                        ├─────────────────────────────────────────────────┐
                        ▼                                                 ▼
             [Supplier PO (Procurement)]                         [Project Execution]
                        │                                                 │
                        ▼                                                 ▼
             [Goods Receipt & QC Seri]                           [Instalasi & Field Work]
                        │                                                 │
                        ▼                                                 ▼
             [Goods Delivery (Surat Jalan)]                      [BAST Signoff Elektronik/Fisik]
                        │                                                 │
                        ├─────────────────────────────────────────────────┘
                        ▼
             [Customer Invoicing & Tax] ──> [Pembayaran / Cicilan] ──> [Closing & Warranty]
```

#### Alur Rinci Tiap Fase:
1. **Fase Presales & Tiket Teknik (`TechnicalRequest`):** Sales/Inside-Sales membuat tiket technical request terhubung dengan Lead. Lead-teknisi melakukan review (`waiting_lead → accepted`), assign teknisi eksekutor (`assigned`), teknisi memproses pekerjaan (`in_progress`), mengisi hasil teknis (`technical_result`), dan menyelesaikan tiket (`completed`). Tiket bertipe survey secara otomatis menginisiasi entri `Survey` terhubung.
2. **Fase Kolaborasi Harga & Presales:** Bila diperlukan harga khusus atau spek custom, Sales/Teknisi mengajukan `PriceRequest` (fitur chat realtime sales-teknisi-admin) atau `RequestHarga`/`SizingProject`. Data biaya modal (`unit_cost`) ditautkan ke katalog produk/supplier.
3. **Fase Penawaran Komersial (`Proposal` / Quotation):** Sales menyusun BOQ dengan proteksi ketat:
   - Dilarang membuat quotation kosong (wajib minimal 1 item BOQ berharga > Rp 0).
   - Sales tidak dapat memanipulasi atau merendahkan modal teknisi/supplier (`assertNoCostTampering`).
   - Sistem otomatis menghitung `subtotal`, `estimated_cost`, `margin_amount`, dan persentase margin laba.
   - Quotation dikirim via WhatsApp (dengan pencatatan otomatis status `sent_channel=whatsapp`) atau Email.
4. **Fase Kesepakatan & Handoff Customer PO:** Ketika pelanggan setuju (status `accepted`/WON), Sales mengunggah berkas PO pelanggan (`po_document_path`) beserta nomor PO, tanggal PO, batas deadline pengiriman, dan alamat pengiriman. Sistem otomatis mengantrikan penawaran ke antrean **Purchasing Handoff** (`/admin/purchasing`).
5. **Fase Gerbang Verifikasi Admin (Admin Verification Gate):** Admin/Purchasing memeriksa keabsahan dan kelengkapan Customer PO di Admin Hub:
   - Jika berkas buram atau data nomor/alamat tidak cocok, Admin menekan **Return to Sales** disertai catatan revisi tertulis (`po_returned_at`, `po_return_reason`). Status PO ditahan dari proses belanja maupun konversi proyek. Sales mendapat notifikasi dan form unggah ulang PO revisi.
   - Jika berkas dan data valid, Admin menekan **Verify PO** (`po_verified_at`, `po_verified_by`).
6. **Fase Pengadaan (`SupplierPurchaseOrder` & `GoodsReceipt`):** Admin/Purchasing menerbitkan PO resmi kepada Supplier (`admin/supplier-pos`) yang diturunkan dari Quotation terverifikasi. Saat barang tiba di gudang, dibuat dokumen `GoodsReceipt` mencatat nomor surat jalan vendor, kuantitas riil, hasil QC fisik, dan nomor seri barcode produk.
7. **Fase Logistik & Pengiriman Pelanggan (`GoodsDelivery`):** Admin Logistik menerbitkan Surat Jalan resmi (`admin/goods-deliveries`) mencantumkan nama driver, plat kendaraan, rincian barang, dan status pengiriman (`in_transit → delivered`).
8. **Fase Eksekusi Proyek & BAST (`Project` & `BastController`):** Quotation dikonversi menjadi Proyek. Teknisi melaksanakan instalasi, mencatat checklist pekerjaan dan foto dokumentasi. Setelah rampung, diterbitkan dokumen **BAST** (`admin/bast`) dengan tanda tangan pimpinan/klien serta penentuan masa garansi purnajual. Proyek bertipe jasa murni dapat dikecualikan dari kewajiban surat jalan fisik melalui toggle `requires_delivery = false`.
9. **Fase Penagihan & Keuangan (`Invoice`, `Payment`, `SupplierInvoice`):**
   - Admin Keuangan menerbitkan Invoice tagihan pelanggan (`admin/invoices`) yang kini dilengkapi tabel item dinamis (`invoice_items`), subtotal, diskon, PPN, nomor Faktur Pajak, dan template cetak resmi A4 (`/admin/invoices/{invoice}/print`).
   - Pelanggan dapat membayar bertahap (cicilan); tiap cicilan dicatat di `payments` dengan upload bukti transfer privat. Status invoice otomatis sinkron (`unpaid → partially_paid → paid`).
   - Invoice tagihan dari Supplier (Hutang Usaha / AP) dicatat di `supplier_invoices` dan histori pelunasannya dicatat pada `supplier_payments`.

---

## 2. TECH STACK & DEPENDENCIES

### 2.1 Backend Environment (`composer.json` & `composer.lock`)
| Paket / Pustaka | Versi Terpasang | Peran & Implementasi pada Kode |
|---|---|---|
| `php` | `8.3.6` CLI (constraint `^8.3`) | Bahasa pemrograman inti runtime server. |
| `laravel/framework` | **`13.34.0`** (constraint `^13.8`) | Core MVC Framework, Eloquent ORM, Database Queue Worker, Scheduled Crons. |
| `laravel/reverb` | **`^1.12`** | WebSocket Server real-time internal performa tinggi untuk broadcast notifikasi instan. |
| `laravel/socialite` | **`^5.31`** | Otentikasi pihak ketiga & Google Single Sign-On (`google_id` pada pengguna). |
| `livewire/livewire` | `^4.3` | Komponen interaktif reaktif: `ProjectStatusList` (master status) dan `LeadTaskChat`. |
| `spatie/laravel-permission` | `^8.3` | RBAC granular (roles, permissions, middleware alias, scope queries). |
| `google/apiclient` | `^2.19` | Integrasi 2-arah Google Calendar API v3 (`GoogleCalendarService`). |
| `laravel-notification-channels/webpush` | `^13.0` | Notifikasi browser push VAPID lintas platform (`HasPushSubscriptions`, `PushSubscriptionController`). |
| `laravel/ai` | `^0.8.0` | Asisten AI kantor (`OfficeAssistant`), tool-calling terintegrasi basis data, dan summarizer chat WA. |
| `phpoffice/phpspreadsheet` | `^5.9` | Mesin impor lead massal format XLSX/CSV (`LeadController@import`) dan ekspor laporan DAR / My Leads. |
| `laravel/breeze` | `^2.4` (dev) | Scaffolding autentikasi aman, password hashing Argon2id/Bcrypt. |
| `laravel/pail`, `pint`, `phpunit ^12.5` | dev tools | Debugging real-time terminal log viewer, PHP code linter, dan automated testing suite. |

---

### 2.2 Frontend Environment (`package.json` & `vite.config.js`)
| Pustaka Frontend | Versi | Penggunaan & Integrasi Komponen |
|---|---|---|
| `tailwindcss` + `@tailwindcss/forms` | `3.1.0` | Sistem utility CSS responsif, dukungan penuh tema Light & Dark Mode seragam. |
| `alpinejs` | `3.4.2` | Reaktivitas mikro di Blade: modal dialog, popover, inline form editing, filter drawer, tab switcher. |
| `vite` + `laravel-vite-plugin` | `8.0.0` | Bundler aset modern dengan code-splitting otomatis: `alpine`, `react`, `dnd`, `calendar`, `markdown`. |
| `react` + `react-dom` | `19.3.0` | Micro-frontend islands: `rubber-segment.jsx`, `glide-select.jsx`, dan visual canvas. |
| `motion` + `ogl` | `13.4.2` / `1.0.11` | Animasi fisika halus & WebGL background canvas di login page. |
| `@fullcalendar/*` | `6.1.21` | Kalender interaktif agenda teknisi (`teknisi-calendar.js`), drag-and-drop jadwal, multi-view agenda. |
| `sortablejs` | `1.15.7` | Drag-and-drop Kanban pipeline visual status leads (`pipeline-dnd.js`). |
| `driver.js` | `1.10.2` | Fitur panduan interaktif pengguna baru (onboarding tour pada menu My Leads dan Lead Flow). |
| `pdfjs-dist` | `6.4.299` | In-browser PDF previewer interaktif untuk dokumen BAST, survey report, dan PO tanpa perlu download lokal. |
| `fflate` | `0.8.3` | Kompresi dan ekstraksi file ZIP cepat di browser untuk download dokumen massal. |
| `laravel-echo` + `pusher-js` | `2.3.0` / `8.5.0` | Klien WebSocket untuk mendengarkan broadcast event Reverb secara realtime pada notifikasi bell. |
| `marked` + `dompurify` | `18.0.11` / `3.4.15` | Parser Markdown & sanitasi HTML aman pada output asisten AI kantor (`/ai/assistant`). |

#### Ringkasan Aset & Standar Desain UI/UX:
- **Total Blade Views:** **270 file** di direktori `resources/views/`.
- **Standar Tombol Wajib (`AGENTS.md §7`):**
  - Seluruh tombol wajib sudut lengkung **`rounded-xl`** (12px), feedback instan `active:scale-95`, dan efek kilau (*shine effect*).
  - Satu CTA primer per halaman menggunakan `<x-icon-button variant="solid">` atau animasi `<x-flow-button>` (port 21st.dev).
  - Navigasi kembali terstandarisasi menggunakan `<x-back-button>` (animasi panel aksen meluncur dari kiri).
  - Ikon tombol berlabel wajib menggunakan ukuran tegas **`h-4 w-4` dengan `stroke-width="3.5"`**.
  - Aksi tabel/baris wajib icon-only melalui `<x-icon-button>` berwarna solid tematik (misal: view indigo, edit amber, delete red, WA emerald).
- **Lynk Dashboard Style:** Open header sapaan tanpa border kotak kaku, kartu KPI dengan ambient corner glow dan kurva sparkline riil (`SparklineBuilder`), Side-by-Side Donut Chart ber-legend 100%, serta durasi transisi angka count-up 1.8 detik.

---

### 2.3 Runtime, Scheduler & Arsitektur Penyimpanan Privat
- **Database Engine:** SQLite (arsitektur file lokal terisolasi berkinerja tinggi pada VPS) dengan skema migration-tested kompatibel dengan MySQL 8.x / MariaDB.
- **Queue Worker:** Antrean berbasis database (`jobs`, `failed_jobs`). Notifikasi pengiriman email (Gmail SMTP) dan push broadcast dijalankan asinkron via `ShouldQueue` pada `PurchasingHandoffNotification`, `ProposalStatusNotification`, dan `TechnicianAssignedNotification`.
- **Scheduled Commands (`bootstrap/app.php`):**
  - `schedules:remind` (setiap menit) — Peringatan jadwal teknisi & pengiriman notifikasi.
  - `followups:remind` (setiap hari jam 08:00) — Peringatan tindak lanjut prospek sales yang jatuh tempo.
  - `proposals:expire` (setiap hari jam 01:30) — Menandai proposal expired bila melewati tanggal valid.
  - `gcal:sync` (setiap 5 menit) — Sinkronisasi dua arah agenda teknisi dengan Google Calendar.
  - `whatsapp:receive` & `whatsapp:bot` (setiap 30 detik) — Polling pesan masuk dan trigger AI Auto-reply.
- **Sistem Penyimpanan Privat (`disk private`):**
  - Direktori `storage/app/private/` memproteksi berkas sensitif: NPWP customer, lampiran tiket teknik, file laporan survey PDF, berkas BAST, surat jalan, Customer PO, bukti transfer pembayaran, dan faktur supplier.
  - Akses berkas melalui secure route ber-policy (`SecureFile::serverMime`), mencegah sniffing link atau direct download tanpa otentikasi sesi.

---

## 3. DAFTAR FITUR & STRUKTUR ROUTES (440 ROUTES)

> Seluruh rute aplikasi didefinisikan secara modular di dalam **`routes/web.php`** (di-load oleh `bootstrap/app.php`). Total terhitung **440 rute aktif**.
> File-file legacy pada `routes/*.php` lama telah dikonsolidasikan penuh ke dalam `web.php`.

### 3.1 Rute Autentikasi & Beranda Umum
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` | `/` | — | Redirect ke `/login` atau `/dashboard` | `web` |
| `GET` | `/dashboard` | `dashboard` | `DashboardController@index` | `auth, verified` |
| `GET` / `POST` | `/login`, `/logout` | `login`, `logout` | `Auth\AuthenticatedSessionController` | `guest` / `auth` |
| `GET` / `POST` | `/forgot-password`, `/reset-password` | `password.*` | `Auth\PasswordResetLinkController` dkk | `guest` |
| `GET` | `/auth/google`, `/auth/google/callback` | `auth.google.*` | `Auth\GoogleLoginController` | `guest` |

---

### 3.2 Profil, Preferensi & Pusat Notifikasi
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `PATCH` / `DELETE` | `/profile` | `profile.edit/update/destroy` | `ProfileController` | `auth` |
| `GET` / `PATCH` / `POST` | `/settings`, `/settings/appearance` | `settings.*` | `SettingsController` | `auth` |
| `GET` | `/notifications` | `notifications.index` | `NotificationController@index` | `auth` |
| `POST` | `/notifications/read-all` | `notifications.read-all` | `NotificationController@readAll` | `auth` |
| `POST` | `/notifications/{notification}/read` | `notifications.read` | `NotificationController@markAsRead` | `auth` |
| `POST` / `DELETE` | `/push-subscriptions` | `push-subscriptions.store/destroy` | `PushSubscriptionController` | `auth` |

---

### 3.3 Asisten AI Kantor & Pengetahuan Produk (RAG)
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` | `/ai/assistant` (throttle 30,1) | `ai.assistant.index/send` | `OfficeAssistantController@index/send` | `auth, permission:manage-admin` |
| `GET` / `PATCH` / `DELETE`| `/ai/assistant/conversations/{c}` | `ai.assistant.conversations.*` | `OfficeAssistantController` | `auth, permission:manage-admin` |
| `GET` / `POST` / `DELETE` | `/knowledge-base/*` | `knowledge-base.*` | `KnowledgeBaseController` | `auth, permission:manage-admin` |
| `GET` / `POST` / `PATCH`  | `/product-knowledge/*` | `product-knowledge.*` | `ProductKnowledgeController` | `auth, permission:manage-admin` |

---

### 3.4 Modul Sales — Pelanggan, Kontak PIC, Follow-Up & Jadwal
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` | `/customers/create`, `/customers` | `customers.create/store` | `CustomerController` | `manage-sales` |
| `GET` / `PUT` / `DELETE` | `/customers/{customer}/edit`, `/{customer}` | `customers.edit/update/destroy` | `CustomerController` | `manage-sales` |
| `GET` / `POST` / `PUT` | `/customer-contacts/*` | `customer-contacts.*` | `CustomerContactController` | `manage-sales` |
| `PATCH` | `/customer-contacts/{contact}/primary` | `customer-contacts.primary` | `CustomerContactController@togglePrimary` | `manage-sales` |
| `GET` | `/customers`, `/customers/{customer}` | `customers.index/show` | `CustomerController` | `view-sales\|manage-sales\|view-customer` |
| `POST` | `/customers/{customer}/documents` | `customers.documents.store` | `CustomerController@storeDocument` | `manage-sales\|manage-technician` |
| `GET` / `POST` / `PUT` | `/sales/follow-ups/*` | `sales.follow-ups.*` | `FollowUpController` | `manage-sales` |
| `POST` | `/sales/follow-ups/{f}/{complete,reopen,snooze}`| `sales.follow-ups.*` | `FollowUpController` | `manage-sales` |
| `GET` / `POST` / `PUT` | `/sales/meetings/*` | `sales.meetings.*` | `SalesMeetingController` | `manage-sales` |
| `GET` / `POST` / `PUT` | `/sales/schedules/*` | `sales.schedules.*` | `SalesScheduleController` | `manage-sales\|manage-inside-sales` |
| `GET` | `/sales/schedules/api` | `sales.schedules.api` | `SalesScheduleController@getSchedulesApi` | `view-sales\|manage-sales` |
| `GET` / `POST` | `/sales/pocs/*` | `sales.pocs.*` | `PocController` | `manage-sales` |
| `POST` | `/manage-sales/targets` | `manage-sales.targets.update`| `ManageSalesController@updateTarget` | `manage-sales-leads` |

---

### 3.5 Sales Flow — Technical Request, Price Request, Quotation & Customer PO
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` | `/sales/technical-requests/create`, `""` | `sales.technical-requests.create/store` | `TechnicalRequestController` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/technical-requests/{tr}/{review,assign,progress,result,complete}` | `sales.technical-requests.*` | `TechnicalRequestController` | `manage-technician` |
| `GET` / `POST` / `PUT` | `/sales/price-requests/*` | `sales.price-requests.*` | `PriceRequestController` | `manage-sales\|manage-technician` |
| `POST` | `/sales/price-requests/{pr}/comments` | `sales.price-requests.comments.store` | `PriceRequestController@storeComment` | `auth` |
| `GET` / `POST` / `PUT` | `/sales/proposals/create`, `""`, `/{p}/edit` | `sales.proposals.create/store/edit/update` | `ProposalController` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/proposals/{p}/ready` | `sales.proposals.ready` | `ProposalController@markReady` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/proposals/{p}/send` | `sales.proposals.send` | `ProposalController@send` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/proposals/{p}/sent-via/{channel}` | `sales.proposals.sent-via` | `ProposalController@markSentVia` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/proposals/{p}/revise` | `sales.proposals.revise` | `ProposalController@revise` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/proposals/{p}/respond` | `sales.proposals.respond` | `ProposalController@respond` | `manage-sales\|manage-inside-sales` |
| `POST` | `/sales/proposals/{p}/po` | `sales.proposals.upload-po` | `ProposalController@uploadPo` | `manage-sales\|manage-inside-sales` |
| `GET` | `/sales/proposals/{p}/po` | `sales.proposals.download-po` | `ProposalController@downloadPo` | `view-sales\|manage-sales\|view-admin` |
| `POST` | `/sales/proposals/{p}/convert` | `sales.proposals.convert` | `ProposalController@convertToProject` | `manage-sales\|manage-admin\|manage-sales-leads` |

---

### 3.6 Presales & Alur Kerja Teknisi (Survey, Sizing, Request Harga, Instalasi)
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` / `PUT` | `/teknisi/surveys/*` | `teknisi.surveys.*` | `SurveyController` | `manage-technician` |
| `POST` | `/teknisi/surveys/{s}/report` | `teknisi.surveys.report.upload` | `SurveyController@uploadReport` | `manage-technician` |
| `GET` | `/teknisi/surveys/{s}/report` | `teknisi.surveys.report.view` | `SurveyController@viewReport` | `view-technician\|manage-technician\|view-sales` |
| `GET` / `POST` / `PUT` | `/teknisi/sizing-projects/*` | `teknisi.sizing-projects.*` | `SizingProjectController` | `manage-technician` |
| `GET` / `POST` / `PUT` | `/teknisi/request-hargas/*` | `teknisi.request-hargas.*` | `RequestHargaController` | `manage-technician` |
| `GET` / `POST` / `PUT` | `/teknisi/instalasis/*` | `teknisi.instalasis.*` | `InstalasiController` | `manage-technician` |
| `GET` | `/teknisi/field-work` | `teknisi.field-work.index` | `FieldWorkController@index` | `view-technician\|manage-technician` |
| `GET` | `/teknisi/dashboard` | `teknisi.dashboard` | `TechnicianDashboardController@index` | `view-technician\|manage-technician` |
| `GET` / `POST` / `PUT` | `/teknisi/jadwal/*` | `teknisi.jadwal.*` | `TechnicianScheduleController` | `manage-technician` |
| `POST` | `/teknisi/kalender/sync` | `teknisi.kalender.sync` | `TechnicianScheduleController@syncNow` | `manage-technician` |

---

### 3.7 Manajemen Proyek & Berkas Proyek
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` / `PUT` / `DELETE` | `/projects/*` | `projects.*` | `ProjectController` | `manage-technician\|manage-admin` (tulis) |
| `GET` | `/projects`, `/projects/{p}` | `projects.index/show` | `ProjectController` | `view-technician\|manage-technician\|view-sales` |
| `POST` | `/projects/{p}/tasks` | `projects.tasks.store` | `ProjectTaskController@store` | `manage-technician` |
| `POST` | `/projects/{p}/supports` | `projects.supports.store` | `ProjectSupportController@store` | `manage-technician` |
| `GET` / `POST` / `DELETE` | `/project-documents/*` | `project-documents.*` | `ProjectDocumentController` | `manage-technician\|manage-sales` |
| `GET` | `/project-documents/{doc}/download` | `project-documents.download` | `ProjectDocumentController@download` | `auth` |

---

### 3.8 Logistik & Surat Jalan (`GoodsDelivery`)
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` | `/admin/goods-deliveries/create`, `""` | `admin.goods-deliveries.create/store` | `GoodsDeliveryController` | `manage-admin` |
| `POST` | `/admin/goods-deliveries/{gd}/status` | `admin.goods-deliveries.status` | `GoodsDeliveryController@updateStatus` | `manage-admin` |
| `GET` | `/admin/goods-deliveries`, `/{gd}` | `admin.goods-deliveries.index/show` | `GoodsDeliveryController` | `view-admin\|manage-admin` |

---

### 3.9 Pengadaan, Supplier & Penerimaan Barang (Purchasing)
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` / `PUT` / `DELETE` | `/admin/suppliers/*` | `admin.suppliers.*` | `SupplierController` | `manage-admin` (tulis), `view-supplier-pos` (baca) |
| `GET` / `POST` | `/admin/supplier-pos/create`, `""` | `admin.supplier-pos.create/store` | `SupplierPurchaseOrderController` | `manage-admin` |
| `POST` | `/admin/supplier-pos/{spo}/issue` | `admin.supplier-pos.issue` | `SupplierPurchaseOrderController@issue` | `manage-admin` |
| `POST` | `/admin/supplier-pos/{spo}/cancel` | `admin.supplier-pos.cancel` | `SupplierPurchaseOrderController@cancel` | `manage-admin` |
| `GET` | `/admin/supplier-pos`, `/{spo}` | `admin.supplier-pos.index/show` | `SupplierPurchaseOrderController` | `view-supplier-pos\|manage-admin` |
| `GET` / `POST` | `/admin/goods-receipts/create`, `""` | `admin.goods-receipts.create/store` | `GoodsReceiptController` | `manage-admin` |
| `POST` | `/admin/goods-receipts/{gr}/verify` | `admin.goods-receipts.verify` | `GoodsReceiptController@verify` | `manage-admin` |
| `GET` | `/admin/goods-receipts`, `/{gr}` | `admin.goods-receipts.index/show` | `GoodsReceiptController` | `view-goods-receipts\|manage-admin` |
| `GET` | `/admin/goods-receipts/{gr}/attachment` | `admin.goods-receipts.attachment` | `GoodsReceiptController@attachment` | `view-goods-receipts\|manage-admin` |
| `GET` | `/admin/purchasing/reports` | `admin.purchasing.reports` | `PurchasingReportController@index` | `view-admin\|manage-admin` |

---

### 3.10 Admin Hub, Handoff Verifikasi & BAST Closing
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` | `/admin/hub` | `admin.hub` | `AdminController@hub` | `view-admin-hub\|manage-admin` |
| `GET` | `/admin/purchasing` | `admin.purchasing.index` | `PurchasingHandoffController@index` | `view-admin\|manage-admin` |
| `GET` | `/admin/purchasing/{proposal}` | `admin.purchasing.show` | `PurchasingHandoffController@show` | `view-admin\|manage-admin` |
| `POST` | `/admin/purchasing/{proposal}/verify-po` | `admin.purchasing.verify-po` | `PurchasingHandoffController@verifyPo` | `manage-admin` |
| `POST` | `/admin/purchasing/{proposal}/return-po` | `admin.purchasing.return-po` | `PurchasingHandoffController@returnPo` | `manage-admin` |
| `GET` / `PUT` | `/admin/bast/{project}/edit`, `/{project}` | `admin.bast.edit/update` | `BastController` | `manage-admin` |
| `GET` | `/admin/bast`, `/admin/bast/{p}/document` | `admin.bast.index/document` | `BastController` | `view-admin\|manage-admin` |

---

### 3.11 Keuangan & Penagihan (Finance AR / AP)
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` / `PUT` / `DELETE` | `/admin/invoices/*` | `admin.invoices.*` | `AdminController` | `manage-admin` (tulis), `view-admin` (baca) |
| `GET` | `/admin/invoices/{invoice}/print` | `admin.invoices.print` | `AdminController@invoicesPrint` | `view-admin\|manage-admin` |
| `GET` / `POST` / `DELETE` | `/admin/payments/*` | `admin.payments.*` | `AdminController` | `manage-admin` (tulis), `view-admin` (baca) |
| `GET` | `/admin/payments/{payment}/proof` | `admin.payments.proof` | `AdminController@paymentsProof` | `view-payment-proofs` |
| `GET` / `POST` | `/admin/supplier-invoices/create`, `""` | `admin.supplier-invoices.create/store` | `SupplierInvoiceController` | `manage-admin` |
| `POST` | `/admin/supplier-invoices/{si}/cancel` | `admin.supplier-invoices.cancel` | `SupplierInvoiceController@cancel` | `manage-admin` |
| `POST` | `/admin/supplier-invoices/{si}/payments` | `admin.supplier-invoices.payments.store` | `SupplierPaymentController@store` | `manage-admin` |
| `GET` | `/admin/supplier-invoices`, `/{si}` | `admin.supplier-invoices.index/show` | `SupplierInvoiceController` | `view-supplier-invoices\|manage-admin` |
| `GET` | `/admin/supplier-invoices/{si}/file` | `admin.supplier-invoices.file` | `SupplierInvoiceController@file` | `view-supplier-invoices\|manage-admin` |
| `GET` | `/admin/supplier-payments/{sp}/proof` | `admin.supplier-payments.proof` | `SupplierPaymentController@proof` | `view-supplier-invoices\|manage-admin` |

---

### 3.12 Document Center (Pusat Dokumen Lintas Modul)
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` | `/documents` | `documents.index` | `DocumentCenterController@index` | `auth, permission:view-customer` |
| `PATCH` | `/documents/{document}/category` | `documents.update-category` | `DocumentCenterController@updateCategory` | `auth, permission:view-customer` |
| `PATCH` | `/documents/{document}/type` | `documents.update-type` | `DocumentCenterController@updateType` | `auth, permission:view-customer` |

---

### 3.13 Pemasaran, WhatsApp Center, Manajemen & Admin Panel
| Metode | URI | Nama Rute | Controller & Method | Middleware |
|---|---|---|---|---|
| `GET` / `POST` / `PUT` / `DELETE` | `/leads/*` | `leads.*` | `LeadController` | `manage-marketing\|manage-sales` |
| `GET` | `/leads/pipeline` | `leads.pipeline` | `LeadController@pipeline` | `view-sales\|manage-sales` |
| `GET` / `POST` | `/leads/import` | `leads.import/import.execute` | `LeadController@importForm/import` | `manage-marketing` |
| `GET` / `POST` / `PUT` | `/whatsapp-center/*` | `whatsapp-center.*` | `WhatsAppCenterController` | `manage-marketing` (tulis), `view-marketing` (baca) |
| `GET` / `POST` | `/api/whatsapp/webhook` | `whatsapp.webhook` | `WhatsAppCenterController@webhook` | Bypass CSRF, throttle `60,1` |
| `GET` | `/manage-sales/activity-log` | `manage-sales.activity-log` | `ManageSalesController@activityLog` | `manage-sales-leads` |
| `GET` | `/manage-sales/team-activity` | `manage-sales.team-activity`| `ManageSalesController@teamActivity` | `manage-sales-leads` |
| `GET` | `/monitoring` | `monitoring.index` | `MonitoringController@index` | `view-monitoring\|manage-monitoring` |
| `GET` / `POST` / `PUT` | `/admin-panel/*` | `admin-panel.*` | `AdminPanelController` dkk | `manage-monitoring` (Super Admin only) |
| `GET` / `PATCH` / `DELETE`| `/trash/*` | `trash.*` | `TrashController` | `view-trash` (enforced by Policy) |

---

## 4. SKEMA DATABASE & MODEL RELASIONAL

### 4.1 Tabel Database & Migrasi Kunci Pasca 15 Oktober 2026
Sebanyak 60+ migrasi ditambahkan sejak 15 Oktober 2026 yang mentransformasi kapabilitas sistem:

```
[2026_10_15] technician_schedule_user (Pivot Multi-Teknisi pada 1 Jadwal)
[2026_10_19] price_requests, comments, replies (Forum Penentuan Harga Sales & Teknisi)
[2026_10_20] customer_documents (Penyimpanan Dokumen Non-Proyek pada Level Customer)
[2026_10_22] document_activities & versioning (Audit Trail & Riwayat Versi Dokumen)
[2026_10_24] sales_targets (Target Omzet dan Lead Berkala Bulanan/Kuartalan)
[2026_10_27] proposals cost & margin (Guard Laba, Biaya Pokok, dan Customer PO Metadata)
[2026_10_27] suppliers & product_supplier (Master Vendor Pengadaan & Katalog Harga Beli)
[2026_10_27] supplier_purchase_orders & items (Pemesanan Barang ke Pemasok)
[2026_10_27] goods_receipts & items (Penerimaan Barang Gudang, QC Fisik, & Nomor Seri)
[2026_10_27] goods_deliveries & items (Surat Jalan Resmi Pengiriman ke Pelanggan)
[2026_10_27] supplier_invoices & payments (Pencatatan Hutang Usaha & Bukti Transfer AP)
[2026_10_27] projects bast & warranty fields (Berita Acara Serah Terima & Tanggal Garansi)
[2026_10_27] invoices tax & finance fields (Nomor Faktur Pajak & Termin Pembayaran)
[2026_10_28] proposal_attachments (Multi-Lampiran Berkas Pendukung Penawaran)
[2026_10_29] customer_contacts single_primary_guard (Integritas 1 PIC Utama per Pelanggan)
[2026_11_02] leads city & province (Normalisasi Lokasi Wilayah Prospek)
[2026_11_02] technician_schedules technical_request_id (Tautan Langsung Jadwal ke Tiket)
[2026_11_05] proposals po_verification fields (Gerbang Validasi & Return PO Sales-Admin)
[2026_11_05] projects requires_delivery flag (Bypass Surat Jalan untuk Proyek Jasa Murni)
[2026_11_05] invoice_items & subtotal/tax/discount (Tabel Item Dinamis & Mesin Hitung Faktur)
[2026_11_05] proposals sent_channel (Audit Pengiriman via WhatsApp vs Email)
```

#### Struktur Kolom Kunci pada Tabel-Tabel Utama:
1. **`proposals`:**
   - Identitas & Komersial: `proposal_number` (unique), `lead_id`, `created_by`, `status`, `proposal_date`, `valid_until`, `sent_at`, `sent_by`, `sent_channel` (`whatsapp|email|manual`).
   - Angka Finansial: `subtotal`, `discount`, `tax`, `grand_total`, `estimated_cost` (total modal), `margin_amount` (laba kotor), `margin_percentage`.
   - Customer PO & Validasi: `po_document_path`, `customer_po_number`, `customer_po_date`, `customer_po_deadline`, `delivery_address`, `po_verified_at`, `po_verified_by`, `po_returned_at`, `po_returned_by`, `po_return_reason`.
   - Termin: `payment_terms_type`, `payment_terms_days`, `delivery_terms`, `warranty_terms`, `internal_notes`.
2. **`proposal_items`:**
   - `proposal_id`, `product_id`, `supplier_id`, `description`, `quantity`, `unit`, `unit_price`, `unit_cost`, `subtotal`, `subtotal_cost`, `discount`.
3. **`suppliers` & `product_supplier`:**
   - `suppliers`: `name`, `code` (unique), `contact_person`, `phone`, `email`, `address`, `term_of_payment_days`, `tax_number`, `notes`, `is_active`.
   - `product_supplier` (pivot): `product_id`, `supplier_id`, `supplier_sku`, `last_purchase_price`, `currency`.
4. **`supplier_purchase_orders` & `items`:**
   - Master: `po_number` (unique), `supplier_id`, `proposal_id`, `project_id`, `pt_group`, `issue_date`, `expected_delivery_date`, `status` (`draft|issued|partially_received|received|cancelled`), `subtotal`, `tax`, `grand_total`, `created_by`.
   - Item: `supplier_purchase_order_id`, `product_id`, `description`, `quantity`, `unit`, `unit_price`, `subtotal`, `received_quantity`, `delivery_quantity`.
5. **`goods_receipts` & `items`:**
   - Master: `gr_number` (unique), `supplier_purchase_order_id`, `receipt_date`, `received_by`, `delivery_order_number` (surat jalan vendor), `attachment_path`, `notes`, `status` (`draft|verified`).
   - Item: `goods_receipt_id`, `supplier_purchase_order_item_id`, `quantity_received`, `serial_numbers` (JSON), `condition` (`good|damaged|rejected`), `notes`.
6. **`goods_deliveries` & `items`:**
   - Master: `delivery_number` (unique), `project_id`, `customer_id`, `delivery_date`, `recipient_name`, `driver_name`, `vehicle_plate`, `status` (`draft|in_transit|delivered|returned`), `tracking_notes`.
   - Item: `goods_delivery_id`, `description`, `quantity`, `unit`, `serial_numbers` (JSON), `notes`.
7. **`supplier_invoices` & `supplier_payments`:**
   - Faktur AP: `invoice_number` (unique per supplier), `supplier_id`, `supplier_purchase_order_id`, `invoice_date`, `due_date`, `subtotal`, `tax`, `grand_total`, `status` (`draft|verified|partially_paid|paid|cancelled`), `document_path`.
   - Pembayaran AP: `supplier_invoice_id`, `payment_date`, `amount`, `payment_method`, `proof_path`, `notes`, `created_by`.
8. **`invoices` & `invoice_items`:**
   - Header Invoice Pelanggan: `invoice_number` (unique), `customer_id`, `project_id`, `proposal_id`, `customer_po_number`, `amount` (grand total), `subtotal`, `discount`, `tax`, `tax_invoice_number` (e-Faktur), `tax_invoice_date`, `status` (`unpaid|partially_paid|paid|cancelled`), `issue_date`, `due_date`, `payment_terms`, `customer_name_snapshot`, `customer_address_snapshot`.
   - Item Dinamis: `invoice_id`, `description`, `quantity`, `unit`, `unit_price`, `discount`, `subtotal`.
9. **`projects` (Kolom BAST & Closing):**
   - `bast_number`, `bast_date`, `bast_signee_name`, `bast_signee_position`, `bast_document_path`, `bast_status`, `requires_installation` (boolean), `requires_delivery` (boolean default true), `warranty_start_date`, `warranty_end_date`, `closing_notes`, `closed_at`, `closed_by`.
10. **`price_requests`, `comments`, `replies`:**
    - `price_requests`: `lead_id`, `proposal_id`, `requested_by`, `project_name`, `customer_name`, `items` (JSON), `status` (`waiting_admin|processed|completed|cancelled`), `processed_by`, `completed_at`.

---

### 4.2 Relasi Antar Model Eloquent (60 Model Aktif)
- `Customer`: Memiliki banyak `contacts` (1 primary dijaga `CustomerContactService`), `projects`, `followUps`, `invoices`, `goodsDeliveries`, `documents`, dan `leads`.
- `Proposal`: Menghubungkan `lead`, `creator`, `items`, `attachments`, `project` (relasi 1-to-1 unik), serta `supplierPurchaseOrders`. Memiliki helper kalkulasi `recalculate()`, validasi kelengkapan `poCompletenessErrors()`, dan state checks `isReadyForPurchasing()`, `isPoVerified()`, `isPoReturned()`.
- `SupplierPurchaseOrder`: Menghubungkan `supplier`, `proposal`, `project`, `items`, `goodsReceipts`, dan `supplierInvoices`.
- `GoodsReceipt`: Menghubungkan `purchaseOrder`, penerima `receiver` (`User`), serta item rincian `items`.
- `GoodsDelivery`: Menghubungkan `customer`, `project`, serta item serial `items`.
- `Project`: Menghubungkan `customer`, `status`, `workType`, `proposal`, `tasks`, `supports`, `documents`, `surveys`, `sizingProjects`, `requestHargas`, `instalasis`, dan `goodsDeliveries`. Guard delegasi `Lead::projects()` dilindungi melalui relasi customer.
- `Invoice`: Menghubungkan `customer`, `project`, `items` (`InvoiceItem`), dan `payments` bertahap.

---

## 5. ARSITEKTUR CONTROLLER, SERVICE & LOGIKA BISNIS KRUSIAL

### 5.1 Inventaris Controller (58 Controller Aktif)
| Controller | Lokasi & Ukuran | Tanggung Jawab Utama & Alur Bisnis |
|---|---|---|
| `LeadController` | `app/Http/Controllers/` (1255 baris) | CRUD Lead, Pipeline Kanban DnD, filter multi-kriteria, log aktivitas, outcome lost/won, impor massal XLSX via PhpSpreadsheet. |
| `WhatsAppCenterController` | `app/Http/Controllers/` (1143 baris) | Pengelolaan 4 akun WA PT, webhook receiver Meta/Green API, live chat inbox, AI auto-reply bot, manual takeover & release, konversi chat menjadi Lead. |
| `ProposalController` | `app/Http/Controllers/` (873 baris) | Mesin status quotation komersial, pembuatan nomor otomatis, validasi modal anti-tampering, upload berkas Customer PO, proteksi quotation kosong, konversi ke Proyek. |
| `DashboardController` | `app/Http/Controllers/` (654 baris) | Dashboard modular berbasis role (Sales, Marketing, Admin, Teknisi, Management, CEO) dengan grafik Lynk Style. |
| `ManageSalesController` | `app/Http/Controllers/` (569 baris) | Dashboard performa sales, alokasi penugasan prospek, live Activity Feed, Team Activity harian, ekspor laporan DAR / My Leads. |
| `CustomerController` | `app/Http/Controllers/` (488 baris) | Pengelolaan direktori pelanggan 4 PT, dokumen legalitas NPWP privat, integrasi kontak PIC terpusat. |
| `ProjectDocumentController` | `app/Http/Controllers/` (476 baris) | Pengelolaan arsip dokumen proyek, sistem versioning (v1, v2..), dan integrasi unggah customer level. |
| `TechnicianScheduleController`| `app/Http/Controllers/` (460 baris) | Penjadwalan teknisi, integrasi kalender multi-teknisi (`technician_schedule_user`), sinkronisasi Google Calendar OAuth 2-arah. |
| `PriceRequestController` | `app/Http/Controllers/` (433 baris) | Kolaborasi penetapan harga beli/modal antara sales dan teknisi/admin dengan fitur komentar & balasan thread. |
| `AdminController` | `app/Http/Controllers/` (419 baris) | Admin Hub operasional, pembuatan Invoice pelanggan, pencatatan pembayaran cicilan, template cetak invoice A4. |
| `SurveyController` | `app/Http/Controllers/` (412 baris) | Pendataan survey lokasi, unggah foto dokumentasi, dan penerbitan berkas laporan survey PDF privat. |
| `TechnicalRequestController` | `app/Http/Controllers/` (411 baris) | Manajemen tiket bantuan teknik presales (review lead-teknisi, assign, pengerjaan, pelaporan hasil). |
| `LeadTaskController` | `app/Http/Controllers/` (389 baris) | Pendelegasian tugas ke Inside Sales, checklist pekerjaan, lampiran file pendukung. |
| `GoodsDeliveryController` | `app/Http/Controllers/` (374 baris) | Penerbitan Surat Jalan pengiriman barang ke pelanggan, tracking kurir/plat nomor, kontrol status sampai tujuan. |
| `TrashController` | `app/Http/Controllers/` (372 baris) | Manajemen keranjang sampah terpadu (Soft Delete Restore/Force Delete) dengan proteksi kepemilikan Policy. |
| `OfficeAssistantController` | `app/Http/Controllers/` (322 baris) | Antarmuka obrolan asisten AI internal (`laravel/ai`) terhubung 7 tool kueri basis data operasional. |
| `SalesScheduleController` | `app/Http/Controllers/` (312 baris) | Penjadwalan agenda meeting & call sales, feed API untuk kalender FullCalendar. |
| `DocumentCenterController` | `app/Http/Controllers/` (305 baris) | Antarmuka pencarian dan filter cepat dokumen lintas modul (8 cabang data) dengan panel preview instan. |
| `FollowUpController` | `app/Http/Controllers/` (301 baris) | Pelacakan interaksi prospek (telepon, WA, email, catatan), snooze, dan penjadwalan reminder. |
| `ProfileController` | `app/Http/Controllers/` (301 baris) | Edit profil pengguna, normalisasi avatar/cover via GD Image, pembaruan kata sandi aman. |
| `InstalasiController` | `app/Http/Controllers/` (288 baris) | Checklist teknis instalasi perangkat, pelaporan status pekerjaan lapangan, foto dokumentasi. |
| `GoodsReceiptController` | `app/Http/Controllers/` (278 baris) | Penerimaan barang dari supplier, pencatatan surat jalan vendor, nomor seri, dan verifikasi QC. |
| `MonitoringController` | `app/Http/Controllers/` (266 baris) | Monitoring komprehensif performa proyek per pelanggan dengan teknik query 2-fase anti-N+1. |
| `SupplierPurchaseOrderController`| `app/Http/Controllers/` (253 baris) | Pembuatan pesanan barang (PO) ke vendor pemasok, penerbitan status issued, kontrol batas kirim. |
| `AdminPanelController` | `app/Http/Controllers/` (246 baris) | Pengaturan hak akses Super Admin, audit trail sistem, manajemen master tipe kerja & kategori dokumen. |
| `SalesMeetingController` | `app/Http/Controllers/` (242 baris) | Pencatatan notula rapat sales (Minutes of Meeting), mode online/offline, daftar hadir, dan foto bukti. |
| `ProductKnowledgeController` | `app/Http/Controllers/` (237 baris) | Pipeline crawling web vendor produk & ekstraksi otomatis menjadi basis pengetahuan AI. |
| `BastController` | `app/Http/Controllers/` (221 baris) | Penerbitan Berita Acara Serah Terima (BAST), upload berkas bertandatangan, penetapan tanggal garansi. |
| `FieldWorkController` | `app/Http/Controllers/` (218 baris) | Hub terpadu pekerjaan lapangan teknisi (rekapitulasi Survey & Instalasi aktif). |
| `ProductController` | `app/Http/Controllers/` (209 lines) | Master katalog produk BOQ proposal (SKU, brand, spesifikasi, harga acuan, part number). |
| `SizingProjectController` | `app/Http/Controllers/` (203 lines) | Analisis kebutuhan teknis pelanggan, rekomendasi perangkat, dan perancangan topologi jaringan. |
| `PurchasingHandoffController` | `app/Http/Controllers/` (105 lines) | Gerbang verifikasi Customer PO dari Sales ke Admin/Purchasing (`verifyPo` dan `returnPo` ber-catatan revisi). |
| `SupplierInvoiceController` | `app/Http/Controllers/` (189 lines) | Pencatatan faktur tagihan dari supplier (Hutang Usaha / AP) dan kontrol jatuh tempo pembayaran. |
| `SupplierPaymentController` | `app/Http/Controllers/` (92 lines) | Eksekusi pembayaran ke supplier, pencatatan metode transfer bank, dan upload bukti transfer privat. |
| `BastController` | `app/Http/Controllers/` (221 lines) | Alur BAST proyek, form penandatangan klien, dan closing komersial proyek. |

---

### 5.2 Inventaris Layanan Bisnis (14 Service Classes)
| Service Class | Lokasi & Ukuran | Pola Desain & Logika Bisnis |
|---|---|---|
| `GoogleCalendarService` | `app/Services/` (1024 baris) | Integrasi Google Calendar v3: OAuth refresh token otomatis, idempoten sync via `google_event_id`, parsing waktu zona, retry logic, sinkronisasi jadwal multi-teknisi. |
| `AdminService` | `app/Services/` (713 baris) | Penomoran otomatis Invoice (`INV/NTI/YYYY/MM/XXXX`) dan PO, kalkulasi pelunasan bertahap, sinkronisasi status tagihan, snapshot identitas pelanggan saat cetak, agregasi metrik Admin Hub. |
| `DocumentCenterService` | `app/Services/` (662 baris) | Mesin federasi dokumen: mengabstraksikan 8 cabang data (Project Doc, Quotation Attachment, Customer PO, Supplier Invoice, Supplier Payment, Goods Receipt, Lead Doc, NPWP) menjadi `DocumentCenterRow` terpadu dengan pagination dan filter cepat. |
| `WhatsappBot` | `app/Services/` (316 baris) | Logika mesin bot WA cerdas: pembacaan antrean pesan tak terbalas, integrasi agen AI, ekstraksi ringkasan kebutuhan prospek, penentuan alih kendali ke manusia (takeover). |
| `DocumentRegistry` | `app/Services/` (265 baris) | Katalog registri tipe dokumen sistem, validasi tipe MIME terstandar, dan pengecekan otorisasi unduhan file privat. |
| `SalesKpiService` | `app/Services/` (252 baris) | Kalkulasi metrik KPI sales individu & tim: leaderboard perolehan omzet, rasio konversi deal, perbandingan realisasi terhadap `SalesTarget`. |
| `WhatsappGateway` | `app/Services/` (229 baris) | Abstraksi gateway API pesan instan (Green API & Meta WhatsApp Cloud API), enkripsi/dekripsi token gateway di database. |
| `SalesService` | `app/Services/` (227 baris) | Logika filter kepemilikan prospek tim sales, audit follow-up kedaluwarsa, pengecekan konsistensi relasi customer-lead. |
| `ProjectDocumentCleanup` | `app/Services/` (215 baris) | Pembersihan berkas sampah dari storage fisik saat terjadi penghapusan permanen atau penggantian versi dokumen. |
| `CustomerContactService` | `app/Services/` (212 baris) | Menjamin integritas aturan **Single Primary Contact**: saat PIC baru dijadikan primary, kontak lama otomatis dinonaktifkan dari status primary; sinkronisasi cache kontak ke master customer. |
| `DevWorkLogService` | `app/Services/` (180 baris) | Perekaman log kerja developer otomatis berdasarkan commit dan aktivitas operasional. |
| `ProcurementService` | `app/Services/` (172 baris) | Penomoran otomatis Goods Receipt (`GR/NTI/YYMM/XXXX`) & Goods Delivery (`SJ/NTI/YYMM/XXXX`), kalkulasi sisa barang PO belum tiba, validasi nomor seri duplikat. |
| `ConversationTitle` | `app/Services/` (65 baris) | Generator judul obrolan AI cerdas berbasis konteks percakapan pertama. |
| `DocumentActivityLogger` | `app/Services/` (31 baris) | Perekaman audit trail interaksi dokumen (upload, preview, replace, download, delete). |

---

### 5.3 Sistem Notifikasi (27 Tipe Notifikasi)
Sistem memiliki 27 class notifikasi di `app/Notifications/` yang disiarkan melalui database channel, WebPush browser, dan email:
- **Sales & CRM:** `LeadAssignedNotification`, `NewLeadNotification`, `FollowUpOverdueNotification`, `MeetingReportReminderNotification`, `LeadTaskNotification`.
- **Presales & Kolaborasi:** `TechnicalRequestCreatedNotification`, `TechnicalRequestReviewedNotification`, `TechnicalWorkCompletedNotification`, `TechnicianAssignedNotification`, `PriceRequestCreatedNotification`, `PriceRequestReviewedNotification`, `PriceRequestCommentNotification`, `PriceRequestCompletedNotification`.
- **Quotation & Purchasing:** `ProposalStatusNotification` (perubahan status quotation & return PO), `PurchasingHandoffNotification` (notifikasi PO diterima siap belanja), `ProjectConvertedNotification`.
- **Teknisi & Lapangan:** `SurveyCreatedNotification`, `SurveyReportUploadedNotification`, `InstallationCompletedNotification`, `TechnicianScheduleAssignedNotification`, `TechnicianScheduleChangedNotification`, `TechnicianScheduleReminderNotification`.
- **Komunikasi & Admin:** `WhatsappInboundNotification`, `WhatsappHandoffNotification`, `BastReadyNotification`, `NewUserPendingApprovalNotification`.

---

## 6. STATUS FITUR & GAP AUDIT (SELESAI VS PLACEHOLDER / BELUM TERHUBUNG)

### 6.1 Fitur yang 100% Selesai & Berjalan di Produksi (Production Ready)
1. **Otentikasi & Keamanan:** Login, lupa password, Google SSO OAuth, middleware Spatie RBAC, proteksi akun Super Admin terakhir, normalisasi gambar profil, disk privat untuk berkas rahasia.
2. **CRM & Lead Lifecycle:** Penangkapan lead dari WA & form, penugasan sales, pelacakan kanban drag-and-drop, filter wilayah (kota/provinsi), pencatatan meeting dengan foto bukti & MOM, import lead massal via Excel.
3. **Kontak Pelanggan:** Manajemen kontak PIC dengan perlindungan tunggal primary contact (`CustomerContactService`), sinkronisasi nomor WhatsApp.
4. **Kolaborasi Presales Teknik:** Tiket bantuan teknik, survey lapangan dengan upload PDF privat, sizing topologi, request harga vendor, dan forum Price Request terhubung antrean sales-teknisi.
5. **Komersial & Quotation Gate:** Pembuatan BOQ berproteksi anti-Rp 0 dan anti-tampering harga pokok, auto-kalkulasi margin laba, pencatatan kanal pengiriman (WhatsApp/Email), upload Customer PO, dan validasi kelengkapan berkas PO.
6. **Gerbang Verifikasi PO (Admin Verification):** Pemisahan tegas antara Customer PO dan Supplier PO; verifikasi PO pelanggan oleh Admin sebelum belanja atau konversi proyek dapat diproses; fitur Return to Sales ber-catatan revisi tertulis.
7. **Pengadaan (Paket 3 Procurement):** Master supplier, penerbitan Supplier PO ke vendor, penerimaan barang gudang (Goods Receipt) ber-QC dan nomor seri barcode, serta pencatatan Hutang Usaha (Supplier Invoice AP) dan pelunasan supplier.
8. **Logistik Pelanggan:** Penerbitan Surat Jalan (Goods Delivery) terhubung nomor polisi & driver, pelacakan status kirim, opsi bypass pengiriman untuk proyek jasa murni (`requires_delivery = false`).
9. **Penagihan Pelanggan (Finance AR):** Penerbitan Invoice ber-item dinamis (`invoice_items`), kalkulasi subtotal, diskon, PPN, nomor Faktur Pajak, pencatatan pembayaran cicilan bertahap, dan layout cetak resmi A4.
10. **Pusat Dokumen Lintas Modul (Document Center):** Pencarian dan penyaringan instan dokumen dari 8 sumber modul, terintegrasi penampil PDF in-browser.
11. **Pelaporan & Dasbor:** Lynk Dashboard ber-sparkline riil 6 bulan, Donut Chart 100%, Activity Feed real-time, monitoring proyek 2-fase anti-N+1, dan kalender agenda teknisi tersinkronisasi GCal.

---

### 6.2 Status Fitur yang Masih Berupa Placeholder, Stub, atau Belum Terhubung
Meskipun kapabilitas inti sudah lengkap, audit menemukan beberapa area yang masih berstatus placeholder atau belum terintegrasi ke sistem eksternal:

1. **Menu Management Placeholder (`routes/web.php:728-729`):**
   - Rute `manage.marketing.index` dan `manage.technical.index` masih merender view stub statis (`manage.placeholder`). Menu ini dicadangkan untuk dasbor manajerial spesifik marketing dan teknisi di masa mendatang.
   - *Catatan:* Untuk `manage.admin.index`, rute sudah berhasil dihubungkan langsung ke `AdminController@hub`.
2. **Integrasi e-Faktur Pajak Otomatis (DJP):**
   - Kolom `tax_invoice_number` dan `tax_invoice_date` pada tabel `invoices` saat ini berfungsi sebagai pencatatan nomor faktur pajak manual dari admin. Belum ada koneksi API langsung (host-to-host) ke aplikasi e-Faktur DJP untuk download/generate XML faktur pajak secara otomatis.
3. **Template Kop Surat Multi-PT Dinamis:**
   - Pencetakan Invoice (`/admin/invoices/{invoice}/print`) saat ini mengacu pada layout standar Tridaya. Pemilihan kop surat khusus per perseroan (antara logo/identitas resmi NTI, MGK, TPS, atau WANI) masih menggunakan layout terpusat dan belum memiliki konfigurasi switch logo multi-PT dinamis di Admin Panel.
4. **Gateway WhatsApp Meta Cloud API vs Green API:**
   - Sistem memiliki controller dan model untuk kedua gateway, namun konfigurasi aktif di database saat ini mengandalkan instance Green API. Webhook Meta Cloud API sudah dikodekan tetapi integrasi resminya memerlukan verifikasi Meta Business Manager aktif dari klien.
5. **Multi-Tenant Google Calendar App Credentials:**
   - Aplikasi menggunakan 1 pasang kredensial global OAuth Google Cloud Console (`google_app_credentials`). Jika Tridaya ingin memisahkan domain Google Workspace per PT di masa depan, arsitektur saat ini masih bersifat shared-tenant.

---

### 6.3 Rekapitulasi Masalah Kritis (P0/P1/P2) & Status Perbaikan
Berdasarkan perbandingan dengan temuan awal audit 15 Oktober 2026:

| Isu Temuan Awal | Prioritas | Status per November 2026 | Solusi & Penanganan pada Kode |
|---|---|---|---|
| `Lead::projects()` NPE bila customer null | `P0` | **TUNTAS (FIXED)** | Metode didelegasikan dengan aman dan dicek relasi customer-nya; pemanggilan proyek melalui Customer / Proposal terkonversi. |
| Celah Supplier PO dibuat sebelum PO terverifikasi | `P0` | **TUNTAS (FIXED)** | Ditambahkan gerbang verifikasi `po_verified_at`, fungsi `isReadyForPurchasing()`, dan blokade pada `SupplierPurchaseOrderController::eligibleProposals()`. |
| Celah Convert to Project tanpa syarat verifikasi | `P0` | **TUNTAS (FIXED)** | Method `convertToProject()` memvalidasi `isReadyForPurchasing()`, berkas fisik PO, nomor PO, dan skema pembayaran sebelum membuat proyek. |
| Berkas Customer PO Revisi & Alert Lambat | `P1` | **TUNTAS (FIXED)** | Form sales menampilkan input unggah ulang berkas PO revisi; notifikasi handoff dialihkan ke background queue (`ShouldQueue`) sehingga loading instan (< 1 detik). |
| Alpine syntax error pada Document Center | `P1` | **TUNTAS (FIXED)** | Row data diisolasi ke scope Alpine `x-data="{ panelData: ... }"` untuk menghindari tabrakan tanda kutip JSON string. |
| N+1 Query pada Pipeline & Monitoring | `P1` | **TUNTAS (FIXED)** | `MonitoringController` menerapkan 2-fase query (`withMax` agregasi + eager loading 15 data per halaman); pipeline lead difilter pada level kueri database. |
| Duplikasi Kontak PIC Customer | `P2` | **TUNTAS (FIXED)** | Migrasi `add_single_primary_guard_to_customer_contacts` dan service `CustomerContactService` menjamin hanya 1 PIC primary per perusahaan. |
| Inkonsistensi Desain Tombol | `P2` | **TUNTAS (FIXED)** | Seluruh antarmuka diseragamkan ke konvensi `rounded-xl`, stroke ikon 3.5, `<x-flow-button>`, dan `<x-back-button>`. |

---

### 6.4 Lampiran Lingkungan Produksi & Kompatibilitas Sistem
- **Kebutuhan Variabel `.env` Inti:**
  - `APP_URL`, `APP_ENV=production`, `APP_KEY`.
  - Database: `DB_CONNECTION=sqlite` (atau `mysql`), `DB_DATABASE=/var/www/3dyapp/database/database.sqlite`.
  - Antrean & Sesi: `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=file`.
  - Real-time Reverb: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT=8080`, `REVERB_SCHEME=https`.
  - Google Calendar: Kredensial tersimpan terenkripsi di database (`google_app_credentials`).
  - WhatsApp: Kredensial instance gateway tersimpan terenkripsi di tabel `whatsapp_accounts`.
  - Web Push: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`.
- **Aturan Build Aset Frontend (`AGENTS.md §5`):**
  - Kompilasi aset CSS/JS (`npm run build`) **wajib dijalankan dari folder utama `/var/www/3dyapp` (branch `main`)**.
  - Folder `public/build` pada worktree divisi lain merupakan symlink ke `main`. Menjalankan build dari worktree lama dapat menghapus kelas Tailwind baru dari bundle produksi.
- **Izin Berkas Cache Web Server:**
  - Setiap kali menjalankan command `artisan` atau `test` sebagai user non-webserver, bersihkan cache view dan rapikan kepemilikan file agar web server (`www-data`) tidak mengalami error 500:
    ```bash
    sudo -u www-data php artisan view:clear
    sudo chmod -R g+rwX storage/framework/cache storage/framework/views storage/framework/sessions storage/app storage/logs
    ```

---
> Dokumen ini diperbarui secara komprehensif berdasarkan audit kode langsung per **November 2026**.
> Seluruh referensi file, rute, tabel migrasi, model, dan kontroler telah diverifikasi valid terhadap struktur repositori `/var/www/3dyapp`.
