# PLAN-SISTEM-END-TO-END-BRANCHES: Integrasi Notifikasi, Customer, Marketing, Sales, Management, Admin & Inside Sales

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Lintas Divisi (Notifikasi, Customer, Marketing, Teknisi, Sales, Management, Admin, Inside Sales/Estimator)
**Branch:** mengikuti tabel di Bagian 0
**Terakhir diperbarui:** 2026-11-09
> Catatan: sebagian menunggu keputusan User.
**Baseline kode:** `main` @ `d30b32d2`

> Dokumen ini disusun dengan gaya yang sama seperti `docs/plans/admin/PLAN-ADMIN-END-TO-END.md`:
> target perubahan presisi ke file, migrasi, dan kriteria selesai berupa checklist.

---

## INSTRUKSI WAJIB UNTUK AI AGENT

1. **Satu branch = satu worktree.** Dilarang pindah branch di worktree milik sesi lain (AGENTS.md §1).
2. **Migrasi wajib** dibuat dengan `php artisan make:migration`. Dilarang mengedit migrasi yang sudah di-merge ke `main` (AGENTS.md §15).
3. **Dilarang membuat modul/tabel baru** bila tabel yang sama sudah ada. Gunakan migrasi aditif dan manfaatkan `proposals`, `proposal_items`, `follow_ups`, `lead_tasks`, `projects`, `purchase_orders`, `invoices`.
4. **Satu commit = satu paket perubahan.** Selama masih bolak-balik pakai `wip(...)`. Commit paket hanya setelah User menyatakan hasilnya sesuai (AGENTS.md §9).
5. **Test wajib hijau** di akhir tiap tahap. Bila menyentuh logika, `php artisan test` harus lulus.
6. **Build Tailwind hanya dari `/var/www/3dyapp`** setelah merge ke `main` (AGENTS.md §5).
7. **Working tree `main` saat ini kotor** (pekerjaan preview dokumen + notifikasi popup). Dilarang menjalankan build atau commit dari `main` sampai pekerjaan itu selesai.
8. **Aturan UI** mengikuti AGENTS.md §7, §7.1, §7.2. Tidak ada teks filler, tidak ada em-dash, tidak ada angka palsu.
9. **Harga modal** (`cost_price`, `total_cost`, `gross_margin`) tidak boleh muncul di cetakan/preview customer.
10. **Semua angka dashboard** berasal dari query database riil, bukan contoh.

---

## BAGIAN 0 — PETA BRANCH, WORKTREE & KESIAPAN

| Bagian | Branch | Worktree | Tertinggal dari `main` | Aksi sebelum mulai |
|---|---|---|---|---|
| A | `feature/sys-notif-popup` | `/home/raafi/3dyapp-notifpop` | 0 | siap |
| B | `feature/div-cus` | `/var/www/3dyapp-cus` | 884 | `git pull` + `git merge main` |
| C | `feature/div-marketing` | `/var/www/3dyapp-mkt` | 129 | `git pull` + `git merge main` |
| D | `feature/div-teknisi` | `/var/www/3dyapp-tek` | 419 | `git pull` + `git merge main` |
| E | `feature/sales-flow` | `/var/www/3dyapp-sales` | 146 | `git pull` + `git merge main` |
| F | `feature/management` | `/home/yeski/3dyapp-management` | 70 | `git pull` + `git merge main` |
| G | `feature/div-panel` | belum ada, usul `/home/raafi/3dyapp-panel` | 124 | buat worktree + `git merge main` |
| H | `feature/sales-inside-estimator` (baru) | usul `/home/raafi/3dyapp-inside` | - | menunggu persetujuan User |

Setelah `git merge main` di tiap worktree: `php artisan migrate` (AGENTS.md §15).

---

## BAGIAN A — NOTIFIKASI REAL-TIME (branch `feature/sys-notif-popup`)

### A.0 Masalah

Notifikasi yang sudah dibaca atau dihapus masih muncul sampai website di-restart.

### A.1 Akar masalah presisi

