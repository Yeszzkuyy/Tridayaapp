# Phase 2 - Database Inventory

**Audit:** Phase 2 - Database Architecture
**Repository:** `/var/www/3dyapp`
**Branch:** `main` at `de8103b4`
**Tanggal:** 2026-11-24
**Mode:** Read-only. Tidak ada migration, seeder, query mutasi, atau perubahan schema.

## 1. Ringkasan Angka

| Item | Hasil |
|---|---:|
| Tabel di schema `public` | 87 |
| Kolom | 1.081 |
| Migration file | 210 |
| Model diperiksa | 68 |
| Foreign key constraint | 182 |
| Primary key | 87 (semua tabel memiliki PK) |
| Unique constraint (non-PK) | 40 |
| Index total | 244 |
| Check constraint | 5 |
| Tabel dengan soft delete | 34 |
| Kolom JSON | 17 |
| FK `ON DELETE CASCADE` | 66 |
| FK `ON DELETE SET NULL` | 110 |
| FK `ON DELETE RESTRICT` | 6 |
| Tabel tanpa PK | 0 |

**Sumber struktur:** katalog PostgreSQL `information_schema` dan `pg_indexes`, bukan pembacaan baris data. Tidak ada tabel bisnis yang dibaca isinya.

**Catatan:** jumlah migration 210 sama dengan jumlah baris tabel `migrations`; schema sudah mencapai migrasi terakhir. Ini tidak membuktikan kualitas data, hanya bahwa migration tercatat.

## 2. Kelompok Domain

| Domain | Tabel | Jumlah |
|---|---|---:|
| Framework, auth, queue, notifikasi | `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`, `notifications`, `push_subscriptions`, `app_settings`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `agent_conversations`, `agent_conversation_messages` | 19 |
| Customer, lead, aktivitas sales | `customers`, `customer_contacts`, `leads`, `lead_activities`, `lead_documents`, `follow_ups`, `pocs`, `sales_schedules`, `partners` | 9 |
| Quotation, task, target | `proposals`, `proposal_items`, `proposal_versions`, `proposal_attachments`, `lead_tasks`, `lead_task_items`, `lead_task_comments`, `lead_task_attachments`, `lead_task_request_files`, `sales_targets` | 10 |
| Estimator, produk, supplier pricing | `estimator_requests`, `estimator_request_items`, `price_requests`, `price_request_comments`, `price_request_replies`, `request_hargas`, `products`, `product_price_logs`, `product_suppliers`, `product_sources`, `suppliers` | 11 |
| Procurement dan logistik | `supplier_purchase_orders`, `supplier_purchase_order_items`, `goods_receipts`, `goods_receipt_items`, `goods_deliveries`, `goods_delivery_items`, `supplier_invoices`, `supplier_payments`, `purchase_orders` | 9 |
| Project, teknisi, dokumen | `projects`, `project_statuses`, `project_tasks`, `project_supports`, `project_activities`, `project_documents`, `document_categories`, `document_activities`, `surveys`, `sizing_projects`, `instalasis`, `technical_requests`, `technical_request_supports`, `technician_schedules`, `technician_schedule_user`, `work_types`, `account_managers` | 17 |
| Invoice dan payment customer | `invoices`, `invoice_items`, `payments` | 3 |
| WhatsApp | `whatsapp_accounts`, `whatsapp_conversations`, `whatsapp_messages`, `whatsapp_conversation_preferences` | 4 |
| AI dan knowledge | `knowledge_base_documents`, `knowledge_documents`, `knowledge_chunks` | 3 |
| Integrasi Google | `google_credentials`, `google_app_credentials` | 2 |
| **Total** | | **87** |

## 3. Inventory Tabel

`Cols` = jumlah kolom. `FKs` = foreign key keluar. `Indexes` = semua index, termasuk PK/unique. `Unique` = unique constraint non-primary.

