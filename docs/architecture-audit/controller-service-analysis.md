# Controller and Service Analysis

**Audit:** Phase 1 - CRM Application Architecture
**Repository:** `/var/www/3dyapp`
**Date:** 2026-11-23
**Mode:** Read-only inspection

## 1. Controller Metrics

| Controller | LOC | Public methods | Private methods | Injected services | Primary concern |
|---|---:|---:|---:|---|---|
| `LeadController` | 1,375 | 26 | 12 | `CustomerContactService`, `CustomerLifecycleService`, `LeadPipelineService` | Lead lifecycle, pipeline, documents, import |
| `ProposalController` | 1,213 | 24 | 18 | `QuotationNumberService`, `QuotationVersionService`, `QuotationPdfService` | Quotation lifecycle, PO, conversion |
| `WhatsAppCenterController` | 1,143 | 21 | 20 | `WhatsappGateway`, `WhatsappBot`, `CustomerContactService` | WhatsApp UI, webhook, conversion |
| `ManageSalesController` | 1,007 | 18 | 15 | `SalesService`, `SalesKpiService` (method injection), `SalesReportStatusService` | Lead assignment, dashboard, KPI, my leads |
| `LeadTaskController` | 936 | 21 | 8 | `QuotationNumberService`, `QuotationVersionService` | Task CRUD, quotation items, attachments |
| `AdminController` | 458 | 23 | 2 | `AdminService` | Invoice, PO, payment, hub |
| `ProjectController` | 216 | 8 | 0 | `ProjectService` | Project CRUD and workflow display |

Total top-level controller code: **20,579 lines**.

## 2. Deep Dive: LeadController

### 2.1 Responsibilities

`LeadController` currently handles:

1. Lead listing, filtering, and pipeline board.
2. Marketing dashboard and monitoring.
3. Lead CRUD, customer creation, contact synchronization.
4. Status changes, batch status, loss outcome.
5. Lead-to-project conversion fallback.
6. Customer promotion.
7. Attachments and document preview/download.
8. XLSX import.
9. Activity timeline and monitoring.

### 2.2 Method Map

| Method | Business operation | Notes |
|---|---|---|
| `index` | Lead list with filters | Marketing route only |
| `pipeline` | Sales kanban | Locked to `assigned_to = auth id` (`:93`) |
| `dashboard` | Marketing dashboard | Large query block |
| `updateStatus` | Status transition | Delegates guard to `LeadPipelineService`; writes payload inline |
| `batchUpdateStatus` | Bulk status | Duplicates `updateStatus` logic |
| `create` / `store` | Lead intake | Creates customer, contact, lead, attachments, activity |
| `show` | Lead detail | Loads many relations |
| `timeline` | Follow-up timeline | JSON/partial |
| `edit` / `update` | Lead edit | Sales cannot reassign own lead (`:718-721`) |
| `destroy` | Soft delete | Policy delete |
| `saveOutcome` | Loss reason/note | Uses `authorizeLeadStatus` |
| `convert` | Direct lead-to-project | Admin/technician fallback |
| `promoteCustomer` | Customer promotion | No ownership policy call |
| `activities` | Activity log | Management/marketing |
| `monitoring` | Marketing monitoring | Role-based recap |
| `importForm` / `import` | XLSX intake | PhpSpreadsheet |

### 2.3 Business Logic Inside the Controller

- Customer creation with explicit `status = lead` (`:517-528`).
- Contact creation/synchronization (`:532-557`).
- Assignment guard (`:166-177`).
- Status payload construction: `acknowledged_at`, `closed_at`, `lost_reason`, `lost_note` (`:196-220`).
- Direct project creation with `Project::create` (`:932-939`).
- Import parsing and row validation (`:1168-1375`).

### 2.4 Duplication

- Lead intake duplicates `WhatsAppCenterController@convert` (`WhatsAppCenterController.php:446-537`).
- `batchUpdateStatus` duplicates most of `updateStatus` (`:227-280` vs `:179-225`).
- Document preview/download duplicates `ProjectDocumentController` behavior (`:1141-1167`).
- Salesperson lookup uses `User::role('sales')` while other code supports `sales-*` (`:1008`).

