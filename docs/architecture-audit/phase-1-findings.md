# Phase 1 Findings

**Audit:** Phase 1 - CRM Application Architecture
**Repository:** `/var/www/3dyapp`
**Branch:** `main` at `de8103b4`
**Date:** 2026-11-23
**Mode:** Read-only inspection

## 1. Executive Summary

The application is a working Laravel monolith with a broad CRM surface: Marketing lead intake, Sales follow-up and quotation, Inside Sales task-based quotation preparation, Estimator product costing, Admin PO verification, Procurement, Technician execution, BAST, Invoice, and Payment. The core commercial chain exists and has meaningful guards, especially around quotation acceptance, PO verification, project conversion, invoice status, and payment amounts.

The main architectural risks are not missing modules. They are **duplicated workflows, conflicting sources of truth, and authorization drift**:

- Project progress is computed by a workflow service but can also be edited manually.
- BAST completion does not synchronize the project workflow.
- Quotation creation exists in three controllers with different tax/default behavior.
- Project creation exists in three paths with different gates.
- Inside Sales can create quotations but cannot read them through the normal quotation detail route.
- A salesperson can promote any lead's customer without an ownership check.
- The role seeder is destructive and hardcodes a super-admin identity.
- Cost/margin visibility in code conflicts with the project security guidance.

The frontend is cohesive in its button/toast/notification systems but still carries a large amount of inline script, native select, and hand-built table/modal markup. The integration layer is better protected than average: WhatsApp webhooks are fail-closed, Google Calendar sync is locked and retried, Reverb has a polling fallback, and file validation checks content MIME.

## 2. Health Signals

| Area | Assessment | Evidence |
|---|---|---|
| CRM coverage | Strong; all described stages exist | `routes/web.php`; module inventory |
| Quotation gate | Strong; accepted + PO verified before convert | `ProposalController.php:1079-1170` |
| PO verification | Strong; six-field completeness check | `Proposal.php:251-274` |
| Invoice/payment integrity | Strong; status derived from payments; overpay guard | `AdminService.php:456-500`; `AdminController.php:414-419` |
| Document segregation | Strong; transaction vs technical docs separated | `ProjectDocumentPolicy.php:29-144` |
| PT scoping | Mostly strong; applied in customer, lead, price request, documents | `PtAccess.php`; `CustomerAccess.php` |
| Test coverage | Broad; 182 PHP test files, 6 JS tests | `tests/` |
| Frontend component reuse | Strong for buttons, back, empty state | 569 `x-icon-button`, 59 `x-back-button` |
| Integration security | Mostly strong; fail-closed webhook, SSRF guard, MIME checks | `WhatsAppCenterController.php:585-654`; `PublicUrl.php`; `SecureFile.php` |
| Repository hygiene | Weak; 17 worktrees, 96 MB tracked ZIP, orphan route files | Phase 0 report; `AGENTS.md` §10 |

## 3. Severity Summary

Material findings in this register:

| Severity | Count | Examples |
|---|---:|---|
| High | 7 | Workflow progress gap, BAST sync gap, destructive role seeder, duplicated quotation/project creation |
| Medium | 23 | Authorization gaps, role drift, monolithic routes, UI standard violations |
| Low / Info | 16 listed here | Dead code, stale comments, scheduler split, minor policy mismatches |

Severity reflects **maintainability, business-control, and security impact**, not necessarily current production failure. No finding in this phase was confirmed as an active data breach or production outage.

The detailed documents contain 65 findings in total; this register repeats the 46 material ones and the most relevant low/info items. The rest are module-specific observations in `module-inventory.md`, `controller-service-analysis.md`, and `authorization-architecture.md`.

## 4. Consolidated Register

### High