| Table | Cols | FKs | Indexes | Unique |
|---|---:|---:|---:|---:|
| `account_managers` | 6 | 0 | 1 | 0 |
| `agent_conversation_messages` | 13 | 0 | 4 | 0 |
| `agent_conversations` | 5 | 0 | 2 | 0 |
| `app_settings` | 5 | 0 | 2 | 1 |
| `cache` | 3 | 0 | 2 | 0 |
| `cache_locks` | 3 | 0 | 2 | 0 |
| `customer_contacts` | 12 | 2 | 2 | 0 |
| `customers` | 25 | 3 | 3 | 0 |
| `document_activities` | 11 | 1 | 4 | 0 |
| `document_categories` | 5 | 0 | 2 | 1 |
| `estimator_request_items` | 21 | 4 | 2 | 0 |
| `estimator_requests` | 18 | 6 | 4 | 0 |
| `failed_jobs` | 7 | 0 | 3 | 1 |
| `follow_ups` | 24 | 3 | 6 | 0 |
| `goods_deliveries` | 12 | 3 | 3 | 1 |
| `goods_delivery_items` | 11 | 3 | 1 | 0 |
| `goods_receipt_items` | 9 | 3 | 2 | 0 |
| `goods_receipts` | 14 | 4 | 4 | 1 |
| `google_app_credentials` | 10 | 0 | 1 | 0 |
| `google_credentials` | 8 | 1 | 2 | 1 |
| `instalasis` | 31 | 7 | 3 | 1 |
| `invoice_items` | 10 | 1 | 2 | 0 |
| `invoices` | 24 | 4 | 6 | 2 |
| `job_batches` | 10 | 0 | 1 | 0 |
| `jobs` | 7 | 0 | 2 | 0 |
| `knowledge_base_documents` | 13 | 2 | 4 | 0 |
| `knowledge_chunks` | 7 | 1 | 3 | 0 |
| `knowledge_documents` | 11 | 2 | 5 | 0 |
| `lead_activities` | 7 | 2 | 4 | 0 |
| `lead_documents` | 8 | 1 | 2 | 0 |
| `lead_task_attachments` | 12 | 4 | 2 | 0 |
| `lead_task_comments` | 6 | 2 | 1 | 0 |
| `lead_task_items` | 20 | 3 | 2 | 0 |
| `lead_task_request_files` | 9 | 2 | 2 | 0 |
| `lead_tasks` | 12 | 3 | 1 | 0 |
| `leads` | 30 | 5 | 7 | 0 |
| `migrations` | 3 | 0 | 1 | 0 |
| `model_has_permissions` | 3 | 1 | 2 | 0 |
| `model_has_roles` | 3 | 1 | 2 | 0 |
| `notifications` | 8 | 0 | 2 | 0 |
| `partners` | 11 | 0 | 1 | 0 |
| `password_reset_tokens` | 3 | 0 | 1 | 0 |
| `payments` | 12 | 2 | 3 | 1 |
| `permissions` | 5 | 0 | 2 | 1 |
| `pocs` | 12 | 3 | 1 | 0 |
| `price_request_comments` | 8 | 2 | 1 | 0 |
| `price_request_replies` | 11 | 3 | 2 | 1 |
| `price_requests` | 17 | 7 | 4 | 0 |
| `product_price_logs` | 19 | 5 | 4 | 0 |
| `product_sources` | 11 | 1 | 5 | 1 |
| `product_suppliers` | 8 | 2 | 3 | 1 |
| `products` | 17 | 0 | 7 | 0 |
| `project_activities` | 8 | 2 | 1 | 0 |
| `project_documents` | 21 | 5 | 8 | 1 |
| `project_statuses` | 9 | 0 | 1 | 0 |
| `project_supports` | 6 | 2 | 1 | 0 |
| `project_tasks` | 12 | 2 | 1 | 0 |
| `projects` | 35 | 9 | 7 | 3 |
| `proposal_attachments` | 9 | 2 | 2 | 0 |
| `proposal_items` | 20 | 3 | 2 | 0 |
| `proposal_versions` | 11 | 2 | 3 | 1 |
| `proposals` | 51 | 6 | 4 | 1 |
| `purchase_orders` | 13 | 3 | 3 | 1 |
| `push_subscriptions` | 9 | 0 | 3 | 1 |
| `request_hargas` | 9 | 1 | 1 | 0 |
| `role_has_permissions` | 2 | 2 | 1 | 0 |
| `roles` | 5 | 0 | 2 | 1 |
| `sales_schedules` | 15 | 3 | 3 | 0 |
| `sales_targets` | 11 | 2 | 2 | 1 |
| `sessions` | 6 | 0 | 3 | 0 |
| `sizing_projects` | 15 | 2 | 2 | 0 |
| `supplier_invoices` | 20 | 5 | 6 | 3 |
| `supplier_payments` | 13 | 3 | 3 | 1 |
| `supplier_purchase_order_items` | 15 | 3 | 3 | 0 |
| `supplier_purchase_orders` | 18 | 4 | 6 | 2 |
| `suppliers` | 12 | 0 | 3 | 1 |
| `surveys` | 21 | 5 | 4 | 1 |
| `technical_request_supports` | 5 | 0 | 3 | 1 |
| `technical_requests` | 25 | 4 | 4 | 0 |
| `technician_schedule_user` | 2 | 2 | 1 | 0 |
| `technician_schedules` | 21 | 4 | 5 | 0 |
| `users` | 19 | 0 | 4 | 2 |
| `whatsapp_accounts` | 15 | 1 | 2 | 1 |
| `whatsapp_conversation_preferences` | 9 | 2 | 2 | 1 |
| `whatsapp_conversations` | 11 | 2 | 2 | 1 |
| `whatsapp_messages` | 14 | 2 | 5 | 2 |
| `work_types` | 4 | 0 | 1 | 0 |