### 2.5 Complexity Assessment

**High.** The controller has 26 endpoints, multiple actors, and at least four distinct subdomains (lead lifecycle, intake, documents, import). It is the largest controller in the application.

**Maintainability:** Low to medium. Changes to lead intake or status rules require scanning a 1,375-line file.

## 3. Deep Dive: ProposalController

### 3.1 Responsibilities

1. Quotation listing, KPI, scoping.
2. Quotation create/update with items, tax, margin, payment terms.
3. Status transitions: ready, send, viewed, revise, accept/reject, cancel.
4. Technical review handoff.
5. PDF generation/download.
6. Attachment download/preview.
7. Customer PO upload/download.
8. Conversion to project.

### 3.2 Method Map

| Method group | Methods | Business operation |
|---|---|---|
| Listing | `index`, `create`, `show`, `preview` | Scoped list, KPI, lead selection |
| Drafting | `store`, `edit`, `update` | Item persistence, totals, versioning, PDF |
| Status | `markReady`, `submitTechnical`, `returnTechnical`, `completeTechnical`, `send`, `markSentVia`, `markViewed`, `revise`, `respond`, `cancel` | Commercial state machine |
| Files | `downloadAttachment`, `previewAttachment`, `downloadPdf`, `uploadPo`, `downloadPo` | Secure file access |
| Conversion | `convertToProject` | Project + procurement handoff |

### 3.3 Business Logic Inside the Controller

- `assertQuotationHasBillableContent` (`:61-70`).
- Item normalization and cost snapshots (`:76-89`, `:316-412`).
- Cost tampering guard (`:293-305`).
- Payment scheme validation (`:278-288`).
- Owner/management/technical authorization helpers (`:91-253`).
- Quotation numbering wrapper (`:233-240`).
- Lead WON transition inside `respond` transaction (`:924-960`).
- Project creation transaction, procurement backfill, notifications (`:1079-1170`).

### 3.4 Duplication

- Quotation creation duplicates `InsideSalesQuotationController@store` and `LeadTaskController@acceptAttachment`.
- Item mapping is repeated in all three creation paths.
- `generateNumber` is a thin wrapper over `QuotationNumberService`; acceptable, but duplicated in `LeadTaskController`.
- Docblocks around lines 96-108 are stale: a comment for `authorizeView` is followed by comments for download/store file methods, but the methods are elsewhere.

### 3.5 Complexity Assessment

**High but cohesive.** The controller owns one commercial aggregate (the quotation) and its handoff. Splitting it is not automatically desirable; the risk is duplicated quotation creation elsewhere.

## 4. Deep Dive: WhatsAppCenterController

### 4.1 Responsibilities

1. Account list and status.
2. Conversation/contact/message listing.
3. Send reply, mark read, takeover/release.
4. Convert sender to customer/lead.
5. Save contact.
6. Green API and Meta webhooks.
7. Inbound/outbound notification handling.
8. Credentials and bot settings.
9. Simulation for testing.

### 4.2 Method Map

| Group | Methods |
|---|---|
| Inbox UI | `index`, `status`, `checkStatus`, `conversations`, `messages`, `contacts` |
| Conversation actions | `saveContact`, `markRead`, `preference`, `store`, `takeover`, `release` |
| Conversion | `convert` |
| Webhook | `verifyWebhook`, `webhook`, `handleNotification`, `handleMetaNotification` |
| Configuration | `updateCredentials`, `updateBotSettings`, `simulate` |
| Internals | `visibleAccounts`, `authorizeAccount`, `customerNumberIndex`, `findCustomer`, `ensureSenderAccessible`, `normalizeNumber` |

### 4.3 Business Logic Inside the Controller

