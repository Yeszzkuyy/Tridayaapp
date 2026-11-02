# Brief Sesi A — Sales → Admin Handoff

> Bagian dari [Rencana Induk](../rencana-induk.md). Baca file induk untuk detail tiap item (masalah, perbaikan, file, verifikasi).

- **Worktree:** `/home/yeski/3dyapp-handoff`
- **Branch:** `feature/handoff-admin`
- **Base:** `main` yang sudah bersih (lihat §2 rencana induk)
- **Jumlah item:** 12
- **Batas teknis:** ikuti `AGENTS.md` §2 (alur merge), §3/§9 (wip → squash), §7 (tombol), §12 (role)

---

## Urutan kerja

| Urut | No | ID | Singkat | Commit scope |
|---|---|---|---|---|
| 1 | A3 | ADM-2 | Komponen status handoff (ditunggu A6) | `sales/quotation` |
| 2 | A4 | ADM-3 | Validasi kelengkapan PO di Sales | `sales/quotation` |
| 3 | A5 | ADM-1 | Dispatch notifikasi ke Admin | `sales/quotation` |
| 4 | A6 | ADM-1b | Tampilan antrean Purchasing | `panel/purchasing` |
| 5 | A1 | MKT-1 | Field Location di Add Lead | `mkt/leads` |
| 6 | A2 | SAL-4 | Back preview quotation | `sales/quotation` |
| 7 | A7 | CUS-1 | Sinkron PIC overview customer | `cus/customers` |
| 8 | A8 | TEK-1 | Access denied notifikasi proposal | `sales/quotation` |
| 9 | A9 | SAL-1 | Tips Add Follow Up | `sales/followups` |
| 10 | A10 | SAL-2 | Loader/success add follow up | `sales/followups` |
| 11 | A11 | SAL-3 | Tabel Request Inside Sales | `sales/requests` |
| 12 | A12 | INS-2 | Change Status di View Detail Request | `sales/price-request` |

**Aturan commit:** dilarang mencampur dua divisi dalam satu commit (`AGENTS.md` §9). Karena sesi ini menyentuh 4 divisi, hasil akhirnya berupa beberapa commit paket (`mkt`, `sales`, `panel`, `cus`), bukan satu commit besar.

---

## File yang kamu miliki

- `app/Http/Controllers/ProposalController.php`
- `app/Models/Proposal.php`
- `app/Http/Controllers/LeadController.php`, `app/Models/Lead.php`, `resources/views/leads/**`
- `app/Http/Controllers/CustomerController.php`, `app/Models/Customer.php`, `resources/views/customers/**`
- `resources/views/sales/**`
- `resources/views/admin/**`, `PurchasingHandoffController`, `AdminService`, `BastController`
- `resources/views/components/handoff-status.blade.php` (baru)
- `app/Notifications/PurchasingHandoffNotification.php` (baru)

## Jangan disentuh

- `app/Http/Controllers/TechnicalRequestController.php` → B
- `app/Http/Controllers/SurveyController.php` → B
- `app/Notifications/**` selain file baru milikmu → B
- `app/Http/Controllers/NotificationController.php`, `app/Support/NotificationPresenter.php` → B
- `resources/js/**` → B
- `resources/views/teknisi/**` → B
- `resources/views/layouts/partials/sidebar.blade.php` → B
- `config/queue.php` → B

## File bersama

- `routes/web.php` — tambah route di blok sales/customer/admin saja, jangan ubah urutan blok lain.
- `lang/en.json`, `lang/id.json` — tambah key, saat konflik keep both.

## Temuan kunci yang harus dijaga

1. `Proposal::isReadyForPurchasing()` = `status === 'accepted'` **dan** `po_document_path` terisi.
2. `convertToProject()` sudah memblokir tanpa file PO, nomor PO, dan `payment_scheme` — jangan dilonggarkan, cukup diperjelas.
3. `ProposalController::authorizeView()` saat ini: owner / `manage-sales-leads` / `manage-admin` / CEO. `lead-technician` kena 403.
4. `LeadController::store` membuat kontak PIC hanya dengan `name` (tanpa `whatsapp`/`phone`) dan mengabaikan PIC pada mode customer existing — sumber masalah CUS-1. `feature/div-marketing` **tidak di-merge**; perbaiki langsung di `main`.
5. Penerima notifikasi Admin memakai `view-admin-hub` / `manage-admin` (§12), bukan role baru.

## Definition of Done (per item)

1. Perubahan sesuai permintaan user, sudah dicek di UI.
2. Tidak ada `dd()`/`dump()`/kode mati.
3. `php artisan test` lulus untuk logika yang disentuh.
4. Tombol mengikuti §7 (icon-only, warna solid, teks Inggris).
5. Tidak ada file sisa (`.bak`, screenshot debug, log).
6. Commit sesuai format §9 + body 2–5 baris.

## Template commit

```
feat(sales/quotation): status handoff seragam dan validasi PO customer
feat(panel/purchasing): penanda antrean siap proses dan tautan handoff
fix(cus/customers): sinkronkan PIC overview dengan kontak customer
fix(sales/proposals): izinkan lead technician membaca quotation
feat(mkt/leads): tambah field location pada add lead
feat(sales/followups): tips add follow up dan loader seragam
style(sales/requests): rapikan tabel request inside sales
feat(sales/price-request): rombak alur change status
```