## 4. Constraint Penting

### 4.1 Unique constraint bisnis

| Tabel | Kolom | Catatan |
|---|---|---|
| `proposals` | `proposal_number` | Global, nullable tidak |
| `invoices` | `invoice_number` | Global |
| `purchase_orders` | `po_number` | Tabel legacy customer PO |
| `supplier_purchase_orders` | `po_number` | Global |
| `supplier_invoices` | `internal_ref_number` | Global |
| `supplier_invoices` | `supplier_id + supplier_invoice_number` | Per supplier |
| `projects` | `project_code` | Global, nullable |
| `projects` | `bast_number` | Global, nullable |
| `projects` | `proposal_id` | Satu project per proposal |
| `goods_receipts` | `receipt_number` | Global |
| `goods_deliveries` | `delivery_number` | Global |
| `products` | `sku` | **Tidak unique**, hanya index |
| `suppliers` | `code` | Global, nullable |
| `sales_targets` | `user_id + period_year + period_month` | Satu target per user per bulan |
| `product_suppliers` | `product_id + supplier_id` | Satu relasi produk-supplier |
| `proposal_versions` | `proposal_id + version` | Nomor versi unik per quotation |
| `price_request_replies` | `price_request_id + version` | Versi reply unik |
| `customer_contacts` | partial unique `customer_id WHERE is_primary` | Satu PIC utama aktif |
| `technician_schedules` | partial unique `google_event_id WHERE deleted_at IS NULL` | Event Google aktif unik |

### 4.2 Check constraint yang tersedia

Hanya ada 5 check constraint:

1. `customers_status_check`: `lead`, `deal`, `instalasi`, `selesai`.
2. `project_tasks_status_check`: `Open`, `Progress`, `Done`.
3. `purchase_orders_status_check`: `draft`, `diproses`, `selesai`, `dibatalkan`.
4. `partners_type_check`: `supplier`, `vendor`, `kontraktor`, `partner`, `distributor`.
5. `whatsapp_messages_direction_check`: `inbound`, `outbound`.

Tidak ada check constraint untuk `leads.status`, `proposals.status`, `invoices.status`, `goods_receipts.status`, `goods_deliveries.status`, `supplier_invoices.status`, `supplier_purchase_orders.status`, `price_requests.status`, `technical_requests.status`, `lead_tasks.status`, `follow_ups.status`, dan status lain. Lihat DB-005.

### 4.3 Foreign key delete rules

| Delete rule | Jumlah | Contoh |
|---|---:|---|
| `CASCADE` | 66 | `projects.customer_id`, `invoices.customer_id`, `payments.invoice_id` |
| `SET NULL` | 110 | `leads.assigned_to`, `projects.sales_user_id`, `product_price_logs.supplier_id` |
| `RESTRICT` | 6 | `goods_receipts.supplier_purchase_order_id`, `supplier_invoices.supplier_id` |
| `NO ACTION` update | 182 | Semua FK tidak memakai `ON UPDATE` khusus |

Lihat DB-001 dan DB-002 untuk dua pola cascade yang berisiko.

### 4.4 Partial index

Hanya dua partial index yang ditemukan:

- `customer_contacts_one_primary` pada `customer_contacts(customer_id) WHERE is_primary = true AND deleted_at IS NULL`.
- `technician_schedules_google_event_id_active_unique` pada `technician_schedules(google_event_id) WHERE google_event_id IS NOT NULL AND deleted_at IS NULL`.

Partial index pertama adalah kontrol integritas yang baik untuk satu PIC utama. Partial index kedua mencegah event Google aktif ganda.

## 5. Inventory Kolom per Tabel

### 5.1 Framework, auth, queue, notifikasi

- `account_managers` (6): id, name, phone, email, created_at, updated_at
- `agent_conversation_messages` (13): id, conversation_id, user_id, agent, role, content, attachments, tool_calls, tool_results, usage, meta, created_at, updated_at
- `agent_conversations` (5): id, user_id, title, created_at, updated_at
- `app_settings` (5): id, key, value, created_at, updated_at
- `cache` (3): key, value, expiration
- `cache_locks` (3): key, owner, expiration
- `failed_jobs` (7): id, uuid, connection, queue, payload, exception, failed_at
- `job_batches` (10): id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at
- `jobs` (7): id, queue, payload, attempts, reserved_at, available_at, created_at
- `migrations` (3): id, migration, batch
- `model_has_permissions` (3): permission_id, model_type, model_id
- `model_has_roles` (3): role_id, model_type, model_id
- `notifications` (8): id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at
- `password_reset_tokens` (3): email, token, created_at
- `permissions` (5): id, name, guard_name, created_at, updated_at
- `push_subscriptions` (9): id, subscribable_type, subscribable_id, endpoint, public_key, auth_token, content_encoding, created_at, updated_at
- `role_has_permissions` (2): permission_id, role_id
- `roles` (5): id, name, guard_name, created_at, updated_at
- `sessions` (6): id, user_id, ip_address, user_agent, payload, last_activity
- `users` (19): id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, deleted_at, last_activity, avatar, preferences, password_changed_at, cover, phone, google_id, google_avatar, initials

### 5.2 Customer, lead, aktivitas sales

- `customers` (25): id, name, address, phone, email, notes, created_at, updated_at, deleted_at, company, status, deleted_by, contact_person, whatsapp, whatsapp_account_id, pt_group, npwp, npwp_document_path, npwp_document_name, billing_address, shipping_address, finance_pic_name, finance_pic_phone, finance_pic_email, created_by
- `customer_contacts` (12): id, customer_id, name, position, phone, email, is_primary, created_at, updated_at, deleted_at, whatsapp, created_by
- `leads` (30): id, customer_id, status, source, notes, incoming_date, assigned_to, created_at, updated_at, deleted_at, segment, kebutuhan, pt_group, solusi, progress_notes, partner_id, assigned_by, assigned_at, whatsapp_account_id, lost_reason, lost_note, closed_at, closing_note, acknowledged_at, location, project_name, need_type, procurement_status, assignment_overdue_notified_at, color_tag
- `lead_activities` (7): id, lead_id, user_id, action, changes, created_at, updated_at
- `lead_documents` (8): id, lead_id, file_name, file_path, mime_type, created_at, updated_at, category
- `follow_ups` (24): id, customer_id, description, follow_up_date, created_by, created_at, updated_at, deleted_at, lead_id, reminder_sent_at, type, next_follow_up_date, completed_at, activity_time, location, meeting_photos, meeting_notes, meeting_outcome, meeting_next_action_date, meeting_mode, daily_report_submitted_at, status, outcome, source
- `pocs` (12): id, customer_id, lead_id, type, scheduled_date, location, status, result_notes, created_by, created_at, updated_at, deleted_at
- `sales_schedules` (15): id, lead_id, assigned_to, created_by, title, type, start_at, end_at, location, description, status, reminder_at, created_at, updated_at, deleted_at
- `partners` (11): id, name, type, contact_person, phone, email, address, notes, created_at, updated_at, deleted_at

### 5.3 Quotation, task, target