- Webhook signature verification is fail-closed for both Meta and Green API (`:585-654`).
- Account matching by `gateway_instance` (`:655-685`).
- Sender visibility: prevents a marketing user from reading contacts owned by another company account (`:1055-1124`).
- Number normalization and ambiguous customer mapping (`:999-1053`).
- Lead creation from WhatsApp conversion (`:446-537`).

### 4.4 Duplication

- `convert` duplicates lead/customer/contact intake from `LeadController@store`.
- `saveContact` also creates or updates customer records (`:257-330`), making three customer-creation paths in this controller.
- The controller combines an HTTP UI, a webhook integration, and a background polling handler.

### 4.5 Complexity Assessment

**High.** 1,143 lines with 21 public methods and 20 private helpers. The webhook path is security-sensitive; the inbox path is UI-heavy.

**Maintainability:** Low. A change to customer creation or WhatsApp security requires navigating the same large class.

## 5. Deep Dive: ManageSalesController

### 5.1 Responsibilities

1. Management lead list and edit.
2. Assign/reassign leads.
3. Sales dashboard (personal and drill-down).
4. Team activity and KPI.
5. Sales targets.
6. My Leads list, export, and quick actions.
7. Activity log.

### 5.2 Method Map

| Group | Methods |
|---|---|
| Management | `index`, `edit`, `update`, `assign` |
| Dashboard | `dashboard`, `showSalesRep`, `dashboardData`, `customerHubRows`, `customerStage` |
| Team | `teamActivity`, `kpi`, `updateTarget` |
| My Leads | `myLeads`, `exportMyLeads`, `myLeadsQuery` |
| Quick actions | `updateLeadNote`, `updateLeadNextFollowUp`, `updateLeadColor`, `logLeadContact` |
| Activity | `activityLog`, `activityLogFeed`, `activityLogData` |

### 5.3 Business Logic Inside the Controller

- Sales assignment validation (`:89-94`, `:152-155`) and notifications (`:945-998`).
- Dashboard query aggregation (`:248-368`).
- Customer stage computation (`:436-477`).
- My Leads query scoping (`:682-757`).
- Target upsert (`:584-617`).

### 5.4 Duplication

- `hasRole('sales')` checks appear in `index`, `edit`, `update`, `assign`, `kpi`, `updateTarget`, while `isSalesRep` supports `sales-*` (`:239-243`).
- Customer stage logic may overlap with `DashboardController` and `CustomerController` read models.
- Personal dashboard and management dashboard share one controller and one view family.

### 5.5 Complexity Assessment

**High.** 1,007 lines for two audiences: salespeople and managers. The route split helps, but the class does not.

## 6. Deep Dive: LeadTaskController

### 6.1 Responsibilities

1. Task list/create/show/edit/update/delete.
2. Comments.
3. Quotation items (create, batch sync, delete).
4. Quotation file attachments and request files.
5. Task completion.
6. Quotation creation from an accepted file.
7. Task chat rendering.

### 6.2 Method Map

