# Module Inventory

**Audit:** Phase 1 - CRM Application Architecture
**Repository:** `/var/www/3dyapp`
**Branch:** `main` at `de8103b4`
**Date:** 2026-11-23
**Mode:** Read-only inspection. No source, schema, or data changes.

## 1. Scope and Method

This inventory is derived from the active route registry (`routes/web.php`), controllers, models, services, Blade views, and the seeded role matrix. Route files that exist but are not loaded are called out separately.

Counts used in this document:

| Item | Count | Source |
|---|---:|---|
| Active route definitions in `routes/web.php` | 479 `->name()` calls, 432 unique name strings (static count) | `routes/web.php` |
| Top-level controllers | 65 files, plus 9 in `Auth/` | `app/Http/Controllers` |
| Models | 68 | `app/Models` |
| Top-level services | 23, plus 6 in subdirectories | `app/Services` |
| Blade views | 310 | `resources/views` |
| Livewire components | 3 | `app/Livewire` |
| Policies | 10 | `app/Policies` |
| Observers | 6 | `app/Observers` |
| Notifications | 36 | `app/Notifications` |

**Limitation:** route counts are static. `php artisan route:list` was not executed in this read-only phase, so the runtime route table is not independently verified.

## 2. Module Summary

| Module | Primary responsibility | Main entry routes | Main controllers |
|---|---|---|---|
| Marketing | Lead intake, partners, WhatsApp inbox, lead documents | `/leads`, `/partners`, `/whatsapp-center` | `LeadController`, `PartnerController`, `WhatsAppCenterController` |
| Sales | Follow-up, meeting, schedule, quotation, price request, DAR, KPI | `/sales/*`, `/leads/pipeline`, `/sales/my-leads` | `ProposalController`, `FollowUpController`, `SalesMeetingController`, `ManageSalesController`, `SalesDarController` |
| Inside Sales | Task-based quotation preparation and quotation items | `/lead-tasks`, `/inside-sales` | `LeadTaskController`, `InsideSalesController`, `InsideSalesQuotationController` |
| Estimator | Product cost requests and price history | `/estimator` | `EstimatorRequestController`, `ProductController` |
| Admin / Finance | Customer PO verification, invoices, payments, BAST | `/admin/*` | `AdminController`, `PurchasingHandoffController`, `BastController` |
| Procurement | Supplier PO, goods receipt, goods delivery, supplier invoice/payment | `/admin/supplier-*`, `/admin/goods-*` | `SupplierPurchaseOrderController`, `GoodsReceiptController`, `GoodsDeliveryController`, `SupplierInvoiceController`, `SupplierPaymentController` |
| Technician | Project execution, survey, sizing, installation, field work | `/teknisi/*`, `/projects/*` | `ProjectController`, `SurveyController`, `SizingProjectController`, `InstalasiController`, `FieldWorkController` |
| Management | Lead assignment, team KPI, activity log, drill-down | `/manage-sales/*`, `/manage/*`, `/monitoring` | `ManageSalesController`, `MonitoringController`, `AdminPanelController` |
| Customer and Documents | Customer master, contacts, unified document center, trash | `/customers`, `/documents`, `/trash` | `CustomerController`, `CustomerContactController`, `DocumentCenterController`, `TrashController` |
| Platform / System | Auth, notifications, AI assistant, settings, admin panel, dev tools | `/login`, `/notifications`, `/ai/assistant`, `/settings`, `/admin-panel` | `Auth\*`, `NotificationController`, `OfficeAssistantController`, `AdminPanelController` |

## 3. Module Detail

### 3.1 Marketing

**Responsibilities**
- Create and maintain leads, prospects, and customer data while the customer is still a lead.
- Capture lead source, segment, need type, PT group, attachments, and assignment.
- Manage partners (suppliers, vendors, contractors, distributors) separate from procurement suppliers.
- Operate the WhatsApp Center: inbox, account ownership, takeover/release, bot settings, and conversion to a lead.
- Import leads from XLSX.