- `proposals` (51): id, lead_id, proposal_number, created_by, proposal_date, valid_until, customer, subtotal, discount, tax, grand_total, notes, status, sent_at, sent_by, created_at, updated_at, deleted_at, po_document_path, total_cost, gross_profit, gross_margin, internal_notes, po_number, po_date, delivery_deadline, delivery_address, payment_scheme, dp_percent, term_days, payment_terms, delivery_terms, po_verified_at, po_verified_by, po_returned_at, po_returned_by, po_return_reason, sent_channel, lead_task_id, customer_address, contact_name, contact_email, contact_phone, project_description, payment_terms_text, letterhead_pt, pdf_path, pdf_generated_at, warranty_terms, tax_rate, default_margin_percent
- `proposal_items` (20): id, proposal_id, description, quantity, unit, unit_price, discount, subtotal, created_at, updated_at, product_id, unit_cost, scope_of_supply, lead_time, section, product_price_log_id, stock_status, stock_eta, warranty_info, target_margin_percent
- `proposal_versions` (11): id, proposal_id, version, label, snapshot, total_before, total_after, change_summary, changed_by, created_at, updated_at
- `proposal_attachments` (9): id, proposal_id, file_path, original_name, mime, size, uploaded_by, created_at, updated_at
- `lead_tasks` (12): id, lead_id, title, description, assigned_to, due_date, priority, status, created_by, created_at, updated_at, deleted_at
- `lead_task_items` (20): id, lead_task_id, product_id, description, quantity, unit, unit_price, discount, subtotal, created_at, updated_at, unit_cost, scope_of_supply, lead_time, section, product_price_log_id, stock_status, stock_eta, warranty_info, target_margin_percent
- `lead_task_comments` (6): id, lead_task_id, user_id, body, created_at, updated_at
- `lead_task_attachments` (12): id, lead_task_id, file_path, original_name, mime, size, uploaded_by, created_at, updated_at, proposal_id, accepted_by, accepted_at
- `lead_task_request_files` (9): id, lead_task_id, file_path, original_name, mime, size, uploaded_by, created_at, updated_at
- `sales_targets` (11): id, user_id, period_month, period_year, target_revenue, target_deals, notes, created_by, created_at, updated_at, target_margin

### 5.4 Estimator, produk, supplier pricing

- `estimator_requests` (18): id, customer_id, lead_id, project_id, proposal_id, requested_by, handled_by, status, notes, attachment_path, completed_at, created_at, updated_at, deleted_at, revision_reason, revision_requested_at, attachment_name, attachment_size
- `estimator_request_items` (21): id, estimator_request_id, product_id, supplier_id, description, quantity, unit, price, stock_status, stock_eta, warranty_info, notes, status, answered_by, answered_at, created_at, updated_at, valid_until, price_includes_tax, tax_rate, price_before_tax
- `price_requests` (17): id, customer_id, requested_by, quotation_path, notes, status, assigned_to, reply_path, reply_notes, completed_at, created_at, updated_at, deleted_at, proposal_id, lead_id, project_id, handled_by
- `price_request_comments` (8): id, price_request_id, user_id, body, created_at, updated_at, type, meta
- `price_request_replies` (11): id, price_request_id, uploaded_by, version, file_path, notes, is_legacy, accepted_by, accepted_at, created_at, updated_at
- `request_hargas` (9): id, project_id, items, notes, attachments, status, created_at, updated_at, deleted_at
- `products` (17): id, brand, name, model, sku, category, description, status, created_at, updated_at, unit, default_price, is_active, cost_price, part_number, has_serial_number, cost_includes_tax
- `product_price_logs` (19): id, product_id, supplier_id, price, currency, price_request_id, updated_by, notes, created_at, updated_at, price_type, stock_status, stock_eta, warranty_info, estimator_request_item_id, valid_until, price_entered, price_includes_tax, tax_rate
- `product_suppliers` (8): id, product_id, supplier_id, is_primary, supplier_part_number, last_cost_price, created_at, updated_at
- `product_sources` (11): id, product_id, url, source_type, status, error, last_fetched_at, content_hash, batch_id, created_at, updated_at
- `suppliers` (12): id, code, name, contact_person, phone, email, address, notes, is_active, created_at, updated_at, deleted_at

### 5.5 Procurement dan logistik