| No | Penyebab | Bukti di kode |
|---|---|---|
| 1 | Read/delete tidak pernah di-broadcast | `app/Events/` hanya berisi `NotificationCreated.php` |
| 2 | Polling mati saat Reverb tersambung | `resources/js/components/NotificationCenter.jsx:519` |
| 3 | Mode `merge` tidak membuang item yang sudah hilang di server | `NotificationCenter.jsx:387-403` |
| 4 | Aksi optimistis tanpa rollback | `NotificationCenter.jsx:351-377`, `602-666` |
| 5 | `destroyAll` selalu membalas `unread: 0` | `NotificationController.php:67-72` |
| 6 | Antrean `jobs` rusak | log produksi 2026-11-09: 21.232 error `permission denied for table jobs` |
| 7 | File JSX ganda di working tree | `resources/js/NotificationCenter.jsx`, `NotificationToast.jsx`, `ToastViewport.jsx` (tidak dipakai) |

### A.2 Target perubahan presisi

1. **Event baru** `app/Events/NotificationRead.php` dan `app/Events/NotificationDeleted.php`:
   - `implements ShouldBroadcastNow, ShouldRescue`
   - channel `private-notifications.{userId}`
   - payload `{ ids, unread, scope }`.
2. **`app/Http/Controllers/NotificationController.php`**:
   - `read()` memanggil `NotificationRead::dispatch($user->id, [$id], $unread)`.
   - `readAll()` memanggil `NotificationRead::dispatch($user->id, $ids, 0, 'all')`.
   - `destroy()` dan `destroyAll()` memanggil `NotificationDeleted`.
   - `destroyAll()` mengembalikan `{ unread: 0, deleted: $deleted }`.
3. **`resources/js/components/NotificationCenter.jsx`**:
   - dengarkan `.notification.read` dan `.notification.deleted`,
   - `applyStatus({ merge: true })` membuang item yang tidak ada di respons server,
   - setelah mutasi sukses panggil `refreshStatus({ merge: false })`,
   - saat gagal, kembalikan state sebelumnya + toast error.
4. **Polling**: tetap jalan 60 detik walau `connected === true` sebagai jaring pengaman.
5. **Operasional**: grant `SELECT, INSERT, UPDATE, DELETE` pada `jobs`, `failed_jobs`, `job_batches`
   untuk user database aplikasi, lalu `php artisan queue:restart`.
6. **Bersihkan** file JSX ganda di root `resources/js/` setelah koordinasi.

### A.3 DoD

- [ ] Baca 1 notifikasi di tab A, tab B ikut berubah tanpa reload.
- [ ] Hapus semua di tab A, tab B kosong dan tetap kosong setelah panel ditutup dibuka.
- [ ] `DELETE /notifications` mengembalikan jumlah terhapus dan database user benar-benar kosong.
- [ ] `php artisan test --filter=Notification` lulus.
- [ ] Tidak ada error `permission denied for table jobs` baru di log.

---

## BAGIAN B — PIC CUSTOMER (branch `feature/div-cus`)

### B.0 Masalah

PIC yang diinput di Add Lead Marketing tidak tampil di daftar dan detail Customer.

### B.1 Akar masalah presisi

| No | Penyebab | Bukti di kode |
|---|---|---|
| 1 | Hanya membaca kontak `is_primary = true` | `app/Models/Customer.php:44-52`, `68-73` |
| 2 | `syncPicCache()` mengosongkan cache bila tidak ada primary | `app/Services/CustomerContactService.php:189-198` |
| 3 | Form Customer tidak punya field PIC | `customers/create.blade.php:11-53`, `customers/edit.blade.php:214-249` |
| 4 | Pencarian tidak mencakup PIC | `CustomerController.php:29-31` |
| 5 | Filter `pic` dipanggil view tapi tidak diproses | `customers/_list.blade.php:2` |
| 6 | Backfill menulis DB saat GET | `CustomerController.php:132` |
| 7 | File sisa | `resources/views/customers/.wip-show.tmp` |

### B.2 Target perubahan presisi

1. **`app/Services/CustomerContactService.php`**: kontak pertama otomatis `is_primary = true`.
2. **`app/Models/Customer.php`**: `picName()`, `picWhatsapp()`, `picPhone()`, `picEmail()`, `picPosition()`
   fallback ke kontak pertama (`orderByDesc('is_primary')->orderBy('id')`).
3. **`app/Http/Controllers/CustomerController.php`**:
   - pencarian `orWhereHas('contacts', ...)` untuk `name`, `phone`, `whatsapp`, `email`,
   - implementasi filter `pic`,
   - hapus `backfillLegacyPrimary()` dari `show()`.