**Routes**
- Lead CRUD and import: `routes/web.php` lines 700-783.
- Lead status, convert, promote, batch status: lines 724-738.
- Partners: lines 716-722, 750-755.
- WhatsApp Center: lines 788-808.
- Webhooks: lines 984-988.
- Lead documents and attachments: lines 757-771.

**Controllers**
- `app/Http/Controllers/LeadController.php` (1,375 lines)
- `app/Http/Controllers/PartnerController.php`
- `app/Http/Controllers/WhatsAppCenterController.php` (1,143 lines)

**Models**
- `Lead`, `LeadActivity`, `LeadDocument`, `Partner`, `Customer`, `CustomerContact`, `WhatsappAccount`, `WhatsappConversation`, `WhatsappMessage`.

**Services**
- `LeadPipelineService` (transition guard), `CustomerContactService`, `CustomerLifecycleService`, `WhatsappGateway`, `WhatsappBot`.

**Views**
- `resources/views/leads` (10), `partners` (4), `whatsapp-center` (2), `marketing` (1).

**Dependencies**
- Customer lifecycle, notifications (`NewLeadNotification`, `WhatsappInboundNotification`), document registry, Sales assignment.

**Main operations**
- `LeadController@store` creates a customer with status `lead` and a lead, then notifies an assignee or management.
- `LeadController@pipeline` is locked to the authenticated salesperson's own leads.
- `WhatsAppCenterController@convert` creates a customer and lead from a WhatsApp sender.

**Verified behavior**
- `LeadController@store` sets `customer.status = 'lead'` explicitly (`LeadController.php:518-528`).
- `LeadController@store` sets the assignee to the current salesperson unless the actor has `manage-marketing` (`LeadController.php:513-515`).
- WhatsApp conversion creates a customer without an explicit status, but the column default is `lead` (`database/migrations/2026_07_24_000000_add_company_and_status_to_customers_table.php`).

### 3.2 Sales

**Responsibilities**
- Follow-up and meeting logging.
- Sales schedules and calendar.
- Quotation lifecycle from draft to accepted/rejected/expired.
- Price requests to estimator/technician.
- Sales request hub, daily activity report, KPI, my-leads export.
- Project read access for the owning salesperson.

**Routes**
- Meetings: `routes/web.php` lines 197-210.
- Follow-up and POC: lines 212-227.
- Schedules, technical requests, quotations: lines 229-272.
- Price requests: lines 274-285.
- Estimator and product catalog: lines 287-350.
- Request hub and quotation detail: lines 352-375.
- My Leads and quick actions: lines 841-856.
- DAR: lines 858-866.
- Sales dashboard: line 842.

**Controllers**
- `ProposalController` (1,213 lines), `FollowUpController`, `SalesMeetingController`, `SalesScheduleController`, `PriceRequestController`, `SalesRequestHubController`, `SalesDarController`, `SalesFinanceController`, `ManageSalesController` (1,007 lines).

**Models**
- `FollowUp`, `SalesSchedule`, `Proposal`, `ProposalItem`, `ProposalVersion`, `ProposalAttachment`, `PriceRequest`, `SalesTarget`, `Poc`.

**Services**
- `SalesService`, `SalesKpiService`, `SalesActivityService`, `SalesReportStatusService`, `QuotationNumberService`, `QuotationVersionService`, `QuotationPdfService`.

**Views**
- `resources/views/sales` (60), `manage-sales` (8).

**Dependencies**
- Lead/Customer, Product, Estimator, Technician, Admin handoff, Document Center, notifications.

**Main operations**
- Quotation creation, revision, sending, response, PDF, PO upload, project conversion.
- Follow-up completion, reopening, snoozing, and automatic next follow-up.

### 3.3 Inside Sales

**Responsibilities**
- Receive task requests from Sales or Management.
- Fill quotation items and upload quotation files.
- Generate a draft quotation from accepted task items/file.
- Work only from tasks assigned to the authenticated inside-sales user.

**Routes**
- `routes/web.php` lines 868-911.

**Controllers**
- `LeadTaskController` (936 lines), `InsideSalesController`, `InsideSalesQuotationController`.