- `supplier_purchase_orders` (18): id, po_number, supplier_id, proposal_id, project_id, issue_date, expected_delivery_date, status, notes, subtotal, discount, tax, grand_total, idempotency_key, created_by, created_at, updated_at, deleted_at
- `supplier_purchase_order_items` (15): id, supplier_purchase_order_id, proposal_item_id, product_id, item_name, part_number, description, unit, quantity_ordered, quantity_received, unit_price, subtotal, created_at, updated_at, quantity_delivered
- `goods_receipts` (14): id, receipt_number, supplier_purchase_order_id, supplier_id, project_id, received_date, supplier_delivery_note_no, received_by, status, notes, attachment_path, created_at, updated_at, deleted_at
- `goods_receipt_items` (9): id, goods_receipt_id, supplier_purchase_order_item_id, product_id, quantity_received, serial_numbers, condition_notes, created_at, updated_at
- `goods_deliveries` (12): id, delivery_number, project_id, customer_id, delivery_date, recipient_name, status, notes, created_by, created_at, updated_at, deleted_at
- `goods_delivery_items` (11): id, goods_delivery_id, product_id, supplier_purchase_order_item_id, item_name, part_number, unit, quantity, serial_numbers, created_at, updated_at
- `supplier_invoices` (20): id, internal_ref_number, supplier_invoice_number, supplier_id, supplier_purchase_order_id, goods_receipt_id, project_id, invoice_type, amount, paid_amount, status, issue_date, due_date, invoice_file_path, notes, created_by, created_at, updated_at, deleted_at, idempotency_key
- `supplier_payments` (13): id, supplier_invoice_id, supplier_purchase_order_id, amount, payment_date, payment_method, proof_file, notes, created_by, created_at, updated_at, deleted_at, idempotency_key
- `purchase_orders` (13): id, po_number, customer_id, project_id, items, amount, status, issue_date, notes, created_by, created_at, updated_at, deleted_at

### 5.6 Project, teknisi, dokumen

- `projects` (35): id, customer_id, account_manager_id, work_type_id, project_name, project_code, quotation_number, start_date, end_date, description, created_at, updated_at, deleted_at, project_status_id, progress, pic_engineer, support_technicians, sales_user_id, teknisi_user_id, deleted_by, proposal_id, requires_installation, bast_number, bast_date, bast_document_id, delivery_deadline, commercial_closed_at, bast_signed_3dy_name, bast_signed_3dy_at, bast_signed_customer_name, bast_signed_customer_at, bast_ready_at, requires_delivery, workflow_state, workflow_synced_at
- `project_statuses` (9): id, name, color, is_default, sort_order, is_active, created_at, updated_at, deleted_at
- `project_tasks` (12): id, project_id, assigned_to, title, description, status, start_date, due_date, completed_at, created_at, updated_at, deleted_at
- `project_supports` (6): id, project_id, user_id, created_at, updated_at, deleted_at
- `project_activities` (8): id, project_id, user_id, activity_date, title, description, created_at, updated_at
- `project_documents` (21): id, project_id, file_name, file_path, notes, uploaded_by, created_at, updated_at, document_category_id, deleted_at, category_id, customer_id, doc_type, file_size, mime_type, documentable_type, documentable_id, document_group_id, version, title, deleted_by
- `document_categories` (5): id, name, created_at, updated_at, deleted_at
- `document_activities` (11): id, document_id, user_id, action, file_name, doc_type, customer_id, project_id, meta, created_at, updated_at
- `surveys` (21): id, project_id, sales_request, survey_date, location, pic, survey_data, documentation, survey_report, status, notes, created_at, updated_at, deleted_at, report_file, report_file_name, title, customer_id, sales_user_id, technical_request_id, technician_user_id
- `sizing_projects` (15): id, project_id, sales_pic, customer_needs, recommendation, specifications, quantity, topology, technical_notes, attachments, status, created_at, updated_at, deleted_at, technician_user_id
- `instalasis` (31): id, project_id, location, technician_pic, schedule_date, job_status, checklist, documentation, installation_report, notes, attachments, status, created_at, updated_at, deleted_at, report_file, report_file_name, completed_at, completed_by, customer_id, sales_user_id, technical_request_id, technician_user_id, bast_number, bast_date, bast_document_id, bast_signed_3dy_name, bast_signed_3dy_at, bast_signed_customer_name, bast_signed_customer_at, bast_ready_at
- `technical_requests` (25): id, lead_id, requested_by, request_type, title, requirement, problem_description, scope, priority, target_date, attachment_path, notes, status, assigned_technician_id, technical_result, completed_at, created_at, updated_at, deleted_at, location, purpose, target_time, pic_contact_name, pic_contact_phone, project_id
- `technical_request_supports` (5): id, technical_request_id, user_id, created_at, updated_at
- `technician_schedules` (21): id, user_id, project_id, technician_user_id, title, description, location, start_at, end_at, status, reminder_minutes, google_event_id, google_calendar_id, google_sync_status, google_sync_error, created_at, updated_at, deleted_at, reminder_sent_at, technical_request_id, customer_id
- `technician_schedule_user` (2): technician_schedule_id, user_id
- `work_types` (4): id, name, created_at, updated_at
- `account_managers` (6): id, name, phone, email, created_at, updated_at