4. **View**: form create/edit Customer menambah blok PIC (nama, jabatan, WhatsApp, email);
   detail Customer menampilkan jabatan + WhatsApp + email PIC utama.
5. **Hapus** `.wip-show.tmp` dan kondisi mati `$customer->assigned_to` di `CustomerPolicy`.

### B.3 DoD

- [ ] Add Lead dengan PIC -> daftar Customer menampilkan nama + WA + email PIC.
- [ ] Detail Customer menampilkan jabatan (bila diisi).
- [ ] Cari nama PIC -> customer terkait muncul.
- [ ] Customer manual punya field PIC di form dan tersimpan sebagai primary.
- [ ] `php artisan test --filter="CustomerContact|CustomerList|CustomerSearch|LeadForm"` lulus.

---

## BAGIAN C — LEAD TIDAK OTOMATIS JADI CUSTOMER (branch `feature/div-marketing`)

### C.0 Masalah

Setiap Add Lead langsung membuat Customer, padahal lead belum tentu membeli.

### C.1 Akar masalah presisi

| No | Penyebab | Bukti di kode |
|---|---|---|
| 1 | `leads.customer_id` NOT NULL | `database/migrations/2026_07_28_035513_create_leads_table.php:13` |
| 2 | `store()` selalu `Customer::create` | `LeadController.php:461-501` |
| 3 | Import membuat Customer massal | `LeadController.php:1192-1224` |
| 4 | WhatsApp Center langsung membuat Customer | `WhatsAppCenterController.php:282-289`, `467` |
| 5 | Data PIC tercampur data perusahaan | `LeadController.php:477-479`, `688-691` |

### C.2 Target perubahan presisi

1. **Migrasi baru** `add_snapshot_fields_to_leads_table`:
   - `customer_id` -> nullable + `nullOnDelete`,
   - `company_name`, `contact_name`, `contact_position`, `contact_phone`, `contact_whatsapp`,
     `contact_email`, `address`.
2. **Command backfill** dari Customer yang sudah terhubung.
3. **`app/Models/Lead.php`**: fillable + `displayCompany()`, `displayPicName()`, `displayPicWhatsapp()`,
   `displayPicEmail()`, relasi null-safe.
4. **`LeadController`**:
   - `store()`/`update()` tidak membuat Customer untuk mode `new`,
   - import menulis snapshot,
   - `syncPrimaryFromLead` dipindah setelah otorisasi,
   - aksi baru `promote` ke Customer dengan izin `manage-sales`.
5. **`WhatsAppCenterController`**: buat Lead dulu, bukan Customer.
6. **View** lead create/edit/index/show memakai helper snapshot + tombol "Jadikan Customer".
7. **Sales**: quotation harus menangani lead tanpa `customer_id`; convert mewajibkan lead sudah jadi Customer.

### C.3 DoD

- [ ] Add Lead baru tidak menambah baris di tabel `customers`.
- [ ] Detail Lead tetap menampilkan PIC lengkap.
- [ ] "Jadikan Customer" membuat Customer + PIC utama dan menautkan lead.
- [ ] Import lead tidak membuat Customer.
- [ ] `php artisan test --filter="LeadForm|LeadDetail|LeadLifecycle|WhatsAppCenter"` lulus.

---

## BAGIAN D — NOTIFIKASI JADWAL TEKNISI (branch `feature/div-teknisi`)

### D.0 Masalah

Anggie (role `marketing`) dan Syifa (role `lead-marketing`) menerima notifikasi saat teknisi mengubah jadwal.

### D.1 Akar masalah presisi

Penerima di `app/Services/GoogleCalendarService.php`:

- `createSchedule()` 721-745: pivot `technician_schedule_user`.
- `updateSchedule()` 751-803: teknisi baru, teknisi dilepas, semua teknisi, owner (`user_id`).
- `deleteSchedule()` 805-817: semua teknisi + owner.
- `notifyTechnician()` 831-848: hanya melewati pelaku dan duplikat, **tanpa filter role**.

Role marketing tidak pernah dikirimi notifikasi oleh kode. Berarti mereka masuk ke pivot, owner, atau
kolom lama `technician_id`.

### D.2 Target perubahan presisi