| Group | Methods |
|---|---|
| Task CRUD | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` |
| Collaboration | `storeComment`, `complete` |
| Items | `storeItem`, `syncItems`, `destroyItem` |
| Attachments | `storeAttachment`, `downloadAttachment`, `previewAttachment`, `destroyAttachment`, `acceptAttachment` |
| Request files | `storeRequestFile`, `downloadRequestFile`, `destroyRequestFile` |
| Quotation | `generateProposalNumber`, proposal creation inside `acceptAttachment` |

### 6.3 Business Logic Inside the Controller

- Four custom authorization helpers (`:32-78`).
- Task item batch synchronization inside a transaction (`:424-551`).
- Item mapping and margin defaults (`:326-423`).
- Quotation creation from accepted file (`:670-781`).
- Quotation numbering wrapper (`:785-790`).

### 6.4 Duplication

- Proposal creation duplicates `ProposalController@store` and `InsideSalesQuotationController@store`.
- Item mapping duplicates `ProposalController@persistItems` and `InsideSalesQuotationController` item copy.
- Attachment acceptance and quotation creation are one large method.

### 6.5 Complexity Assessment

**High.** 936 lines for a task module that now also owns quotation creation. The task boundary is blurred.

## 7. Cross-Controller Duplication

### CTL-01 - LeadController is a multi-domain controller

**Finding ID:** CTL-01

- **Status:** Verified
- **Description:** 26 public methods spanning lead CRUD, status, conversion, documents, import, monitoring, and dashboard.
- **Evidence:** `LeadController.php` method map above; 1,375 lines.
- **Impact:** High review cost and high regression risk. Authorization rules differ by method.
- **Severity:** High (maintainability)
- **Recommendation:** Extract lead intake and document handling first. Keep pipeline and status in the controller until the workflow rules are stabilized.

### CTL-02 - ProposalController is large but cohesive

**Finding ID:** CTL-02

- **Status:** Verified
- **Description:** 24 public methods covering the full quotation lifecycle, files, and conversion.
- **Evidence:** `ProposalController.php` method map; 1,213 lines.
- **Impact:** The class is the commercial source of truth. Any change has cross-module effects.
- **Severity:** Medium
- **Recommendation:** Do not split by method count. Extract quotation drafting (items/tax/margin) only if it becomes shared with the other two creation paths.

### CTL-03 - WhatsAppCenterController mixes integration and UI

**Finding ID:** CTL-03

- **Status:** Verified
- **Description:** One class handles inbox pages, webhook verification, inbound parsing, credentials, bot settings, and lead conversion.
- **Evidence:** `WhatsAppCenterController.php:36-958`; 1,143 lines.
- **Impact:** Security-sensitive webhook code is coupled to UI changes. Testing requires the full HTTP stack.
- **Severity:** High
- **Recommendation:** Extract `WhatsAppWebhookService` (verify + parse + store inbound) and `WhatsAppInboxService` (visibility + conversation queries). Keep the controller as a thin HTTP adapter.

### CTL-04 - ManageSalesController serves two audiences

**Finding ID:** CTL-04

- **Status:** Verified
- **Description:** Management functions and personal sales workspace share one class.
- **Evidence:** `ManageSalesController.php:27-175` (management) and `:619-855` (my leads) in one file.
- **Impact:** Changes for salespeople can affect management screens and vice versa.
- **Severity:** Medium
- **Recommendation:** Split personal sales dashboard/my-leads from management supervision when the next dashboard change is requested.

### CTL-05 - LeadTaskController also owns quotation creation

**Finding ID:** CTL-05

- **Status:** Verified
- **Description:** Task CRUD plus item management plus proposal creation from attachment.
- **Evidence:** `LeadTaskController.php:670-781`.
- **Impact:** Inside Sales task behavior is coupled to quotation numbering, pricing, and versioning.
- **Severity:** High
- **Recommendation:** Move proposal creation into the shared quotation creation service (see ARC-04).

### CTL-06 - Quotation creation is duplicated in three controllers

**Finding ID:** CTL-06

- **Status:** Verified
- **Description:** Three independent `Proposal::create` paths with separate item mapping and defaults.
- **Evidence:** `ProposalController.php:582-623`; `InsideSalesQuotationController.php:194-260`; `LeadTaskController.php:708-769`.
- **Impact:** Inconsistent tax defaults already exist: `ProposalController` uses submitted tax/tax rate, `InsideSalesQuotationController` uses `AppSetting::vatRate()`, `LeadTaskController` uses `0`.
- **Severity:** High
- **Recommendation:** One service with source-specific defaults and a single item mapper. Add a test that all three sources produce the same totals for the same items.

### CTL-07 - Project creation is split across three paths

**Finding ID:** CTL-07

- **Status:** Verified
- **Description:** `ProjectService@create`, `ProposalController@convertToProject`, and `LeadController@convert`.
- **Evidence:** `ProjectService.php:23-70`; `ProposalController.php:1079-1170`; `LeadController.php:932-939`.
- **Impact:** Different required fields, notifications, workflow initialization, and procurement linkage.
- **Severity:** High
- **Recommendation:** Route all paths through `ProjectService` with a `source` argument and per-source validation.

### CTL-08 - BAST file storage is implemented twice

**Finding ID:** CTL-08

- **Status:** Verified
- **Description:** Project BAST and installation BAST each create/update `ProjectDocument` rows and delete old files with separate code.
- **Evidence:** `BastController.php:156-199`; `InstalasiController.php:263-309`.
- **Impact:** A change to BAST document metadata or storage path must be applied twice.
- **Severity:** Medium
- **Recommendation:** Extract `BastDocumentService` used by both controllers.

### CTL-09 - Sales-owner resolution has two different implementations

**Finding ID:** CTL-09

- **Status:** Verified
- **Description:** `AdminService::resolveSalesOwner` prefers proposal creator, then lead assignee, then project sales, then latest lead. `SalesFinanceController::ownsInvoice` checks proposal creator, lead assignee, project sales, then any lead for the customer.
- **Evidence:** `AdminService.php:412-436`; `SalesFinanceController.php:12-36`.
- **Impact:** A user may be treated as the sales owner for notification but not for invoice access, or vice versa.
- **Severity:** Low
- **Recommendation:** Extract one `SalesOwnerResolver` and use it for notification, invoice visibility, and KPI attribution.

### CTL-10 - Direct model writes and service writes coexist

**Finding ID:** CTL-10

- **Status:** Verified
- **Description:** Some controllers use services (`AdminController`, `ProjectController`), while others write models and transactions directly (`ProposalController`, `LeadController`, `LeadTaskController`).
- **Evidence:** `AdminController.php:18` vs `ProposalController.php:582-623` and `LeadController.php:517-578`.
- **Impact:** Business rules are enforced only where the code remembers to call them.
- **Severity:** Medium
- **Recommendation:** Standardize on services for writes that span more than one table or trigger notifications. Keep single-table CRUD in controllers.

### CTL-11 - Stale docblocks reduce review confidence

**Finding ID:** CTL-11

- **Status:** Verified
- **Description:** `ProposalController` around lines 96-108 contains comments for methods that are not directly below them; `LeadController` around lines 156-160 has a comment for a guard above a different helper.
- **Evidence:** `ProposalController.php:96-109`; `LeadController.php:156-164`.
- **Impact:** Reviewers may misread which method a rule applies to.
- **Severity:** Low
- **Recommendation:** Clean up comments when the file is next touched. Do not create a standalone cleanup commit without approval.

## 8. Service Layer Assessment

### Strengths

- `AdminService` centralizes invoice, PO, payment, status derivation, document attachment, and snapshot logic.
- `ProcurementService` centralizes number generation, receipt/delivery recalculation, serial checks, and supplier invoice status.
- `ProjectWorkflowService` is the clearest domain service in the codebase: one evaluation, one sync, explicit steps.
- `SalesService` owns follow-up lifecycle and ownership checks.
- `DocumentRegistry` prevents duplicate document rows.

### Gaps

- No `LeadIntakeService`.
- No `QuotationCreationService`.
- No `ProjectCreationService` used by all paths.
- No `BastDocumentService`.
- No `SalesOwnerResolver`.
- No `WhatsAppWebhookService`.

These gaps are the main reason controllers are large. They are also the safest extraction candidates because they are already duplicated or transaction-heavy.

## 9. Priority Recommendations

1. **Quotation creation service** - removes the highest-risk duplication (three paths, tax/default differences).
2. **Lead intake service** - removes customer/lead duplication and adds a transaction boundary.
3. **Project creation consolidation** - makes the quotation gate consistent.
4. **WhatsApp webhook/inbox split** - isolates security-sensitive code.
5. **BAST document service** - removes duplicate file lifecycle logic.
6. **Sales owner resolver** - aligns notifications and invoice access.

Do not refactor `ProposalController` or `LeadController` wholesale. Extract only the workflows above, with tests that pin current behavior first.