**Models**
- `LeadTask`, `LeadTaskItem`, `LeadTaskComment`, `LeadTaskAttachment`, `LeadTaskRequestFile`, `Proposal`.

**Services**
- `QuotationNumberService`, `QuotationVersionService`, `QuotationPdfService`, `Rupiah`.

**Views**
- `resources/views/lead-tasks` (6), `inside-sales` (2).

**Dependencies**
- Sales leads, product/price log, estimator, quotation, notifications.

**Verified behavior**
- Task item and attachment mutation is limited to the assignee or management (`LeadTaskController.php:70-78`).
- Quotation creation from a task is implemented in two places: `InsideSalesQuotationController@store` and `LeadTaskController@acceptAttachment`.

### 3.4 Estimator

**Responsibilities**
- Answer product cost requests from Sales.
- Maintain price history and product cost data.
- Export price request data.

**Routes**
- `routes/web.php` lines 287-350.

**Controllers**
- `EstimatorRequestController`, `ProductController`.

**Models**
- `EstimatorRequest`, `EstimatorRequestItem`, `Product`, `ProductPriceLog`, `ProductSource`, `Supplier`.

**Services**
- `ProductPriceService`, `BulkProductPriceUpdateService`, `QuotationPdfService` (indirect).

**Views**
- `resources/views/estimator` (5), `product-knowledge` (2).

**Dependencies**
- Product catalog, supplier master, quotation item cost snapshot, notifications.

**Main operations**
- Create request, answer with cost, accept/revision/cancel, price history, Excel export.
- Bulk price update (Estimator/Purchasing).

### 3.5 Admin and Finance

**Responsibilities**
- Verify customer PO on accepted quotations.
- Create customer invoices, customer payments, and receipts.
- Manage BAST (project and installation-level).
- Admin hub read models and procurement reports.
- Finance read access to customer payment proofs and BAST.

**Routes**
- `routes/web.php` lines 564-695.
- PO verification: lines 621-623.
- Invoice/PO/payment: lines 567-624.
- Read-only admin: lines 626-695.

**Controllers**
- `AdminController`, `PurchasingHandoffController`, `BastController`, `PurchasingReportController`, `SalesFinanceController` (sales-side invoice reader).

**Models**
- `Invoice`, `InvoiceItem`, `Payment`, `PurchaseOrder`, `Proposal` (PO fields), `Project` (BAST fields).

**Services**
- `AdminService`, `ProcurementService`.

**Views**
- `resources/views/admin` (41).

**Dependencies**
- Sales quotation, procurement, project/BAST, document registry, notifications, customer.

**Main operations**
- Invoice creation/update/delete/print; payment recording; status derivation.
- PO verification/return; BAST update and commercial closing evaluation.

### 3.6 Procurement

**Responsibilities**
- Create Supplier PO from an accepted and PO-verified quotation.
- Record goods receipts and verify quantities/serials.
- Create delivery notes and move them to prepared/shipped/delivered.
- Record supplier invoices and supplier payments.

**Routes**
- `routes/web.php` lines 588-619 (write) and 641-695 (read).

**Controllers**
- `SupplierController`, `SupplierPurchaseOrderController`, `GoodsReceiptController`, `GoodsDeliveryController`, `SupplierInvoiceController`, `SupplierPaymentController`, `PurchasingReportController`.

**Models**
- `Supplier`, `SupplierPurchaseOrder`, `SupplierPurchaseOrderItem`, `GoodsReceipt`, `GoodsReceiptItem`, `GoodsDelivery`, `GoodsDeliveryItem`, `SupplierInvoice`, `SupplierPayment`.

**Services**
- `ProcurementService`.

**Views**
- Included under `resources/views/admin` (41 total).

**Dependencies**
- Proposal/items, project, supplier master, invoice/project closing.

**Verified behavior**
- Supplier PO creation is blocked unless `Proposal::isReadyForSupplierPo()` is true (`SupplierPurchaseOrderController.php:109`).
- That gate means accepted quotation + uploaded PO + verified PO + not returned (`Proposal.php:243-248`).
- Supplier PO can exist before a project; project conversion later backfills `project_id` on orphan procurement rows (`ProposalController.php:1120-1135`).