1. Query diagnosa read-only: cek `technician_schedule_user`, `user_id`, `technician_id` untuk kedua akun.
2. **`GoogleCalendarService`**: tambah `canReceiveScheduleNotification(User $user, ?User $owner): bool`
   dengan aturan role `technician` / `lead-technician` / `prakerin-technician`.
3. Panggil method itu di `notifyTechnician()`.
4. **`TechnicianScheduleChangedNotification::via()`**: hormati preferensi `notify_system`.
5. **`TechnicianScheduleController` + view**: validasi hanya user ber-role teknisi yang bisa dipilih.
6. Bersihkan data pivot yang salah setelah disetujui User.

### D.3 DoD

- [ ] Ubah jadwal -> tidak ada notifikasi baru untuk Anggie dan Syifa.
- [ ] Teknisi yang ditugaskan/dilepas tetap menerima notifikasi.
- [ ] Form jadwal menolak user non-teknisi.
- [ ] `php artisan test --filter="TechnicianSchedule"` lulus + test baru.

---

## BAGIAN E — SALES FLOW (branch `feature/sales-flow`)

### E.1 Tahap E1 — Urutan kartu dan bahasa dashboard

**Target:**

1. `resources/views/sales/dashboard.blade.php`: pindahkan definisi + render 5 kartu KPI
   (baris 165-275) ke setelah kartu motivasi (baris 18-62) dan sebelum My Personal KPI (64-160).
2. Ganti teks Indonesia hardcode (baris 76, 82, 84, 115, 117, 119, 144, 146, 148, 155, 283, 317)
   dengan `__()`.
3. `app/Services/SalesKpiService.php` baris 206, 221, 235 -> `__()`.
4. `lang/en.json` baris 4731-4732 diperbaiki menjadi Inggris; tambah key baru di `lang/en.json` + `lang/id.json`.

**DoD:**

- [ ] Urutan: header -> motivasi -> 5 kartu KPI -> My Personal KPI -> Customer Hub.
- [ ] Locale `en` semua label Inggris; locale `id` tetap Indonesia.
- [ ] `php artisan test --filter="SalesKpi|Dashboard"` lulus.

### E.2 Tahap E2 — Report harian dari Add Meeting

**Target:**

1. Migrasi baru `add_daily_report_submitted_at_to_follow_ups_table`.
2. `SalesMeetingController@store()` -> redirect ke form report dengan data terisi.
3. `SalesService::completeMeetingReport()` mengisi `daily_report_submitted_at` + log `daily_report_submitted`.
4. Dashboard Sales: kartu Daily Report (meeting hari ini, sudah, belum, tombol kirim).
5. `sales/meetings/index.blade.php`: badge `Report sent` / `Report pending` + waktu kirim.
6. Service baru `SalesReportStatusService::daily($date)` untuk dipakai Sales dan Management.

**DoD:**

- [ ] Add Meeting -> form report terisi otomatis -> kirim tanpa input ulang.
- [ ] Dashboard menampilkan status sudah/belum lapor.
- [ ] Kirim dua kali ditolak.
- [ ] `php artisan test --filter="MeetingReport|SalesMeetingTracker"` lulus.

### E.3 Tahap E3 — Quotation otomatis dari Inside Sales

**Target:**

1. Migrasi baru `create_lead_task_items_table`:
   `lead_task_id`, `product_id`, `description`, `quantity`, `unit`, `unit_price`, `discount`, `subtotal`.
2. Migrasi baru `add_lead_task_id_to_proposals_table` + aturan 1 task = 1 quotation aktif.
3. `LeadTaskController@acceptAttachment()`: buat `Proposal` **beserta** `ProposalItem` dari `lead_task_items`.
4. `proposals.created_by` = Sales pemilik lead; Inside Sales tercatat di `lead_tasks`/attachment.
5. Form item di `lead-tasks/show.blade.php` memakai komponen quotation (`_form.blade.php:79-119`).
6. Opsional: `proposal_items.price_request_reply_id` untuk jejak harga estimator.
7. Admin melihat rincian item di halaman handoff tanpa membuka file.

**DoD:**

- [ ] Inside Sales isi 3 item -> Accept -> Quotation Sales berisi 3 item yang sama + total + margin.
- [ ] Sales bisa kirim quotation tanpa mengetik ulang.
- [ ] Tidak ada quotation `draft` 0 item dari alur Inside Sales.
- [ ] `php artisan test --filter="InsideSalesTask|ProposalModule|ProposalInsideSales|PriceRequestQuotation"` lulus.