| ID | Finding | Evidence | Impact | Recommendation |
|---|---|---|---|---|
| ARC-02 | Controllers carry too much domain logic | `LeadController.php` 1,375 LOC; `ProposalController.php` 1,213; `WhatsAppCenterController.php` 1,143; `ManageSalesController.php` 1,007 | High review cost, duplicated rules, regression risk | Extract only duplicated/transaction-heavy workflows; keep single-aggregate controllers |
| ARC-03 | Project progress has two sources of truth | `ProjectWorkflowService.php:36-160, 200-230`; `ProjectController.php:184-199`; `ProjectService.php:72-104` | Manual progress can disagree with workflow; closing/reporting inconsistent | Choose one authority; add override reason and audit if manual remains |
| ARC-04 | Three quotation creation paths | `ProposalController.php:523-642`; `InsideSalesQuotationController.php:154-260`; `LeadTaskController.php:670-781` | Tax/default/numbering drift; triple maintenance | One `QuotationCreationService` with source-specific defaults |
| ARC-05 | Three project creation paths | `ProjectService.php:23-70`; `ProposalController.php:1079-1170`; `LeadController.php:882-951` | Different gates, fields, notifications, procurement linkage | Route all through `ProjectService` with explicit source |
| WF-03 | Workflow requires survey and sizing for every project | `ProjectWorkflowService.php:44-110, 277-285` | Delivery-only/installation-only projects cannot reach 100% | Make steps conditional on project work type/requirements |
| WF-04 | BAST save does not sync project workflow | `BastController.php:100-149`; no `ProjectWorkflowService` reference; `projects:sync-workflow` not scheduled | BAST/progress/status stay stale; closing may be blocked | Call `ProjectWorkflowService::sync` after BAST update |
| AUTH-07 | Role seeder is destructive and hardcodes super-admin | `RoleAndPermissionSeeder.php:24-29, 77-155, 178-181` | Custom roles/permissions wiped; id 1 receives super-admin | Idempotent `firstOrCreate`/sync; dedicated super-admin bootstrap command |

### Medium

| ID | Finding | Evidence | Impact | Recommendation |
|---|---|---|---|---|
| AUTH-01 | `promoteCustomer` has no ownership/PT check | `routes/web.php:735-738`; `LeadController.php:970-984` | Any `manage-sales` user can promote another salesperson's lead customer | Add `authorize('update', $lead)` + `PtAccess::canWritePt` |
| AUTH-03 | Inside Sales cannot read quotations they created | `InsideSalesQuotationController.php:189-199`; `ProposalController.php:91-94, 160-172, 451-465`; `routes/web.php:352-368` | Empty quotation list or 403 after generating a draft | Include task/creator scope for `manage-inside-sales` |
| AUTH-05 | `hasRole('sales')` excludes `sales-<pt>`-only users | `ManageSalesController.php:47, 74, 91, 153, 597`; `LeadController.php:174` | PT-only sales cannot be assigned/targeted/listed | Add `User::isSales()` and use consistently |
| AUTH-06 | Cost/margin visibility conflicts with security guidance | `ProposalPolicy.php:25-35`; `ProductPolicy.php:15-24`; `3dy-security` guidance | Sales/inside sales see cost/margin despite sensitive-data guidance | Confirm audience; split cost and selling-margin permissions if needed |
| AUTH-10 | No CSP because inline code is pervasive | `SetSecurityHeaders.php:1-30`; `layouts/app.blade.php` inline blocks; 46 views with `<script>` | XSS impact cannot be reduced by CSP | Extract inline scripts/styles, then add nonce CSP |
| ARC-01 | Route registry is a single 988-line file | `routes/web.php`; 12 `sales.` groups; 83 permission checks | Hard to review one module's route surface | Split by module after runtime route diff and tests |
| ARC-09 | Queue worker depends on cron scheduler | `routes/console.php`; `bootstrap/app.php:106-113` | Queued jobs/notifications stop if cron stops | Supervised worker + scheduler health check |
| CTL-08 | BAST file storage implemented twice | `BastController.php:156-199`; `InstalasiController.php:263-309` | Metadata/storage changes must be applied twice | Extract `BastDocumentService` |
| WF-01 | Customer promoted to `deal` at warm/hot | `CustomerLifecycleService.php:22`; `LeadController.php:958-965`; `CustomerController.php:56-64` | Prospect appears in Customer menu before deal | Confirm timing; adjust `SERIOUS_STAGES` or menu filter |
| WF-02 | Admin/technician can create project without quotation | `LeadController.php:882-951` | Project can lack proposal/PO traceability | Confirm exception; require reason and mark non-quotation |
| WF-05 | Manual project progress/status can bypass workflow | `ProjectController.php:184-199`; `ProjectService.php:72-104` | Project can be marked Done while invoices unpaid | Remove manual override or require audit/reason |
| WF-07 | Lead intake is not transactional | `LeadController.php:517-578`; `WhatsAppCenterController.php:465-537` | Partial customer/lead records on failure | Wrap in transaction; shared intake service |
| MOD-01 | Five orphan route files duplicate active names | `routes/admin.php`, `admin-panel.php`, `marketing.php`, `monitoring.php`, `teknisi.php` | Editing them has no effect; review confusion | Delete/archive with a route-registry test |
| DEP-01 | Quotation is a high-coupling hub with cross-module writes | `ProposalController.php:973-1170`; `SupplierPurchaseOrderController.php:109` | Quotation changes affect sales/admin/procurement/technician | Extract handoff service and contract tests |
| DEP-02 | Soft-delete cascades create hidden cross-module effects | `CustomerObserver.php:8-27`; `ProjectObserver.php:75-99` | One delete removes records from other modules | Document cascade; use explicit delete service + tests |
| DEP-04 | Document Center depends on eight source schemas | `DocumentCenterService.php:123-472` | Schema change can break the unified list | Add per-source integration tests |
| FE-01 | 184 native selects remain vs 47 glide-select | `resources/views` counts; `components/glide-select.blade.php` | Inconsistent dropdown behavior/style | Migrate per touched file; lint new views |
| FE-02 | 46 views contain inline scripts; 43 fetch calls in views | `resources/views` counts | Logic split, harder to test, blocks CSP | Move page fetch logic into JS modules |
| FE-03 | 100 tables vs 1 `x-data-table` usage | `resources/views` counts | Pagination/empty/action markup duplicated | Extract shared table partials where touched |
| FE-04 | 33 custom modal patterns vs 7 `x-modal` | `resources/views` counts | Focus/Escape/dark-mode behavior varies | Standardize new modals on `x-modal` |
| FE-05 | 80 `text-[10px]` and 190 `text-[11px]` occurrences | `resources/views`; `sidebar.blade.php:92, 138, 552` | Below 12px minimum; readability issue | Replace when touched; visual review |
| FE-06 | Workspace tabs JS is 1,611 lines | `resources/js/workspace-tabs.js`; `config/workspace.php` | Bugs affect every page; hard to test | Add browser tests; do not rewrite without a defect |
| INT-02 | AI tools duplicate authorization logic | `GetLeadDetails.php:22-39`; `GetSalesSummary.php:25-29` | Tool output can drift from policy/UI | Make tools use policies or contract tests |