### 3.7 Technician

**Responsibilities**
- Project execution and progress.
- Survey, sizing, installation, work result, technical requests.
- Technician schedule and Google Calendar.
- Upload technical documents and BAST for installation jobs.
- Field work list combining survey and installation.

**Routes**
- Project read/write: `routes/web.php` lines 396-451.
- Technician calendar: lines 453-474.
- Survey/sizing/installation/request: lines 476-547.
- Field work: lines 479-506.

**Controllers**
- `ProjectController`, `ProjectTaskController`, `ProjectSupportController`, `ProjectDocumentController`, `TechnicianDashboardController`, `TechnicianScheduleController`, `TechnicianTaskController`, `SurveyController`, `SizingProjectController`, `InstalasiController`, `FieldWorkController`, `WorkResultController`.

**Models**
- `Project`, `ProjectTask`, `ProjectSupport`, `Survey`, `SizingProject`, `Instalasi`, `TechnicalRequest`, `TechnicianSchedule`, `PriceRequest`.

**Services**
- `ProjectService`, `ProjectWorkflowService`, `ProjectDocumentCleanup`, `GoogleCalendarService`, `DocumentRegistry`.

**Views**
- `resources/views/teknisi` (25), `projects` (4), `project_documents` (5), `project_tasks` (1), `project_supports` (2).

**Dependencies**
- Proposal/convert, price request, document center, customer, admin BAST, notifications.

**Main operations**
- Create/manual project (technician/management only), update project, manage tasks/supports, create survey/sizing/installation, update BAST.

### 3.8 Management

**Responsibilities**
- Assign/reassign leads to sales.
- Supervise team activity, KPI, targets, and salesperson drill-down.
- Read-only monitoring across divisions.
- Manage marketing/technical placeholders.
- Admin panel for users, roles, settings, project statuses, document categories, audit log.

**Routes**
- `manage-sales`: `routes/web.php` lines 810-825.
- `manage`: lines 827-839.
- Monitoring: lines 913-918.
- Admin panel: lines 920-971.
- Dev work log: lines 973-980.

**Controllers**
- `ManageSalesController`, `MonitoringController`, `AdminPanelController`, `AccountManagerController`, `WorkTypeController`, `DocumentCategoryController`, `ProjectStatusController`/`ProjectStatusList`, `DevWorkLogController`.

**Models**
- `Lead`, `SalesTarget`, `User`, `Role`, `Permission`, `ProjectStatus`, `DocumentCategory`, `AppSetting`.

**Services**
- `SalesKpiService`, `SalesReportStatusService`, `DevWorkLogService`.

**Views**
- `manage-sales` (8), `admin-panel` (19), `monitoring` (1), `manage` (1), `developer` (1).

### 3.9 Customer and Documents

**Responsibilities**
- Customer master and contacts, with lead-to-deal lifecycle.
- Customer timeline, NPWP download, project cards.
- Unified Document Center across project, quotation, customer PO, supplier invoice/payment, goods receipt, lead, and NPWP.
- Trash for customers, projects, documents.
- Document versioning, archive, restore, category/type changes.

**Routes**
- Customers: `routes/web.php` lines 150-195.
- Documents: lines 181-195.
- Project documents: lines 420-451.
- Trash: lines 549-562.

**Controllers**
- `CustomerController`, `CustomerContactController`, `DocumentCenterController`, `DocumentRepositoryController`, `ProjectDocumentController`, `DocumentCategoryController`, `TrashController`.

**Models**
- `Customer`, `CustomerContact`, `ProjectDocument`, `DocumentCategory`, `DocumentActivity`, `LeadDocument`.

**Services**
- `DocumentCenterService`, `DocumentRegistry`, `DocumentActivityLogger`, `DocumentCenterAccess`, `DocumentAccess`, `CustomerContactService`, `CustomerLifecycleService`.

**Views**
- `customers` (12), `documents` (5), `trash` (1), `customer_contacts` (2).