### E.4 Tahap E4 — PO number, convert aman, PIC engineer

**Penyebab 500 (terkonfirmasi):**

- `ProposalController@convertToProject` (885-985) memanggil `notifyAdminHandoff()` (856-870).
- Penerima = `User::permission('view-admin-hub')->where('id','!=',auth()->id())`.
- Admin yang convert -> penerima kosong -> tidak ada dispatch -> sukses.
- Sales yang convert -> Admin menerima -> `PurchasingHandoffNotification` (`ShouldQueue`) -> tabel `jobs`
  error `permission denied` (21.232 error hari ini) -> 500 setelah project ter-commit.

**Target:**

1. Grant tabel `jobs` + `php artisan queue:restart`.
2. Bungkus notifikasi setelah project dibuat dengan `try/catch` + `report()`.
3. Guard `$proposal->project()->withTrashed()->exists()`.
4. `isOwner()` null-safe `$proposal->lead?->assigned_to`; convert menolak 422 bila lead terhapus.
5. Tambah `requires_delivery` ke `Project::$fillable`.
6. Modal convert menampilkan syarat `po_verified_at`.
7. Tangkap `QueryException` unique `projects.proposal_id` -> 422 ramah.
8. Migrasi baru `add_po_snapshot_to_projects_table`:
   `po_number`, `po_date`, `po_verified_at`, `po_verified_by`.
9. Validasi `po_number` unik per customer + normalisasi.
10. Invoice mengambil nomor PO dari proposal (read-only).
11. Modal convert: pilih PIC Engineer + Support Technician.
12. Saat convert: isi `teknisi_user_id`, `pic_engineer`, baris `project_supports`, kirim notifikasi.
13. Convert tanpa engineer -> project masuk daftar Unassigned untuk `lead-technician`.
14. Daftar project teknisi memfilter `teknisi_user_id` **atau** pivot `project_supports`.

**DoD:**

- [ ] Admin verifikasi PO -> Sales convert -> sukses, tanpa 500.
- [ ] Convert dua kali -> 422 "sudah dikonversi", bukan error.
- [ ] Project menampilkan nomor PO yang sama dengan quotation dan invoice.
- [ ] PIC Engineer menerima notifikasi dan melihat project di daftarnya.
- [ ] `php artisan test --filter="AdminHandoffPoVerification|ProjectWorkflow|ProposalConvertUnique|ProcurementProjectLink"` lulus.

---

## BAGIAN F — MONITORING REPORT SALES (branch `feature/management`)

### F.1 Target perubahan presisi

1. `ManageSalesController@teamActivity` (451-524): tambahkan data report meeting per sales.
2. View `manage-sales/team-activity.blade.php`: section **Meeting Report Today**
   (Sales, Meeting, Reported, Pending, Last Report, Status).
3. Filter tanggal + PT/divisi.
4. `manage-sales/sales-rep.blade.php`: daftar meeting + badge status report.
5. Sidebar grup Management: tambah link Team Activity (`routes/web.php:727` sudah ada, link belum ada).
6. Empty state menjelaskan cara mengisi, bukan "No data".
7. Opsional: digest 17:30 ke Management.

### F.2 DoD

- [ ] Sales yang sudah lapor tampil `done`, yang belum `missing`.
- [ ] Filter tanggal berfungsi.
- [ ] Menu Team Activity muncul di sidebar Management.
- [ ] `php artisan test --filter="SalesUxImprovement|SalesKpiTarget|ManageSales"` lulus.

---

## BAGIAN G — ADMIN PO VERIFIKASI (branch `feature/div-panel`)

### G.1 Target perubahan presisi

1. Buat worktree `feature/div-panel` + merge `main`.
2. `admin/purchasing/handoff/show.blade.php`: checklist `poCompletenessErrors()`, tombol Verify/Return,
   blok ringkas nomor PO, tanggal, deadline, alamat, cara bayar.
3. `sales/proposals/show.blade.php`: badge `Waiting for verification` / `Verified` / `Returned` + alasan.
4. Tombol convert hanya aktif setelah `po_verified_at` terisi.
5. Notifikasi ke Sales saat diverifikasi/dikembalikan; notifikasi ke Admin saat PO diunggah.
6. Nomor PO satu sumber; invoice tidak bisa mengetik ulang.
7. Audit log `po_verified` / `po_returned` tampil di kedua sisi.