### Low / Info

| ID | Finding | Evidence | Recommendation |
|---|---|---|---|
| AUTH-02 | Lead status route allows admin/technician but controller denies | `routes/web.php:726-731`; `LeadController.php:144-154` | Align route and controller |
| AUTH-04 | Project document upload policy allows management, route does not | `ProjectDocumentPolicy.php:74-94`; `routes/web.php:441-443` | Align policy and route |
| AUTH-08 | Role deletion has no assignment guard | `AdminPanelController.php:222-230` | Block or require reassignment |
| AUTH-09 | Google OAuth has no domain allowlist | `AuthenticatedSessionController.php:106-149` | Restrict to company domains if intended |
| MOD-02 | Three price-request concepts | `PriceRequest`, `EstimatorRequest`, `RequestHarga` | Document decision table |
| DEP-03 | Procurement can exist without a project | `SupplierPurchaseOrderController.php:109-150`; `ProposalController.php:1120-1135` | Show orphan state; reconciliation report |
| CTL-09 | Two sales-owner resolvers | `AdminService.php:412-436`; `SalesFinanceController.php:12-36` | Extract one resolver |
| WF-08 | Sizing/installation statuses are plain strings | `SizingProjectController.php:71, 124`; `InstalasiController.php:79, 171` | Add constants/enums |
| FE-07 | Stale full-page-reload comment | `layouts/app.blade.php:255-267` vs `:1000`; 99 `wire:navigate` | Correct comment |
| FE-09 | `app.js` mixes unrelated concerns | `resources/js/app.js:1-440` | Split appearance, push, legacy AJAX |
| FE-10 | Alpine state mostly inline | 108 `x-data`, 2 `Alpine.data` | Extract named components when touched |
| INT-01 | Meta secret comment contradicts fail-closed code | `config/whatsapp.php:55-57`; `WhatsAppCenterController.php:585-600` | Fix comment |
| INT-04 | Google Calendar can assign events to arbitrary user | `GoogleCalendarService.php:247-250` | Require mapping or leave unassigned |
| INT-06 | Quotation PDF generation is synchronous | `QuotationPdfService.php:14-24`; `ProposalController.php:634-638` | Measure; consider queueing |
| INT-07 | Scheduler split across two files | `bootstrap/app.php:106-113`; `routes/console.php` | Consolidate schedule |
| INT-08 | Reverb broadcast synchronous by design | `NotificationCreated.php:8-22` | Keep; revisit at scale |

## 5. Top 10 Priorities

1. **Fix project closing correctness**: condition workflow steps on project requirements and sync workflow after BAST/invoice/payment (WF-03, WF-04, ARC-03).
2. **Make the role seeder safe**: idempotent upsert, no hardcoded super-admin identity, production guard (AUTH-07).
3. **Unify quotation creation**: one service, one item mapper, one tax/default rule (ARC-04, CTL-06).
4. **Close authorization gaps**: `promoteCustomer` ownership, inside-sales quotation visibility (AUTH-01, AUTH-03).
5. **Unify project creation**: one `ProjectService` path with source-specific gates (ARC-05, CTL-07).
6. **Fix sales role semantics**: `User::isSales()` including `sales-*` (AUTH-05, ARC-06).
7. **Decide cost/margin visibility**: align code and security documentation (AUTH-06).
8. **Confirm customer promotion timing**: warm/hot vs quotation/PO (WF-01).
9. **Extract the duplicated BAST/lead-intake workflows** (CTL-08, WF-07, MOD-03).
10. **Add CI hardening**: PHP test workflow, Pint check, static analyzer decision. Phase 0 found only a JS test workflow (`.github/workflows/js-tests.yml`).