**Verified behavior**
- Document Center is a read model that unions multiple source tables (`DocumentCenterService::authorizedQuery`, lines 123-196).
- Transaction documents are separated from technical documents by `doc_type` (`ProjectDocument` DOC_TYPES, policy checks).
- Customer default list hides `status = lead` unless `?status=lead|all` is passed (`CustomerController.php:56-64`).

### 3.10 Platform and System

**Responsibilities**
- Authentication, password reset, Google OAuth, pending approval.
- Notifications (database, Reverb, web push), toast queue.
- AI assistant and knowledge base.
- Settings/profile/appearance.
- Admin panel (super-admin only by permission).
- Health endpoint and webhook.

**Routes**
- `routes/auth.php`, `routes/web.php` lines 70-148, 913-988.
- `routes/channels.php`, `routes/console.php`.

**Controllers**
- `Auth\*` (9), `NotificationController`, `PushSubscriptionController`, `OfficeAssistantController`, `KnowledgeBaseController`, `ProductKnowledgeController`, `SettingsController`, `ProfileController`, `AdminPanelController`.

**Models**
- `User`, `DatabaseNotification`, `AppSetting`, `KnowledgeBaseDocument`, `KnowledgeChunk`, `KnowledgeDocument`, `ProductSource`.

**Services**
- `NotificationPresenter`, `SessionRevoker`, `PageContext`, `OnlineStatus` (unused), `Knowledge\*`, `FirecrawlService`.

**Views**
- `auth` (7), `layouts` (8), `profile` (4), `settings` (3), `ai` (1), `knowledge-base` (1), `errors` (2), `legal` (2).

## 4. Cross-Cutting Components

| Component | Responsibility | Evidence |
|---|---|---|
| `CustomerAccess` | Restrict customer queries for sales-like users; everyone else sees all | `app/Support/CustomerAccess.php:25-100` |
| `PtAccess` | Restrict writes by `sales-<pt>` role; unrestricted for management/super-admin | `app/Support/PtAccess.php:20-74` |
| `DocumentAccess` | Per-document trash/view/manage rules | `app/Support/DocumentAccess.php:29-292` |
| `DocumentCenterAccess` | Query scoping for the unified document read model | `app/Support/DocumentCenterAccess.php` |
| `SparklineBuilder` | Historical KPI sparkline data | `app/Support/SparklineBuilder.php` |
| `Pricing` / `Rupiah` | Money and margin formatting | `app/Support/Pricing.php`, `Rupiah.php` |
| `PageContext` | Breadcrumb, section label, workspace tab metadata | `app/Support/PageContext.php` |
| `ProjectWorkflowService` | Single evaluator for six project steps | `app/Services/Project/ProjectWorkflowService.php` |
| `DocumentRegistry` | One file = one document row across modules | `app/Services/DocumentRegistry.php` |
| `NotificationPresenter` | Uniform notification payload for bell/feed | `app/Support/NotificationPresenter.php` |

## 5. Routes Not Part of the Active Application

These files exist but are not required by `bootstrap/app.php` or `routes/web.php`:

- `routes/admin.php`
- `routes/admin-panel.php`
- `routes/marketing.php`
- `routes/monitoring.php`
- `routes/teknisi.php`

They contain route names that duplicate active routes in `routes/web.php` (for example `leads.index`, `invoices.index`, `projects.index`, `dashboard`, `monitoring.index`). Editing them has no runtime effect.

`routes/api.php` is also not loaded because the `api:` entry is commented out in `bootstrap/app.php:24`; it references `auth:sanctum`, but Sanctum is not installed.

## 6. Module Findings

### MOD-01 - Orphaned route files duplicate active route names

**Finding ID:** MOD-01