### 5.7 Invoice dan payment customer

- `invoices` (24): id, invoice_number, customer_id, project_id, amount, status, issue_date, due_date, notes, created_by, created_at, updated_at, deleted_at, proposal_id, invoice_type, paid_amount, customer_po_number, idempotency_key, billing_address, payment_terms, subtotal, discount, tax, paid_at
- `invoice_items` (10): id, invoice_id, description, quantity, unit, unit_price, discount, subtotal, created_at, updated_at
- `payments` (12): id, invoice_id, amount, payment_date, payment_method, proof_file, notes, created_by, created_at, updated_at, deleted_at, idempotency_key

### 5.8 WhatsApp, AI, integrasi

- `whatsapp_accounts` (15): id, name, phone_number, account_code, assigned_to, is_active, created_at, updated_at, deleted_at, gateway_instance, gateway_token, gateway_status, gateway_type, bot_enabled, bot_instructions
- `whatsapp_conversations` (11): id, whatsapp_account_id, sender_number, mode, handled_by, bot_turns, needs_summary, taken_over_at, reminded_at, created_at, updated_at
- `whatsapp_messages` (14): id, whatsapp_account_id, sender_number, sender_name, message_body, direction, wa_message_id, lead_id, created_at, updated_at, status, gateway_message_id, read_at, is_bot
- `whatsapp_conversation_preferences` (9): id, user_id, whatsapp_account_id, sender_number, is_pinned, is_muted, is_archived, created_at, updated_at
- `knowledge_base_documents` (13): id, original_name, category, project_id, user_id, store_id, provider_file_id, provider_document_id, mime, size, status, created_at, updated_at
- `knowledge_documents` (11): id, source_id, product_id, title, content, source_url, version, content_hash, status, created_at, updated_at
- `knowledge_chunks` (7): id, document_id, chunk_index, content, metadata, created_at, updated_at
- `google_credentials` (8): id, user_id, access_token, refresh_token, expires_at, calendar_id, created_at, updated_at
- `google_app_credentials` (10): id, client_id, client_secret, calendar_id, created_at, updated_at, last_sync_at, last_sync_summary, last_sync_error, last_sync_error_kind

## 6. Catatan Struktur

- Semua 87 tabel memiliki primary key.
- 34 tabel memakai soft delete; hanya `customer_contacts` dan `technician_schedules` yang memiliki partial unique index khusus.
- 17 kolom JSON menyimpan snapshot/array, bukan relasi: `proposal_versions.snapshot`, `projects.workflow_state`, `lead_activities.changes`, `request_hargas.items`, `surveys.documentation`, `sizing_projects.attachments`, `instalasis.checklist/documentation/attachments`, `follow_ups.meeting_photos`, `goods_receipt_items.serial_numbers`, `goods_delivery_items.serial_numbers`, `document_activities.meta`, `price_request_comments.meta`, `knowledge_chunks.metadata`, `users.preferences`.
- 19 tabel adalah framework/auth/queue/agent; tabel ini tidak mengikuti domain CRM tetapi tetap memengaruhi audit, retensi, dan performa.
- Tabel `meetings` dan `meeting_drafts` sudah di-drop melalui migration; tidak ada di schema aktif.
- `purchase_orders` masih ada, tetapi penulisan baru diblokir di controller (`AdminController@posStore` mengembalikan 403).

## 7. Batasan

- Inventory dibaca dari katalog PostgreSQL, bukan dari `php artisan db:table` atau dump schema.
- Row count tidak dipakai sebagai bukti kualitas data karena statistik PostgreSQL bisa basi dan database yang terhubung bisa berbeda dari produksi.
- Tidak ada pemeriksaan data aktual, duplikat data, atau orphan data. Temuan integritas bersifat struktural.
- Tipe `numeric` dilaporkan tanpa precision/scale di katalog yang dipakai; detail presisi tidak dianalisis pada phase ini.
