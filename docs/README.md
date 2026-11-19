# Dokumentasi 3DY App

Folder ini menampung dokumen proyek. Rencana perubahan disimpan di
`plans/<divisi>/` dengan nama `PLAN-<MENU>-<FITUR>.md` dan header
`**Status Pengerjaan:**`. Paket kerja lintas divisi memakai penanda work yang
sama, mis. `PLAN-SALES-INTERVIEW-USERSALES.md`, dan index di
`plans/00-<WORK>-INDEX.md`.

## Struktur

| Folder | Isi |
|---|---|
| `plans/` | Index paket kerja dan folder rencana per divisi |
| `plans/<divisi>/` | Rencana aktif per divisi, nama `PLAN-*.md`, status jelas |
| `audit/` | Hasil audit read-only per modul |
| `reference/` | Acuan desain, database, arsitektur, dan PRD |
| `panduan/` | Runbook dan panduan operasional |
| `archive/` | Dokumen lama/superseded, disimpan untuk riwayat |

## Daftar Rencana

Paket kerja aktif: [INTERVIEW-USERSALES](plans/00-INTERVIEW-USERSALES-INDEX.md)
(Raafi: Sales, Teknisi; Yeski: Estimator, Inside Sales, Admin/Purchasing).

### Sales

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-SALES-INTERVIEW-USERSALES](plans/sales/PLAN-SALES-INTERVIEW-USERSALES.md) | Belum Dikerjakan | Prospek, DAR, quotation, KPI margin, Customer Hub |
| [PLAN-SALES-QUOTATION](plans/sales/PLAN-SALES-QUOTATION.md) | Siap Dikerjakan | Revamp Proposal menjadi Quotation |
| [PLAN-SALES-EKOSISTEM](plans/sales/PLAN-SALES-EKOSISTEM.md) | Belum Dikerjakan | Ekosistem Sales & Quotation (draft V2) |
| [PLAN-SALES-DAR-TIMELINE](plans/sales/PLAN-SALES-DAR-TIMELINE.md) | Belum Dikerjakan | Integrasi DAR & timeline follow up |
| [PLAN-SALES-DASHBOARD-KARTU](plans/sales/PLAN-SALES-DASHBOARD-KARTU.md) | Siap Dikerjakan | Urutan 5 kartu KPI + konsistensi bahasa |
| [PLAN-SALES-DASHBOARD-CUSTOMER-HUB](plans/sales/PLAN-SALES-DASHBOARD-CUSTOMER-HUB.md) | Siap Dikerjakan | Privasi sales, drill-down, customer hub |
| [PLAN-SALES-QUOTATION-OTOMATIS](plans/sales/PLAN-SALES-QUOTATION-OTOMATIS.md) | Siap Dikerjakan | Quotation otomatis dari item Inside Sales |
| [PLAN-SALES-QUOTATION-PDF](plans/sales/PLAN-SALES-QUOTATION-PDF.md) | Siap Dikerjakan | Template quotation per PT |
| [PLAN-SALES-PO-DAN-CONVERT](plans/sales/PLAN-SALES-PO-DAN-CONVERT.md) | Siap Dikerjakan | PO selaras, convert aman, PIC engineer |
| [PLAN-SALES-REPORT-MEETING](plans/sales/PLAN-SALES-REPORT-MEETING.md) | Siap Dikerjakan | Report harian otomatis dari Add Meeting |

### Estimator

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-ESTIMATOR-INTERVIEW-USERSALES](plans/estimator/PLAN-ESTIMATOR-INTERVIEW-USERSALES.md) | Belum Dikerjakan | Harga modal terpisah, riwayat, report |
| [PLAN-SALES-ESTIMATOR-PRICE-REQUEST](plans/estimator/PLAN-SALES-ESTIMATOR-PRICE-REQUEST.md) | Siap Dikerjakan | Estimator, histori harga, export Excel |
| [PLAN-SALES-ESTIMATOR-DASHBOARD](plans/estimator/PLAN-SALES-ESTIMATOR-DASHBOARD.md) | Belum Dikerjakan | Dashboard kerja Estimator |

### Inside Sales

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-INSIDE-SALES-INTERVIEW-USERSALES](plans/inside-sales/PLAN-INSIDE-SALES-INTERVIEW-USERSALES.md) | Belum Dikerjakan | Riwayat harga, margin, jasa teknisi, PDF |
| [PLAN-SALES-INSIDE-DASHBOARD](plans/inside-sales/PLAN-SALES-INSIDE-DASHBOARD.md) | Siap Dikerjakan | Dashboard kerja Inside Sales |