- **Status:** Verified
- **Description:** Five route files are dead but contain names already registered in `routes/web.php`.
- **Evidence:** No `require` or provider reference outside `routes/web.php`; `routes/web.php:982` requires only `auth.php`. Duplicate names found by static comparison (for example `leads.index`, `invoices.index`, `teknisi.dashboard`).
- **File paths:** `routes/admin.php`, `routes/admin-panel.php`, `routes/marketing.php`, `routes/monitoring.php`, `routes/teknisi.php`.
- **Impact:** A developer can edit an apparently valid route file and see no behavior change. It also inflates the apparent route surface during reviews.
- **Severity:** Medium
- **Recommendation:** Delete or move them to `docs/archive/` after confirming no external script reads them. Add a route-registry test that asserts only intended files are loaded.

### MOD-02 - Three separate price-request concepts

**Finding ID:** MOD-02

- **Status:** Verified, intent needs confirmation
- **Description:** `PriceRequest` (installation/technical price request), `EstimatorRequest` (product cost request), and `RequestHarga` (project-scoped technician request) coexist.
- **Evidence:** `app/Models/PriceRequest.php:18-21`, `app/Models/EstimatorRequest.php`, `app/Models/RequestHarga.php:9-23`; route groups at `routes/web.php:274-321` and `routes/web.php:497-537`.
- **Impact:** Users and developers must know which request type belongs to which menu. Reporting and notification logic can drift.
- **Severity:** Low
- **Recommendation:** Document the three concepts in one place and add a decision table. Do not merge them without a business decision.

### MOD-03 - Lead creation logic exists in two controllers

**Finding ID:** MOD-03

- **Status:** Verified
- **Description:** `LeadController@store` and `WhatsAppCenterController@convert` both create customer, contact, lead, activity, and notification records, with different rules.
- **Evidence:** `LeadController.php:473-604`; `WhatsAppCenterController.php:446-537`.
- **Impact:** WhatsApp-created leads do not go through `LeadPipelineService`, `guardSalesAssignee`, or the same validation. Future changes can make the two entry points diverge.
- **Severity:** Medium
- **Recommendation:** Extract a `LeadIntakeService` with explicit source-specific options, then call it from both controllers. Keep the WhatsApp source marker.

### MOD-04 - Module ownership is enforced mostly at the route layer

**Finding ID:** MOD-04

- **Status:** Verified
- **Description:** Modules share `Customer`, `Lead`, `Proposal`, and `Project` tables, and controllers in different modules write to the same records. There is no domain-level module boundary or ownership service for most cross-module writes.
- **Evidence:** `ProposalController@convertToProject` writes `Project`, `SupplierPurchaseOrder`, `GoodsReceipt`, and `SupplierInvoice` (`ProposalController.php:1090-1160`); `AdminService` writes `ProjectDocument` rows for invoices/PO/payments (`AdminService.php:500-535`).
- **Impact:** Changes to one module can silently affect another. Authorization must be checked at every write path, not only at the menu.
- **Severity:** Info
- **Recommendation:** Treat the shared kernel (`Customer`, `Lead`, `Proposal`, `Project`, `ProjectDocument`) as an explicit shared domain and require service-level writes for cross-module updates.

### MOD-05 - Module views are unevenly distributed

**Finding ID:** MOD-05

- **Status:** Verified
- **Description:** Sales owns 60 views and Admin 41, while Estimator, Inside Sales, Monitoring, and Marketing each own 1-5 views. Much module UI is embedded in shared or larger folders.
- **Evidence:** View counts from `resources/views` (Sales 60, Admin 41, Technician 25, Admin Panel 19, Estimator 5, Inside Sales 2, Monitoring 1, Marketing 1).
- **Impact:** Navigation and ownership are harder to infer from folder names. Reviewers must search across folders.
- **Severity:** Info
- **Recommendation:** When touching a module, move its views into the module folder only if the move is part of the same user-visible package. Do not perform a mass move without approval.

## 7. Verification Notes

- All module names above correspond to active route groups or active controllers.
- `routes/marketing.php`, `routes/admin.php`, `routes/admin-panel.php`, `routes/monitoring.php`, and `routes/teknisi.php` are **not** active.
- The application has no separate API module at runtime; `routes/api.php` is not loaded.
- The Admin Panel is effectively super-admin only because its route gate uses `manage-monitoring`, which only `super-admin` receives from the seeder.