## 6. What Is Healthy

- The commercial handoff gate is real: conversion requires accepted quotation + uploaded PO + verified PO + payment scheme (`ProposalController.php:1079-1170`).
- Invoice status is derived from payments, not manually set (`AdminService.php:456-500`).
- Overpayment and cancellation guards exist (`AdminController.php:414-419`; `AdminService.php:225-238`).
- Supplier PO is blocked until the customer PO is verified (`SupplierPurchaseOrderController.php:109`).
- Goods receipt/delivery transitions are guarded; delivered notes cannot move backward (`GoodsDeliveryController.php:171-187`).
- Payment proof has a dedicated permission (`view-payment-proofs`) and is excluded from generic admin reads.
- Webhook verification is fail-closed for both WhatsApp providers.
- Secure file validation checks extension and MIME content.
- Notification payloads are centralized and the bell works without WebSocket.
- The test suite is broad and includes role-matrix, UI-standard, finance-integrity, document-access, and workflow tests.

## 7. Open Questions for Human Review

1. Should a customer become `deal` at `warm`, or only after quotation accepted/PO verified?
2. Should admin/technician retain the direct lead-to-project fallback? If yes, what evidence should be required?
3. Is manual project progress/status still needed, or should workflow be the only authority?
4. Who is allowed to see cost/margin: admin only, or sales/inside sales as the current policy allows?
5. Is procurement-before-project intentional? Should orphan Supplier POs be visible in a separate queue?
6. Should Inside Sales see the quotation detail of a quotation they created from a task?
7. Is the role seeder ever run in production? If yes, it needs a guard before any further use.
8. Should Google OAuth be restricted to company email domains?

## 8. Recommended Phase 2 Audit

1. **Runtime route and middleware audit**: `php artisan route:list`, duplicate-name check, route-file load test.
2. **Authorization matrix execution**: positive and negative tests for every role and PT scope, especially `promoteCustomer`, inside-sales quotation, project documents, payment proofs, supplier data.
3. **Workflow state tests**: delivery-only, installation-only, skipped survey, BAST completion, manual override, commercial closing.
4. **Database review**: foreign keys, indexes, unique constraints, enum/status columns on 210 migrations.
5. **Performance review**: dashboard/pipeline/document-center queries, N+1, PDF generation time.
6. **Frontend audit**: mobile/dark mode, keyboard/focus, contrast, workspace tabs, native select/modal migration.
7. **Integration audit**: signed WhatsApp payloads in staging, Google Calendar owner mapping, queue/cron health, AI tool policy parity.
8. **Repository hygiene**: worktree count, merged branches, tracked ZIP/videos, orphan route files.

## 9. Limitations

- No source code, database schema, or data was modified.
- No tests were executed; findings are from static inspection.
- No runtime route table was generated; route counts are static.
- No external API was called; integration behavior is inferred from code and config.
- No browser-based UI, mobile, or accessibility audit was performed.
- No database index or query-plan review was performed.
- Findings marked "hypothesis" or "intent unclear" require a business decision before code changes.

## 10. Document Index

| Document | Purpose |
|---|---|
| `module-inventory.md` | Module responsibilities, routes, controllers, models, services, views |
| `application-architecture.md` | Layers, request lifecycle, patterns, architecture findings |
| `module-dependency-map.md` | Mermaid module graph and dependency risks |
| `business-workflow-map.md` | Verified end-to-end workflow, gates, transitions, findings |
| `controller-service-analysis.md` | Deep dive on five large controllers and service gaps |
| `authorization-architecture.md` | Roles, permissions, policies, PT/customer/document scope |
| `frontend-architecture.md` | Layouts, components, Livewire, Alpine, React, JS data flow |
| `integration-architecture.md` | WhatsApp, Google Calendar, Reverb, Web Push, AI, documents, queue |
| `phase-1-findings.md` | Consolidated register, priorities, open questions |
| `business-discovery-alignment.md` | Kesesuaian 23 aturan bisnis, 20 keputusan, dan 14 gap dengan implementasi |
| `../reference/BUSINESS-DISCOVERY-3DY-V1.md` | Dokumen sumber Business Discovery v1.0 |