### Admin

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-ADMIN-PURCHASING-INTERVIEW-USERSALES](plans/admin/PLAN-ADMIN-PURCHASING-INTERVIEW-USERSALES.md) | Belum Dikerjakan | PI read-only, purchasing saving, logistik |
| [PLAN-ADMIN-END-TO-END](plans/admin/PLAN-ADMIN-END-TO-END.md) | Siap Dikerjakan | Integrasi Sales, Purchasing, Logistik & Finance |
| [PLAN-ADMIN-PO-VERIFIKASI](plans/admin/PLAN-ADMIN-PO-VERIFIKASI.md) | Belum Dikerjakan | Verifikasi PO customer oleh Admin |
| [PLAN-ADMIN-SALES-TARGET-SYNC](plans/admin/PLAN-ADMIN-SALES-TARGET-SYNC.md) | Siap Dikerjakan | Realisasi target sales dari pembayaran finance |

### Teknisi

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-TEKNISI-INTERVIEW-USERSALES](plans/teknisi/PLAN-TEKNISI-INTERVIEW-USERSALES.md) | Belum Dikerjakan | Layout plan, survey, biaya jasa |
| [PLAN-TEKNISI-NOTIF-JADWAL](plans/teknisi/PLAN-TEKNISI-NOTIF-JADWAL.md) | Siap Dikerjakan | Notifikasi & jadwal teknisi |

### Marketing

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-MARKETING-LEAD-TANPA-CUSTOMER](plans/marketing/PLAN-MARKETING-LEAD-TANPA-CUSTOMER.md) | Belum Dikerjakan | Lead tanpa customer |

### Customer

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-CUSTOMER-PIC](plans/customer/PLAN-CUSTOMER-PIC.md) | Siap Dikerjakan | Satu sumber kontak PIC |

### Management

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-MANAGEMENT-MONITORING-REPORT-SALES](plans/management/PLAN-MANAGEMENT-MONITORING-REPORT-SALES.md) | Belum Dikerjakan | Monitoring kepatuhan report sales |

### Dashboard

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-DASHBOARD-FRONTEND](plans/dashboard/PLAN-DASHBOARD-FRONTEND.md) | Siap Dikerjakan | Wireframe sistem operasional |

### Sistem

| Plan | Status | Keterangan |
|---|---|---|
| [PLAN-SISTEM-END-TO-END-BRANCHES](plans/sistem/PLAN-SISTEM-END-TO-END-BRANCHES.md) | Siap Dikerjakan | Urutan branch end-to-end |
| [PLAN-SISTEM-NOTIFIKASI-REALTIME](plans/sistem/PLAN-SISTEM-NOTIFIKASI-REALTIME.md) | Siap Dikerjakan | Notifikasi realtime |
| [PLAN-SISTEM-NOTIF-POPUP](plans/sistem/PLAN-SISTEM-NOTIF-POPUP.md) | Selesai | Pop up notifikasi pojok layar |
| [PLAN-SISTEM-ROADMAP-DESEMBER](plans/sistem/PLAN-SISTEM-ROADMAP-DESEMBER.md) | Belum Dikerjakan | Roadmap Desember & AI Agent |

## Audit

- `audit/AUDIT-SALES-ADMIN-FLOW.md`
- `audit/AUDIT-MARKETING.md`
- `audit/AUDIT-QUOTATION.md`
- `audit/AUDIT-SETTINGS-ADMIN-PANEL.md`
- `audit/AUDIT-UNUSED-PAGES.md`
- `audit/AUDIT-SECURITY.md`
- `audit/AUDIT-ROLE-PERMISSION.md`
- `audit/PROJECT-SPEC-AUDIT.md`

## Referensi

- `reference/DESIGN.md`
- `reference/DARK-MODE-SYSTEM.md`
- `reference/DARK-THEME-PALETTE.md`
- `reference/LOGIN-PAGE-SPEC.md`
- `reference/SISTEM-WEB.md`
- `reference/GOOGLE-CALENDAR.md`
- `reference/DATABASE-V1.md`
- `reference/ERD-V1.md`
- `reference/SYSTEM-DESIGN-V1.md`
- `reference/PRD-PROJECT-WORKFLOW.md`
- `reference/TENTANG-WEBSITE.md`

## Panduan

- `panduan/DATABASE-RESTORE-RUNBOOK.md`
- `panduan/PANDUAN-UAT-END-TO-END.md`

## Arsip

### Rencana Lama

- `archive/plans/PLAN-SALES-HANDOFF-ADMIN.md`
- `archive/plans/PLAN-SISTEM-NOTIFIKASI-TEKNISI.md`
- `archive/plans/PLAN-SISTEM-AUDIT-NOVEMBER.md`

### Dokumen Lain

- `archive/COMPLETE-SALES-ECOSYSTEM-PLAN-V1.md`
- `archive/DARK-PALETTE-OLD.md`
- `archive/CHANGELOG-TAHAP5.md`
- `archive/HANDOVER-2026-08-26.md`
- `archive/PRD-INTERVIEW-NOTES.md`
- `archive/WAWANCARA-USER-SALES.md`

## Catatan

Dokumen spesifikasi lengkap dan hasil bedah codebase ada di
`audit/PROJECT-SPEC-AUDIT.md`.