### G.2 DoD

- [ ] Admin melihat checklist dan bisa verify satu klik.
- [ ] Sales melihat status + alasan pengembalian.
- [ ] Nomor PO invoice = quotation.
- [ ] `php artisan test --filter="PurchasingHandoff|AdminHandoffPoVerification"` lulus.

---

## BAGIAN H — DASHBOARD INSIDE SALES & ESTIMATOR (branch baru)

### H.1 Target perubahan presisi

1. Tambah entry branch/worktree baru setelah disetujui User.
2. Route group `/workspace`:
   - `inside-sales.dashboard` -> `manage-inside-sales`,
   - `estimator.dashboard` -> `manage-price-requests`.
3. Controller terpisah: `InsideSalesDashboardController`, `EstimatorDashboardController`.
4. Dashboard Inside Sales: request baru, dikerjakan, menunggu estimator, selesai minggu ini, aging.
5. Dashboard Estimator: price request masuk, belum dijawab, dijawab hari ini, rata-rata waktu, riwayat harga.
6. Sidebar: perbaiki kondisi menu Inside Sales untuk user multi-role (jangan bergantung "tidak punya `view-sales`").
7. Notifikasi request baru dan jawaban harga memakai pola yang sudah ada.

### H.2 DoD

- [ ] Estimator langsung melihat dashboard dengan antrean riil.
- [ ] Inside Sales melihat dashboard kerjanya.
- [ ] User multi-role bisa berpindah tab tanpa logout.
- [ ] `php artisan test --filter="PriceRequest|InsideSalesTask|RoleMatrix|DivisionAccess"` lulus.

---

## RINGKASAN URUTAN EKSEKUSI

| Gelombang | Bagian | Fokus | Gate sebelum lanjut |
|---|---|---|---|
| 1 | A | Notifikasi real-time + queue | Dua tab sinkron, test notifikasi hijau |
| 1 | E1 | Kartu dashboard + bahasa | Urutan benar, test hijau |
| 1 | E4 (poin 1-8) | Stop 500 convert + PO snapshot | Convert sukses tanpa error |
| 1 | B | PIC Customer | PIC tampil + test hijau |
| 1 | D | Notifikasi jadwal teknisi | Anggie/Syifa tidak menerima |
| 2 | E2 | Report meeting | Status harian terbaca |
| 2 | F | Monitoring Management | Team Activity menampilkan report |
| 2 | G | Verifikasi PO Admin | Verify satu klik, status terlihat Sales |
| 2 | E4 (poin 11-14) | PIC Engineer | Engineer dapat notifikasi + project muncul |
| 3 | E3 | Quotation otomatis | Item + total sama dari Inside Sales |
| 3 | C | Lead tanpa Customer | Add Lead tidak membuat Customer |
| 3 | H | Dashboard Inside Sales/Estimator | Dashboard riil per role |

---

## KEPUTUSAN YANG MENUNGGU USER

1. **Lead menjadi Customer:**
   - **Opsi A (rekomendasi):** otomatis saat quotation `accepted` atau PO masuk.
   - **Opsi B:** manual lewat tombol "Jadikan Customer".
   - **Opsi C:** keduanya.
2. **Engineer belum dipilih saat convert:**
   - **Opsi A (rekomendasi):** project masuk antrean Unassigned, `lead-technician` menunjuk.
   - **Opsi B:** convert diblokir sampai engineer dipilih.
3. **Verifikasi PO:**
   - **Opsi A (rekomendasi):** tetap wajib, Admin cukup satu klik.
   - **Opsi B:** boleh convert dulu, verifikasi menyusul.
4. **Struktur menu Inside Sales/Estimator:**
   - **Opsi A (rekomendasi):** satu grup "Workspace" dengan tab per role.
   - **Opsi B:** dua menu benar-benar terpisah.

---

## CATATAN AKHIR

- Dokumen ini disimpan di `docs/plans/sistem/PLAN-SISTEM-END-TO-END-BRANCHES.md`.
- Rencana rinci per topik ada di `docs/plans/<divisi>/` dengan nama `PLAN-*`
  sesuai tabel Bagian 0.
- Status tiap rencana diperbarui pada commit `docs(...)` yang sama saat rencana berubah.
