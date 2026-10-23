# System test report

## Executive result and evidence date
The final isolated local verification on 20 October 2026 passed **218 tests with 3566 assertions in 175.11 seconds**, plus native multi-process concurrency and actual disposable database dump/restore checks. This report records the executed software checks. Business acceptance remains pending and no production readiness, exhaustive penetration testing or capacity certification is claimed.

## Reading the consolidated evidence
The following chapters reproduce the maintained test reports, with whitespace normalized. Some chapters retain original Step 12 findings followed by a Pending-work follow-up. The follow-up and the first completion chapter supersede earlier statements that stock/supplier workflows were absent or native database checks were still pending. This preserves traceability rather than erasing the audit history. UAT manual statuses remain pending even when automated coverage exists.

The authoritative execution log is storage/logs/pending-complete-mysql.log. Schema snapshots, preservation, syntax, style and PDF evidence are referenced by the completion chapter. The full requirement-by-requirement matrix is docs/testing/brd-compliance-matrix.md and the method inventory is docs/testing/automated-test-inventory.md in the delivered repository; these are detailed companion evidence rather than assertions that every BRD requirement is accepted.

## Environment and safety
Laravel 13 / PHP 8.5, the MySQL driver against local XAMPP MariaDB 10.4.32, InnoDB/utf8mb4. The helper created a randomized local disposable test database, exercised migrations/recovery there and removed only that fixture database. Operational tables were not reset or migrated during this verification. Matching before/after row counts do not prove byte-identical operational records.

## Acceptance still required
Approved operational backup destination; actual Google Drive configuration/upload; physical printer/scanner tests; realistic-volume capacity acceptance; owner UAT/signatures; purchase-wide discount/freight costing decision. No live operational restore, production build, scheduler activation, installer or deployment was performed.


# Pending-work completion report

The missing operational modules have now been implemented and tested. Full business acceptance remains conditional on the external items below; this is not a 100% BRD-compliance declaration.

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

Implemented: supplier registration/edit/search and opening balances; inventory master, references, preferred suppliers and optional known opening costs; purchase drafts/finalization, partial GRNs and source-linked stock receiving; authorized repair requests/issues and complete unused-issue returns; corrections/damage/counts; supplier returns with explicitly agreed credit; supplier purchase/opening payments and reversals; purchase/GRN print/PDF/history; receipt/card PDF export; statement template/paper/Sinhala/history fixes; approved quotation charge transfer; unquoted labour charge entry; completion/delivery timestamps; latest-purchase valuation and recorded-cost profit estimates.

Financial corrections: accounting payment reversals reduce collections without reducing an issued sale; real refunds remain separate. Older returned goods preserve their original cost but cannot replace a newer purchase cost. Repair quote approval transfers selling charges without posting stock or payments. Financial and stock writes are transactional, permission checked, and retain original documents.

An approved live-backup folder has not been supplied; Google Drive folder/account/OAuth fields remain blank as requested. No host/production scheduler, production build, installer, packaging or deployment was performed. Physical printer/scanner checks, realistic-volume capacity acceptance and owner signatures remain pending. Latest purchase cost was explicitly selected by the owner. Purchase-wide discount/freight allocation remains a separate unanswered decision; affected profit estimates remain incomplete rather than assuming a policy. Existing role permissions were preserved: storekeepers can receive/issue/count/adjust, while purchase approval and supplier money operations require `purchases.approve`.

## Native checks

- PASS: two cashiers buying four of five units; one sale, one rejection, one unit remains.
- PASS: simultaneous independent checkouts receive distinct sale numbers.
- PASS: simultaneous identical retries create one sale and one payment.
- PASS: two simultaneous cashiers, invoice, one save and one rejection; barrier-to-completion 165.828 ms.
- PASS: two simultaneous cashiers, payment, one save and one rejection; barrier-to-completion 198.91 ms.
- PASS: one invoice-job reservation and exactly 100.00 allocated, no overpayment.
- PASS: service/PDF observations recorded: {"invoice-create-and-issue":57.288,"payment-receive-and-allocate":63.399,"pos-checkout-including-stock-posting":34.648,"a5-invoice-pdf-with-print-history":1424.018}
- PASS: two receptionists create atomic receipts and unique jobs; 2 concurrent workers; 149.996 ms; ["SAVED:1","SAVED:2"]
- PASS: two technicians update their assigned jobs with retained histories; 2 concurrent workers; 103.372 ms; ["SAVED:5","SAVED:6"]
- PASS: POS and repair issue compete for the same five units; 2 concurrent workers; 163.011 ms; ["SAVED:1","REJECTED:{\"cart\":[\"Insufficient saleable stock for Test spare part. Available: 1.000.\"]}"]
- PASS: two storekeepers cannot over-receive a purchase; 2 concurrent workers; 151.152 ms; ["REJECTED:{\"quantity\":[\"Quantity exceeds the unreceived purchase quantity.\"]}","SAVED:1"]
- PASS: receipt, technician, shared stock and GRN race invariants verified in disposable MySQL.
- PASS: real MySQL dump created, nonempty, SHA-256 verified, private and protected.
- PASS: confirmation and wrong-password refusals; authenticated CLI restore, safety dump, maintenance lifecycle, data recovery and audit history.

## Practical boundaries

Repair return currently reverses an entire unused, resalable issue before billing; damaged goods use the damage workflow. Receiving posts one purchase line/location per GRN submission, supporting partial deliveries. Supplier returns for historical purchases containing repeated lines for the same part are refused until their source is reconciled. No financial records are deleted to simplify these cases. Profit is an estimate of recorded costs, not an audited net-income certification.


# Integration test report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

Verified chains: intake/advance → approval pricing → repair stock issue → invoice → net payments → delivery; supplier purchase draft → approval/payable → partial receiving → stock ledger → supplier payment → reversal; supplier return → stock deduction → explicitly agreed supplier credit; POS versus repair competing for the same stock. Existing customer financial tables are untouched by supplier payment operations.

Draft purchase, quotation and part request saves do not change stock. Approval sets job prices but does not issue parts or collect money. Repeat posts are protected by stable keys and locked source documents. Failure cases and the native races exercise duplicate/over-receiving/overselling protection.

Native concurrency covers two receptionists, two assigned technicians on their respective jobs, two invoice/payment cashiers, POS versus repair issue, and two GRN receivers. This does not establish a whole-shop capacity limit or same-record optimistic conflict detection for arbitrary edits.
- PASS: two cashiers buying four of five units; one sale, one rejection, one unit remains.
- PASS: simultaneous independent checkouts receive distinct sale numbers.
- PASS: simultaneous identical retries create one sale and one payment.
- PASS: two simultaneous cashiers, invoice, one save and one rejection; barrier-to-completion 165.828 ms.
- PASS: two simultaneous cashiers, payment, one save and one rejection; barrier-to-completion 198.91 ms.
- PASS: one invoice-job reservation and exactly 100.00 allocated, no overpayment.
- PASS: service/PDF observations recorded: {"invoice-create-and-issue":57.288,"payment-receive-and-allocate":63.399,"pos-checkout-including-stock-posting":34.648,"a5-invoice-pdf-with-print-history":1424.018}
- PASS: two receptionists create atomic receipts and unique jobs; 2 concurrent workers; 149.996 ms; ["SAVED:1","SAVED:2"]
- PASS: two technicians update their assigned jobs with retained histories; 2 concurrent workers; 103.372 ms; ["SAVED:5","SAVED:6"]
- PASS: POS and repair issue compete for the same five units; 2 concurrent workers; 163.011 ms; ["SAVED:1","REJECTED:{\"cart\":[\"Insufficient saleable stock for Test spare part. Available: 1.000.\"]}"]
- PASS: two storekeepers cannot over-receive a purchase; 2 concurrent workers; 151.152 ms; ["REJECTED:{\"quantity\":[\"Quantity exceeds the unreceived purchase quantity.\"]}","SAVED:1"]
- PASS: receipt, technician, shared stock and GRN race invariants verified in disposable MySQL.
- PASS: real MySQL dump created, nonempty, SHA-256 verified, private and protected.
- PASS: confirmation and wrong-password refusals; authenticated CLI restore, safety dump, maintenance lifecycle, data recovery and audit history.

An approved live-backup folder has not been supplied; Google Drive folder/account/OAuth fields remain blank as requested. No host/production scheduler, production build, installer, packaging or deployment was performed. Physical printer/scanner checks, realistic-volume capacity acceptance and owner signatures remain pending. Latest purchase cost was explicitly selected by the owner. Purchase-wide discount/freight allocation remains a separate unanswered decision; affected profit estimates remain incomplete rather than assuming a policy. Existing role permissions were preserved: storekeepers can receive/issue/count/adjust, while purchase approval and supplier money operations require `purchases.approve`.


# Functional test report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

The final suite exercises the prior customer, intake, quotation, invoice, payment, POS, role/security/report/backup functionality and the new stock/purchasing/supplier workflows. StockPurchasingWorkflowTest covers the actual customer advance → receipt/job → quotation approval → stock issue → invoice → remaining payment → delivery path without directly setting billable job amounts. Inspection/technician authorization is also covered by the existing job suite and native assigned-job tests. Supplier edit/search preserves opening financial history. Preferred-supplier/opening-cost inputs are verified.

LatestPurchaseCostTest verifies newer purchase cost survives an older returned item's movement, exact valuation and refund-aware recorded margin; payment correction does not become a sales refund. PrintGapCompletionTest verifies supplier/GRN documents and statement settings/history. The complete source method inventory is in automated-test-inventory.md. A passing suite does not replace physical or owner UAT.
An approved live-backup folder has not been supplied; Google Drive folder/account/OAuth fields remain blank as requested. No host/production scheduler, production build, installer, packaging or deployment was performed. Physical printer/scanner checks, realistic-volume capacity acceptance and owner signatures remain pending. Latest purchase cost was explicitly selected by the owner. Purchase-wide discount/freight allocation remains a separate unanswered decision; affected profit estimates remain incomplete rather than assuming a policy. Existing role permissions were preserved: storekeepers can receive/issue/count/adjust, while purchase approval and supplier money operations require `purchases.approve`.


# Database relationship audit

Read-only audit of `nilambara_repair_management` on 2026-10-20T09:41:16+05:30. No application migrations or business data writes were performed by this audit script.

- 69 tables inspected, 247 foreign-key column mappings inspected, 0 orphan matches detected by per-column checks.

- Non-InnoDB/non-utf8mb4 tables: none.

- Floating-point storage columns: none.

- Compound associations are additionally verified by BusinessConstraintsTest and BusinessRelationshipsTest in the disposable database. The per-column orphan scan alone does not prove compound-key integrity.

- Infrastructure tables may use string primary keys. No primary key/index changes were made.

- Operational migration state: `storage/logs/step12-migrations.log`; route inventory: `storage/logs/step12-routes.json`.

- Full PK/FK ERD: [existing ERD](../database/erd.md). No schema changes in Step 12, so no ERD relationship changes are necessary.

## Tables and indexes

| Table | Rows at read-only snapshot | Index names |
|---|---:|---|
| audit_logs | 16 | PRIMARY, ix_audit_logs_da9eab2f, ix_audit_logs_92da6dde, ix_audit_logs_5fe56ab1, ix_audit_logs_cace4a15, audit_logs_occurred_at_index |
| backup_histories | 0 | PRIMARY, ix_backup_histories_5af47fbb, ix_backup_histories_48a3661d, ix_backup_histories_5fb88229 |
| backup_restores | 0 | PRIMARY, ix_backup_restores_b9e34ae4, ix_backup_restores_5fb88229 |
| cache | 0 | PRIMARY, cache_expiration_index |
| cache_locks | 0 | PRIMARY, cache_locks_expiration_index |
| customers | 0 | PRIMARY, uq_customers_68db6340, ix_customers_ac145587, ix_customers_a76c5cd4, ix_customers_e7e1e7cc |
| customer_account_entries | 0 | PRIMARY, uq_customer_account_entries_a5d450da, uq_customer_account_entries_7db4ecce, uq_customer_account_entries_1de3c56e, uq_customer_account_entries_15d25c20, uq_customer_account_entries_bc1ec442, uq_customer_account_entries_fcaec7df, uq_customer_account_entries_5b312e0c, uq_customer_account_entries_fc740855, fk_customer_account_entries_5585485a, fk_customer_account_entries_62d0b35a, fk_customer_account_entries_4355babb, fk_customer_account_entries_5bcc665a, fk_customer_account_entries_afce3f4c, ix_customer_account_entries_d947b0a0, ix_customer_account_entries_a7a13f4c, ix_customer_account_entries_06129e94 |
| document_print_histories | 0 | PRIMARY, ix_document_print_histories_52a2214d, ix_document_print_histories_66747a19, ix_document_print_histories_cf053e5b, ix_document_print_histories_596c572d, ix_document_print_histories_15d25c20, ix_document_print_histories_bc1ec442, ix_document_print_histories_fcaec7df, ix_document_print_histories_a7a13f4c, ix_document_print_histories_86cc2ebe, ix_document_print_histories_432b819f |
| document_sequences | 2 | PRIMARY, uq_document_sequences_aacd3103 |
| employees | 6 | PRIMARY, uq_employees_348a4a98, uq_employees_cace4a15 |
| expenses | 0 | PRIMARY, uq_expenses_b958d1d5, ix_expenses_4545e26c, ix_expenses_478b68a9, ix_expenses_06129e94 |
| expense_categories | 0 | PRIMARY, uq_expense_categories_6ae99955 |
| failed_jobs | 0 | PRIMARY, failed_jobs_uuid_unique, failed_jobs_connection_queue_failed_at_index |
| goods_received_notes | 0 | PRIMARY, uq_goods_received_notes_951d6c0e, uq_goods_received_notes_da00b996, fk_goods_received_notes_3e1bac6a, ix_goods_received_notes_86cc2ebe, ix_goods_received_notes_2bc0d2ca, ix_goods_received_notes_ec48f282 |
| goods_received_note_items | 0 | PRIMARY, uq_goods_received_note_items_156a1229, uq_goods_received_note_items_d9f67aa6, fk_goods_received_note_items_f23d6f0e, fk_goods_received_note_items_d8bea874, fk_goods_received_note_items_25d8e711, ix_goods_received_note_items_fc342424, ix_goods_received_note_items_86cc2ebe, ix_goods_received_note_items_e7e6cd38, ix_goods_received_note_items_10e3c1ce, ix_goods_received_note_items_876d7bdb |
| invoices | 0 | PRIMARY, uq_invoices_a5d450da, uq_invoices_60284980, ix_invoices_c0000d83, ix_invoices_feb4944b, ix_invoices_a7a13f4c, ix_invoices_782ecea6, ix_invoices_43e48017, invoices_status_due_date_index |
| invoice_history | 0 | PRIMARY, ix_invoice_history_event, ix_invoice_history_actor |
| invoice_items | 0 | PRIMARY, uq_invoice_items_d0e58f0e, fk_invoice_items_cf7ebf0c, ix_invoice_items_15d25c20, ix_invoice_items_0d3839f6, ix_invoice_items_c7f10065, ix_invoice_items_10e3c1ce |
| invoice_jobs | 0 | PRIMARY, uq_invoice_jobs_393bb4c9, uq_invoice_jobs_ec756383, uq_invoice_jobs_d2dc6852, fk_invoice_jobs_62d0b35a, fk_invoice_jobs_05167f50, ix_invoice_jobs_15d25c20, ix_invoice_jobs_a7a13f4c |
| jobs | 0 | PRIMARY, jobs_queue_index |
| job_approvals | 0 | PRIMARY, ix_job_approvals_d2dc6852, ix_job_approvals_06129e94 |
| job_batches | 0 | PRIMARY |
| job_cards | 0 | PRIMARY, uq_job_cards_d2dc6852, ix_job_cards_52a2214d |
| job_inspections | 0 | PRIMARY, ix_job_inspections_d2dc6852, ix_job_inspections_4a26244b, ix_job_inspections_9909258e |
| job_part_usages | 0 | PRIMARY, uq_job_part_usages_d9f67aa6, uq_job_part_usages_7db4ecce, ix_job_part_usages_d2dc6852, ix_job_part_usages_10e3c1ce, ix_job_part_usages_876d7bdb, ix_job_part_usages_1d9e5646, ix_job_part_usages_46df84f0 |
| job_status_histories | 0 | PRIMARY, ix_job_status_histories_04832136, ix_job_status_histories_ee56f6a4, ix_job_status_histories_d2dc6852, ix_job_status_histories_69259fd8 |
| migrations | 26 | PRIMARY |
| password_reset_tokens | 0 | PRIMARY |
| payments | 0 | PRIMARY, uq_payments_ad3551cc, uq_payments_39743c6d, uq_payments_7db4ecce, uq_payments_a5d450da, ix_payments_7c3ebe1b, ix_payments_6b26a8f9, ix_payments_a7a13f4c, ix_payments_ec48f282 |
| payment_allocations | 0 | PRIMARY, uq_payment_allocations_ae6c2db1, uq_payment_allocations_7db4ecce, fk_payment_allocations_5bcc665a, fk_payment_allocations_05167f50, fk_payment_allocations_62d0b35a, fk_payment_allocations_4355babb, fk_payment_allocations_5f5d56da, fk_payment_allocations_e4f5269a, ix_payment_allocations_fcaec7df, ix_payment_allocations_a7a13f4c, ix_payment_allocations_d2dc6852, ix_payment_allocations_15d25c20, ix_payment_allocations_bc1ec442, ix_payment_allocations_997c1d72 |
| payment_allocation_releases | 0 | PRIMARY, uq_payment_allocation_releases_7db4ecce, fk_payment_allocation_releases_cb606553, fk_payment_allocation_releases_b1bc50fb, ix_payment_allocation_releases_c33ff68d, ix_payment_allocation_releases_fcaec7df, ix_payment_allocation_releases_5b312e0c, ix_payment_allocation_releases_e78980db |
| payment_reversals | 0 | PRIMARY, uq_payment_reversals_ae6c2db1, uq_payment_reversals_7db4ecce, uq_payment_reversals_a5d450da, fk_payment_reversals_5f5d56da, ix_payment_reversals_fcaec7df, ix_payment_reversals_a7a13f4c, ix_payment_reversals_07fdd642 |
| permissions | 98 | PRIMARY, uq_permissions_e6fb0621, uq_permissions_name_guard |
| permission_role | 216 | PRIMARY, uq_permission_role_1fd58356, ix_permission_role_b36bcfe0, ix_permission_role_3b8b97af |
| permission_user | 0 | PRIMARY, uq_permission_user_6d378d26, ix_permission_user_cace4a15, ix_permission_user_3b8b97af |
| pos_sales | 0 | PRIMARY, uq_pos_sales_ad3551cc, uq_pos_sales_1407226b, uq_pos_sales_a5d450da, uq_step10_pos_sale_idempotency, uq_step10_pos_payment_reference, ix_pos_sales_a8fee2cf, ix_pos_sales_a7a13f4c, ix_pos_sales_782ecea6, pos_sales_payment_verified_by_user_id_foreign |
| pos_sale_items | 0 | PRIMARY, uq_pos_sale_items_3da73ab0, uq_pos_sale_items_d9f67aa6, ix_pos_sale_items_bc1ec442, ix_pos_sale_items_10e3c1ce, ix_pos_sale_items_876d7bdb |
| print_templates | 8 | PRIMARY, uq_print_templates_1f1f8ab9 |
| purchases | 0 | PRIMARY, uq_purchases_4e0db050, uq_purchases_6c59959c, uq_purchases_95419c78, ix_purchases_d9987142, ix_purchases_2bc0d2ca, ix_purchases_ae7720e8, purchases_status_due_date_index |
| purchase_items | 0 | PRIMARY, uq_purchase_items_ed918c72, uq_purchase_items_951d6c0e, uq_purchase_items_26e56352, ix_purchase_items_86cc2ebe, ix_purchase_items_10e3c1ce |
| quotations | 0 | PRIMARY, uq_quotations_4bfa8930, uq_quotations_73fda6a0, uq_quotations_b0fb8224, fk_quotations_05167f50, fk_quotations_69de8f01, ix_quotations_0bd5d101, ix_quotations_d2dc6852, ix_quotations_a7a13f4c, ix_quotations_ae7720e8, quotations_updated_by_user_id_foreign, ix_quotations_prep_approval |
| quotation_approvals | 0 | PRIMARY, ix_quotation_approvals_596c572d, ix_quotation_approvals_06129e94 |
| quotation_history | 0 | PRIMARY, ix_quotation_history_event, ix_quotation_history_actor |
| quotation_items | 0 | PRIMARY, uq_quotation_items_9d5c5c2c, ix_quotation_items_596c572d, ix_quotation_items_10e3c1ce |
| repair_jobs | 0 | PRIMARY, uq_repair_jobs_a5d450da, uq_repair_jobs_e9c25de9, uq_repair_jobs_8bf12fb6, ix_repair_jobs_b8aca1c7, ix_repair_jobs_67b6d081, ix_repair_jobs_c6ae1260, ix_repair_jobs_a7a13f4c, ix_repair_jobs_ec48f282, ix_repair_jobs_465640da, ix_repair_jobs_43e48017, repair_jobs_received_at_index, repair_jobs_completed_at_index, repair_jobs_delivered_at_index |
| repair_receipts | 0 | PRIMARY, uq_repair_receipts_d2dc6852, uq_repair_receipts_dc11faa3, fk_repair_receipts_05167f50, ix_repair_receipts_a7a13f4c |
| roles | 6 | PRIMARY, uq_roles_e6fb0621, uq_roles_name_guard |
| role_user | 6 | PRIMARY, uq_role_user_721adde8, ix_role_user_cace4a15, ix_role_user_b36bcfe0 |
| sessions | 7 | PRIMARY, sessions_user_id_index, sessions_last_activity_index |
| spare_parts | 0 | PRIMARY, uq_spare_parts_cf559ae3, uq_spare_parts_006b53f0, ix_spare_parts_6ae99955, ix_spare_parts_a5d20765, ix_spare_parts_9c4e4a89, ix_spare_parts_4252c4be, ix_spare_parts_2bc0d2ca, ix_spare_parts_4ea168a9 |
| spare_part_brands | 0 | PRIMARY, uq_spare_part_brands_6ae99955 |
| spare_part_categories | 0 | PRIMARY, uq_spare_part_categories_6ae99955 |
| stock_adjustments | 0 | PRIMARY, uq_stock_adjustments_4fe26025, ix_stock_adjustments_ae7720e8, ix_stock_adjustments_46df84f0 |
| stock_adjustment_items | 0 | PRIMARY, uq_stock_adjustment_items_1e5daea3, uq_stock_adjustment_items_d9f67aa6, ix_stock_adjustment_items_a140820a, ix_stock_adjustment_items_10e3c1ce, ix_stock_adjustment_items_876d7bdb |
| stock_counts | 0 | PRIMARY, uq_stock_counts_568cf27e, ix_stock_counts_c0622bff |
| stock_count_items | 0 | PRIMARY, uq_stock_count_items_ee7e6c5a, uq_stock_count_items_d9f67aa6, ix_stock_count_items_942c8300, ix_stock_count_items_10e3c1ce, ix_stock_count_items_876d7bdb |
| stock_locations | 0 | PRIMARY, uq_stock_locations_e6fb0621 |
| stock_movements | 0 | PRIMARY, uq_stock_movements_d9f67aa6, uq_stock_movements_7db4ecce, uq_stock_movements_36e939cc, uq_stock_movements_59d7f54b, uq_stock_movements_53950c4b, uq_stock_movements_eba31e8f, uq_stock_movements_81078615, uq_stock_movements_ec123f6e, uq_stock_movements_28c2bb76, fk_stock_movements_d942adbc, fk_stock_movements_037ecddd, fk_stock_movements_cc6702aa, fk_stock_movements_ad495a05, fk_stock_movements_0d8111ca, fk_stock_movements_f4ec6a20, fk_stock_movements_693404d5, ix_stock_movements_fa634624, ix_stock_movements_d084c0da, ix_stock_movements_10e3c1ce, ix_stock_movements_876d7bdb, ix_stock_movements_06129e94, ix_stock_movements_35647365 |
| stock_returns | 0 | PRIMARY, uq_stock_returns_855a7dba, uq_step10_pos_return_idempotency, ix_stock_returns_d2dc6852, ix_stock_returns_bc1ec442, ix_stock_returns_86cc2ebe, ix_stock_returns_06129e94, stock_returns_refund_payment_reversal_id_foreign |
| stock_return_items | 0 | PRIMARY, uq_stock_return_items_d9f67aa6, ix_stock_return_items_7032963c, ix_stock_return_items_10e3c1ce, ix_stock_return_items_876d7bdb, stock_return_items_pos_sale_item_id_foreign |
| suppliers | 0 | PRIMARY, uq_suppliers_e1183bab, ix_suppliers_6ae99955, ix_suppliers_e7e1e7cc |
| supplier_account_entries | 0 | PRIMARY, uq_supplier_account_entries_4e0db050, uq_supplier_account_entries_7db4ecce, uq_supplier_account_entries_1de3c56e, uq_supplier_account_entries_86cc2ebe, uq_supplier_account_entries_69c70222, uq_supplier_account_entries_3ec7caab, uq_supplier_account_entries_fc740855, fk_supplier_account_entries_10afd45a, fk_supplier_account_entries_3e1bac6a, fk_supplier_account_entries_e24bcc51, fk_supplier_account_entries_80141e8c, ix_supplier_account_entries_a3fe3b0b, ix_supplier_account_entries_2bc0d2ca, ix_supplier_account_entries_06129e94 |
| supplier_allocation_releases | 0 | PRIMARY, uq_supplier_allocation_releases_7db4ecce, fk_supplier_allocation_releases_fd2690eb, fk_supplier_allocation_releases_2e689253, ix_supplier_allocation_releases_ba299898, ix_supplier_allocation_releases_69c70222, ix_supplier_allocation_releases_3ec7caab, ix_supplier_allocation_releases_e78980db |
| supplier_payments | 0 | PRIMARY, uq_supplier_payments_4e0db050, uq_supplier_payments_39743c6d, uq_supplier_payments_7db4ecce, ix_supplier_payments_7c3ebe1b, ix_supplier_payments_2bc0d2ca, ix_supplier_payments_38e2e5db |
| supplier_payment_allocations | 0 | PRIMARY, uq_supplier_payment_allocations_3f75acb8, uq_supplier_payment_allocations_7db4ecce, fk_supplier_payment_allocations_e24bcc51, fk_supplier_payment_allocations_3e1bac6a, ix_supplier_payment_allocations_69c70222, ix_supplier_payment_allocations_2bc0d2ca, ix_supplier_payment_allocations_86cc2ebe, ix_supplier_payment_allocations_997c1d72 |
| supplier_payment_reversals | 0 | PRIMARY, uq_supplier_payment_reversals_3f75acb8, uq_supplier_payment_reversals_4e0db050, uq_supplier_payment_reversals_7db4ecce, fk_supplier_payment_reversals_e24bcc51, ix_supplier_payment_reversals_69c70222, ix_supplier_payment_reversals_2bc0d2ca, ix_supplier_payment_reversals_07fdd642 |
| system_settings | 0 | PRIMARY, uq_system_settings_3b49c75a, ix_system_settings_7a022eb2 |
| units_of_measure | 0 | PRIMARY, uq_units_of_measure_e6fb0621 |
| users | 7 | PRIMARY, users_email_unique, uq_users_username |

## Declared foreign-key mappings

| Child | Parent | Delete rule | Orphans |
|---|---|---|---:|
| audit_logs.user_id | users.id | RESTRICT | 0 |
| backup_histories.initiated_by_user_id | users.id | RESTRICT | 0 |
| backup_restores.initiated_by_user_id | users.id | RESTRICT | 0 |
| backup_restores.backup_history_id | backup_histories.id | RESTRICT | 0 |
| customer_account_entries.invoice_id | invoices.id | RESTRICT | 0 |
| customer_account_entries.reverses_entry_id | customer_account_entries.id | RESTRICT | 0 |
| customer_account_entries.payment_id | payments.id | RESTRICT | 0 |
| customer_account_entries.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| customer_account_entries.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| customer_account_entries.customer_id | pos_sales.customer_id | RESTRICT | 0 |
| customer_account_entries.payment_reversal_id | payment_reversals.id | RESTRICT | 0 |
| customer_account_entries.customer_id | payment_reversals.customer_id | RESTRICT | 0 |
| customer_account_entries.payment_id | payments.id | RESTRICT | 0 |
| customer_account_entries.customer_id | payments.customer_id | RESTRICT | 0 |
| customer_account_entries.invoice_id | invoices.id | RESTRICT | 0 |
| customer_account_entries.customer_id | invoices.customer_id | RESTRICT | 0 |
| customer_account_entries.payment_reversal_id | payment_reversals.id | RESTRICT | 0 |
| customer_account_entries.recorded_by_user_id | users.id | RESTRICT | 0 |
| customer_account_entries.reverses_entry_id | customer_account_entries.id | RESTRICT | 0 |
| customer_account_entries.customer_id | customer_account_entries.customer_id | RESTRICT | 0 |
| customer_account_entries.customer_id | customers.id | RESTRICT | 0 |
| document_print_histories.invoice_id | invoices.id | RESTRICT | 0 |
| document_print_histories.repair_receipt_id | repair_receipts.id | RESTRICT | 0 |
| document_print_histories.payment_id | payments.id | RESTRICT | 0 |
| document_print_histories.printed_by_user_id | users.id | RESTRICT | 0 |
| document_print_histories.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| document_print_histories.purchase_id | purchases.id | RESTRICT | 0 |
| document_print_histories.quotation_id | quotations.id | RESTRICT | 0 |
| document_print_histories.job_card_id | job_cards.id | RESTRICT | 0 |
| document_print_histories.print_template_id | print_templates.id | RESTRICT | 0 |
| document_print_histories.customer_id | customers.id | RESTRICT | 0 |
| employees.user_id | users.id | RESTRICT | 0 |
| expenses.expense_category_id | expense_categories.id | RESTRICT | 0 |
| expenses.recorded_by_user_id | users.id | RESTRICT | 0 |
| goods_received_notes.purchase_id | purchases.id | RESTRICT | 0 |
| goods_received_notes.supplier_id | purchases.supplier_id | RESTRICT | 0 |
| goods_received_notes.purchase_id | purchases.id | RESTRICT | 0 |
| goods_received_notes.received_by_employee_id | employees.id | RESTRICT | 0 |
| goods_received_notes.supplier_id | suppliers.id | RESTRICT | 0 |
| goods_received_note_items.purchase_item_id | purchase_items.id | RESTRICT | 0 |
| goods_received_note_items.purchase_item_id | purchase_items.id | RESTRICT | 0 |
| goods_received_note_items.spare_part_id | purchase_items.spare_part_id | RESTRICT | 0 |
| goods_received_note_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| goods_received_note_items.location_id | stock_locations.id | RESTRICT | 0 |
| goods_received_note_items.purchase_id | purchases.id | RESTRICT | 0 |
| goods_received_note_items.goods_received_note_id | goods_received_notes.id | RESTRICT | 0 |
| goods_received_note_items.purchase_item_id | purchase_items.id | RESTRICT | 0 |
| goods_received_note_items.purchase_id | purchase_items.purchase_id | RESTRICT | 0 |
| goods_received_note_items.goods_received_note_id | goods_received_notes.id | RESTRICT | 0 |
| goods_received_note_items.purchase_id | goods_received_notes.purchase_id | RESTRICT | 0 |
| invoices.cashier_employee_id | employees.id | RESTRICT | 0 |
| invoices.cancelled_by_user_id | users.id | RESTRICT | 0 |
| invoices.customer_id | customers.id | RESTRICT | 0 |
| invoice_history.actor_user_id | users.id | RESTRICT | 0 |
| invoice_history.invoice_id | invoices.id | RESTRICT | 0 |
| invoice_items.invoice_id | invoices.id | RESTRICT | 0 |
| invoice_items.quotation_item_id | quotation_items.id | RESTRICT | 0 |
| invoice_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| invoice_items.invoice_job_id | invoice_jobs.id | RESTRICT | 0 |
| invoice_items.invoice_job_id | invoice_jobs.id | RESTRICT | 0 |
| invoice_items.invoice_id | invoice_jobs.invoice_id | RESTRICT | 0 |
| invoice_jobs.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| invoice_jobs.customer_id | repair_jobs.customer_id | RESTRICT | 0 |
| invoice_jobs.invoice_id | invoices.id | RESTRICT | 0 |
| invoice_jobs.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| invoice_jobs.invoice_id | invoices.id | RESTRICT | 0 |
| invoice_jobs.customer_id | invoices.customer_id | RESTRICT | 0 |
| invoice_jobs.customer_id | customers.id | RESTRICT | 0 |
| job_approvals.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| job_approvals.recorded_by_user_id | users.id | RESTRICT | 0 |
| job_cards.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| job_cards.print_template_id | print_templates.id | RESTRICT | 0 |
| job_inspections.inspected_by_employee_id | employees.id | RESTRICT | 0 |
| job_inspections.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| job_inspections.repaired_by_employee_id | employees.id | RESTRICT | 0 |
| job_part_usages.spare_part_id | spare_parts.id | RESTRICT | 0 |
| job_part_usages.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| job_part_usages.approved_by_user_id | users.id | RESTRICT | 0 |
| job_part_usages.location_id | stock_locations.id | RESTRICT | 0 |
| job_part_usages.requested_by_user_id | users.id | RESTRICT | 0 |
| job_status_histories.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| job_status_histories.changed_by_user_id | users.id | RESTRICT | 0 |
| payments.received_by_employee_id | employees.id | RESTRICT | 0 |
| payments.customer_id | customers.id | RESTRICT | 0 |
| payment_allocations.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| payment_allocations.customer_id | repair_jobs.customer_id | RESTRICT | 0 |
| payment_allocations.invoice_id | invoices.id | RESTRICT | 0 |
| payment_allocations.payment_id | payments.id | RESTRICT | 0 |
| payment_allocations.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| payment_allocations.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| payment_allocations.customer_id | pos_sales.customer_id | RESTRICT | 0 |
| payment_allocations.payment_id | payments.id | RESTRICT | 0 |
| payment_allocations.customer_id | payments.customer_id | RESTRICT | 0 |
| payment_allocations.payment_id | payments.id | RESTRICT | 0 |
| payment_allocations.customer_scope_id | payments.customer_scope_id | RESTRICT | 0 |
| payment_allocations.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| payment_allocations.invoice_id | invoices.id | RESTRICT | 0 |
| payment_allocations.customer_id | invoices.customer_id | RESTRICT | 0 |
| payment_allocations.allocated_by_user_id | users.id | RESTRICT | 0 |
| payment_allocations.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| payment_allocations.customer_scope_id | pos_sales.customer_scope_id | RESTRICT | 0 |
| payment_allocations.customer_id | customers.id | RESTRICT | 0 |
| payment_allocation_releases.payment_id | payments.id | RESTRICT | 0 |
| payment_allocation_releases.payment_allocation_id | payment_allocations.id | RESTRICT | 0 |
| payment_allocation_releases.released_by_user_id | users.id | RESTRICT | 0 |
| payment_allocation_releases.payment_reversal_id | payment_reversals.id | RESTRICT | 0 |
| payment_allocation_releases.payment_reversal_id | payment_reversals.id | RESTRICT | 0 |
| payment_allocation_releases.payment_id | payment_reversals.payment_id | RESTRICT | 0 |
| payment_allocation_releases.payment_allocation_id | payment_allocations.id | RESTRICT | 0 |
| payment_allocation_releases.payment_id | payment_allocations.payment_id | RESTRICT | 0 |
| payment_reversals.payment_id | payments.id | RESTRICT | 0 |
| payment_reversals.reversed_by_user_id | users.id | RESTRICT | 0 |
| payment_reversals.payment_id | payments.id | RESTRICT | 0 |
| payment_reversals.customer_scope_id | payments.customer_scope_id | RESTRICT | 0 |
| payment_reversals.customer_id | customers.id | RESTRICT | 0 |
| permission_role.role_id | roles.id | RESTRICT | 0 |
| permission_role.permission_id | permissions.id | RESTRICT | 0 |
| permission_user.user_id | users.id | RESTRICT | 0 |
| permission_user.permission_id | permissions.id | RESTRICT | 0 |
| pos_sales.cashier_employee_id | employees.id | RESTRICT | 0 |
| pos_sales.customer_id | customers.id | RESTRICT | 0 |
| pos_sales.payment_verified_by_user_id | users.id | RESTRICT | 0 |
| pos_sale_items.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| pos_sale_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| pos_sale_items.location_id | stock_locations.id | RESTRICT | 0 |
| purchases.created_by_user_id | users.id | RESTRICT | 0 |
| purchases.supplier_id | suppliers.id | RESTRICT | 0 |
| purchase_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| purchase_items.purchase_id | purchases.id | RESTRICT | 0 |
| quotations.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| quotations.customer_id | repair_jobs.customer_id | RESTRICT | 0 |
| quotations.created_by_user_id | users.id | RESTRICT | 0 |
| quotations.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| quotations.revises_quotation_id | quotations.id | RESTRICT | 0 |
| quotations.revises_quotation_id | quotations.id | RESTRICT | 0 |
| quotations.repair_job_id | quotations.repair_job_id | RESTRICT | 0 |
| quotations.customer_id | quotations.customer_id | RESTRICT | 0 |
| quotations.customer_id | customers.id | RESTRICT | 0 |
| quotations.updated_by_user_id | users.id | RESTRICT | 0 |
| quotation_approvals.recorded_by_user_id | users.id | RESTRICT | 0 |
| quotation_approvals.quotation_id | quotations.id | RESTRICT | 0 |
| quotation_history.actor_user_id | users.id | RESTRICT | 0 |
| quotation_history.quotation_id | quotations.id | RESTRICT | 0 |
| quotation_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| quotation_items.quotation_id | quotations.id | RESTRICT | 0 |
| repair_jobs.technician_id | employees.id | RESTRICT | 0 |
| repair_jobs.received_by_employee_id | employees.id | RESTRICT | 0 |
| repair_jobs.cancelled_by_user_id | users.id | RESTRICT | 0 |
| repair_jobs.customer_id | customers.id | RESTRICT | 0 |
| repair_receipts.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| repair_receipts.customer_id | repair_jobs.customer_id | RESTRICT | 0 |
| repair_receipts.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| repair_receipts.customer_id | customers.id | RESTRICT | 0 |
| role_user.role_id | roles.id | RESTRICT | 0 |
| role_user.user_id | users.id | RESTRICT | 0 |
| spare_parts.default_location_id | stock_locations.id | RESTRICT | 0 |
| spare_parts.unit_id | units_of_measure.id | RESTRICT | 0 |
| spare_parts.category_id | spare_part_categories.id | RESTRICT | 0 |
| spare_parts.supplier_id | suppliers.id | RESTRICT | 0 |
| spare_parts.brand_id | spare_part_brands.id | RESTRICT | 0 |
| stock_adjustments.created_by_user_id | users.id | RESTRICT | 0 |
| stock_adjustments.approved_by_user_id | users.id | RESTRICT | 0 |
| stock_adjustment_items.stock_adjustment_id | stock_adjustments.id | RESTRICT | 0 |
| stock_adjustment_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| stock_adjustment_items.location_id | stock_locations.id | RESTRICT | 0 |
| stock_counts.counted_by_user_id | users.id | RESTRICT | 0 |
| stock_count_items.stock_count_id | stock_counts.id | RESTRICT | 0 |
| stock_count_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| stock_count_items.location_id | stock_locations.id | RESTRICT | 0 |
| stock_movements.job_part_usage_id | job_part_usages.id | RESTRICT | 0 |
| stock_movements.spare_part_id | job_part_usages.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | job_part_usages.location_id | RESTRICT | 0 |
| stock_movements.count_item_id | stock_count_items.id | RESTRICT | 0 |
| stock_movements.adjustment_item_id | stock_adjustment_items.id | RESTRICT | 0 |
| stock_movements.spare_part_id | stock_adjustment_items.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | stock_adjustment_items.location_id | RESTRICT | 0 |
| stock_movements.reverses_movement_id | stock_movements.id | RESTRICT | 0 |
| stock_movements.spare_part_id | spare_parts.id | RESTRICT | 0 |
| stock_movements.reverses_movement_id | stock_movements.id | RESTRICT | 0 |
| stock_movements.spare_part_id | stock_movements.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | stock_movements.location_id | RESTRICT | 0 |
| stock_movements.location_id | stock_locations.id | RESTRICT | 0 |
| stock_movements.recorded_by_user_id | users.id | RESTRICT | 0 |
| stock_movements.adjustment_item_id | stock_adjustment_items.id | RESTRICT | 0 |
| stock_movements.return_item_id | stock_return_items.id | RESTRICT | 0 |
| stock_movements.spare_part_id | stock_return_items.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | stock_return_items.location_id | RESTRICT | 0 |
| stock_movements.return_item_id | stock_return_items.id | RESTRICT | 0 |
| stock_movements.pos_sale_item_id | pos_sale_items.id | RESTRICT | 0 |
| stock_movements.pos_sale_item_id | pos_sale_items.id | RESTRICT | 0 |
| stock_movements.spare_part_id | pos_sale_items.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | pos_sale_items.location_id | RESTRICT | 0 |
| stock_movements.job_part_usage_id | job_part_usages.id | RESTRICT | 0 |
| stock_movements.grn_item_id | goods_received_note_items.id | RESTRICT | 0 |
| stock_movements.spare_part_id | goods_received_note_items.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | goods_received_note_items.location_id | RESTRICT | 0 |
| stock_movements.grn_item_id | goods_received_note_items.id | RESTRICT | 0 |
| stock_movements.count_item_id | stock_count_items.id | RESTRICT | 0 |
| stock_movements.spare_part_id | stock_count_items.spare_part_id | RESTRICT | 0 |
| stock_movements.location_id | stock_count_items.location_id | RESTRICT | 0 |
| stock_returns.pos_sale_id | pos_sales.id | RESTRICT | 0 |
| stock_returns.repair_job_id | repair_jobs.id | RESTRICT | 0 |
| stock_returns.purchase_id | purchases.id | RESTRICT | 0 |
| stock_returns.recorded_by_user_id | users.id | RESTRICT | 0 |
| stock_returns.refund_payment_reversal_id | payment_reversals.id | RESTRICT | 0 |
| stock_return_items.spare_part_id | spare_parts.id | RESTRICT | 0 |
| stock_return_items.location_id | stock_locations.id | RESTRICT | 0 |
| stock_return_items.stock_return_id | stock_returns.id | RESTRICT | 0 |
| stock_return_items.pos_sale_item_id | pos_sale_items.id | RESTRICT | 0 |
| supplier_account_entries.supplier_payment_reversal_id | supplier_payment_reversals.id | RESTRICT | 0 |
| supplier_account_entries.supplier_id | supplier_payment_reversals.supplier_id | RESTRICT | 0 |
| supplier_account_entries.purchase_id | purchases.id | RESTRICT | 0 |
| supplier_account_entries.supplier_id | purchases.supplier_id | RESTRICT | 0 |
| supplier_account_entries.reverses_entry_id | supplier_account_entries.id | RESTRICT | 0 |
| supplier_account_entries.reverses_entry_id | supplier_account_entries.id | RESTRICT | 0 |
| supplier_account_entries.supplier_id | supplier_account_entries.supplier_id | RESTRICT | 0 |
| supplier_account_entries.purchase_id | purchases.id | RESTRICT | 0 |
| supplier_account_entries.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_account_entries.supplier_payment_reversal_id | supplier_payment_reversals.id | RESTRICT | 0 |
| supplier_account_entries.recorded_by_user_id | users.id | RESTRICT | 0 |
| supplier_account_entries.supplier_id | suppliers.id | RESTRICT | 0 |
| supplier_account_entries.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_account_entries.supplier_id | supplier_payments.supplier_id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_reversal_id | supplier_payment_reversals.id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_id | supplier_payment_reversals.supplier_payment_id | RESTRICT | 0 |
| supplier_allocation_releases.released_by_user_id | users.id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_allocation_id | supplier_payment_allocations.id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_reversal_id | supplier_payment_reversals.id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_allocation_id | supplier_payment_allocations.id | RESTRICT | 0 |
| supplier_allocation_releases.supplier_payment_id | supplier_payment_allocations.supplier_payment_id | RESTRICT | 0 |
| supplier_payments.paid_by_user_id | users.id | RESTRICT | 0 |
| supplier_payments.supplier_id | suppliers.id | RESTRICT | 0 |
| supplier_payment_allocations.purchase_id | purchases.id | RESTRICT | 0 |
| supplier_payment_allocations.supplier_id | purchases.supplier_id | RESTRICT | 0 |
| supplier_payment_allocations.purchase_id | purchases.id | RESTRICT | 0 |
| supplier_payment_allocations.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_payment_allocations.allocated_by_user_id | users.id | RESTRICT | 0 |
| supplier_payment_allocations.supplier_id | suppliers.id | RESTRICT | 0 |
| supplier_payment_allocations.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_payment_allocations.supplier_id | supplier_payments.supplier_id | RESTRICT | 0 |
| supplier_payment_reversals.reversed_by_user_id | users.id | RESTRICT | 0 |
| supplier_payment_reversals.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_payment_reversals.supplier_id | suppliers.id | RESTRICT | 0 |
| supplier_payment_reversals.supplier_payment_id | supplier_payments.id | RESTRICT | 0 |
| supplier_payment_reversals.supplier_id | supplier_payments.supplier_id | RESTRICT | 0 |
| system_settings.updated_by_user_id | users.id | RESTRICT | 0 |

Follow-up exact tuple audit inspected 199 complete foreign-key constraints, including composite keys; orphan tuples: 0. Before/after operational table row counts are identical (`step12-preservation.log`). Row-count equality is not a byte-for-byte data comparison.

Financial, stock and history records use restrictive delete behavior tested in SchemaIntegrityTest; authentication/permission infrastructure has its own cascade rules. Polymorphic audit identities intentionally have no target FK. Models/relationships are tested separately; supplier/purchase relationships do not establish operational CRUD support.

## Pending-work follow-up

No schema changes were necessary. All workflows reuse the existing 69 tables and declared keys. Follow-up before/after snapshots and exact composite-key orphan checks are recorded separately. See pending-preservation.log; row-count equality is not a byte-for-byte data comparison.


# Security test report

Safe local testing only. AuthenticationTest executes password rejection, CSRF enforcement, five-attempt rate limiting, session regeneration, logout invalidation, disabled-account rejection and no public registration. EmployeeManagementTest tests all six role definitions, per-user denial precedence, direct route restrictions, assigned technician access and last-administrator protection. Payment/POS/backup tests exercise sensitive operation permissions.

Step12AuditTest sends an SQL-looking search value and verifies it remains data, then verifies stored script markup is escaped in customer details. Report sort fields are allowlisted. These narrow probes do not constitute exhaustive penetration testing of every field.

SystemSupportTest rejects unknown/secret editable settings; AuditSafetyTest covers redaction and historical log safety. Backup tests reject web-root destinations and traversal, conceal private paths, verify checksums and require authenticated restore confirmation. Google Drive transport is mocked; no actual account access is configured.

PHP syntax and Composer validation results are in their evidence logs. Composer audit reported no security vulnerability advisories at execution time; this is a dependency advisory result, not proof the application has no vulnerabilities. No package versions were changed.

Unverified: full hostile upload corpus, every stored/reflected XSS sink, end-to-end browser session timeout, local filesystem permission guarantees for every Windows account, production HTTPS/cookie configuration (outside this phase). No security mechanism was disabled to pass business tests. Supplier/stock authorization cannot be accepted for absent workflows. The conflicting management/storekeeper purchase rule requires owner agreement.

## Pending-work follow-up

New routes inherit auth/active-account/CSRF middleware. Services also enforce permissions for financial posting, stock operations and assigned-job requests. Cashiers cannot administer suppliers/stock; storekeepers cannot post supplier payments or approve purchases using the existing role matrix. Quoted, billed and closed job charges cannot be changed through the unquoted pricing endpoint. No credential values were introduced into settings or reports. New SQL filters are parameterized; print fields remain escaped. The prior text describing supplier/stock operations as absent is superseded by this section and the final workflow suite.


# Financial reconciliation report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

Exact disposable-fixture assertions include: approved quotation total 30.00, advance 5.00, outstanding 25.00 then 0.00; repair parts cost 6.00 and labour cost 2.00 produce recorded profit estimate 22.00. Supplier purchase total 16.00, payment 6.00 leaves 10.00; full reversal restores 16.00. Agreed supplier return credit 5.00 leaves 11.00. Opening balance 10.50 less payment 3.50 gives 7.00, restored to 10.50 after reversal. These are test fixtures only.

Latest-purchase fixture: an old sale costs 3.00, a new purchase costs 7.00, the old item returns at its original 3.00, and a subsequent sale still snapshots 7.00. Nine units are valued at 63.00. Net revenue 10.00 and net recovered/sold cost 7.00 produce a 3.00 recorded margin. Unknown costs remain unknown, not invented zero costs.

Invoice and customer payment calculations continue to derive net allocations/releases; advances are not counted twice. Supplier balances use the existing supplier ledger, with positive purchase/opening liabilities, negative payments/return credits, and linked positive reversals. Original entries remain append-only. No parallel accounting ledger was created.

Latest purchase cost is owner-approved. Purchase-level freight/discount allocation is not yet approved. Affected estimates, cost mismatches, unknown source costs, no-refund stock returns and ambiguous repair cash refunds remain labelled incomplete. Profit is not sales revenue or a certified accounting statement.


# Inventory reconciliation report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

One signed movement ledger supports opening stock, GRNs, authorized repair issues, POS, resalable returns, supplier returns, damage, corrections and physical counts. The part is locked before checking current ledger quantities, using the same product lock as POS. Requests do not reserve or deduct stock; approval-and-issue records both authorization and movement atomically. Complete unused issues may be returned once before billing, retaining the original negative movement and a linked positive return.

Native shared-stock race: repair and POS each request four from five; one succeeds and one rejects, leaving one unit. Native GRN race: two receivers request four against five ordered; exactly four are received once. Sequential replay, over-receiving, counts and damage cases also pass. Negative stock is rejected by the workflow. Counts record expected and observed quantities before posting their difference.

Valuation uses the latest posted purchase cost at the location, falling back to an explicitly known opening cost only when no purchase cost exists. Old returns retain original cost snapshots and do not alter the selected latest purchase cost. Unknown costs are visibly excluded from known valuation totals. Purchase-wide adjustment allocation remains pending.


# Local performance test report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

One local service iteration per operation; fixture construction excluded; no browser/network latency.

| Operation | ms |
|---|---:|
| invoice-create-and-issue | 57.288 |
| payment-receive-and-allocate | 63.399 |
| pos-checkout-including-stock-posting | 34.648 |
| a5-invoice-pdf-with-print-history | 1424.018 |

Dataset row counts: `{"customers": 2, "repair_jobs": 2, "invoices": 2, "payments": 7, "pos_sales": 5, "spare_parts": 3, "stock_movements": 8}`. Native race timings are in the completion report. These are small local observations, not percentiles or production capacity. Existing report/dashboard/customer/job timing fixture output remains in step12-local-timings.json. No realistic-volume capacity or statistically defensible slow-query benchmark was performed.


# Printing and PDF verification

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

The earlier fixture run generated and visually checked PDFs using embedded regular/bold Noto Sinhala fonts: A5 repair receipt (one page), A6 job card (two pages), customized A5 landscape statement (one page), A4 supplier purchase and GRN (one page each). Exact dimensions/font metadata are in pending-pdf-review.json; artifacts are under storage/app/testing/pending-*.pdf and *-review-*.png. Earlier overflow and missing-glyph defects were corrected. Long documents may legitimately paginate beyond these short-fixture counts.

Statement paper/orientation/title now drive both browser CSS and PDF paper, and statement prints/duplicates preserve history. Invoice visible heading uses its configured title. POS receipt PDFs now embed Sinhala fonts and honor configured font size, including thermal output. Intake PDF download/reprint routes enforce original permissions and retain duplicate labels. Supplier/GRN documents support browser print, PDF, duplication, branding, margins, signatures and audit history using the existing purchase print template.

No physical printer/scanner or fresh interactive browser print-preview acceptance was performed. Physical margins, two-sided card handling, thermal roll behavior and native-language business approval remain owner/hardware UAT items. No asset build or packaging occurred.


# Backup and restore test report

Schedule definition: daily `0 2 * * *`, Asia/Colombo; configured retention 30 days, maximum 30 scheduled daily copies. Manual copies are protected. `step12-schedule.log` records schedule inspection. No production/Windows scheduler was installed or enabled.

Step11BackupTest verifies retention, latest recoverable copy protection, history retention, changed/missing/path-traversal detection, private destination checks, blank Drive disabling and mocked remote checksum/size verification. BackupManagementTest tests authorization, history and failures.

Native helper actually creates a nonempty MySQL dump, verifies SHA-256, rejects absent confirmation/wrong password, creates a safety dump, restores altered fixture data, checks recovered data/audit/history and maintenance cleanup. It runs only against the newly created disposable MySQL database and an isolated temporary storage path. Evidence: `PASS: real MySQL dump...` and `PASS: confirmation and wrong-password refusals...` in full-run logs. This is not a restore over the operational database.

Operational destination remains blank pending an explicitly approved location. Earlier automatic approval review rejected exporting live data to an unspecified/unapproved backup destination; no such export was performed in Step 12. Google Drive folder/account/OAuth credentials remain blank as requested. Real cloud upload, operational scheduled execution and off-computer recovery are therefore blocked, not passed. No credential values are reproduced here.

## Pending-work follow-up

Real native dump/restore and confirmation/password refusal checks were repeated successfully in pending-complete-mysql.log after the new modules. Operational destination/Drive/scheduler status remains as above.


# Defect register

Final evidence: 218 passed (3566 assertions); see pending-complete-mysql.log. Earlier failures remain in their original evidence logs and were not hidden.

| ID | Severity | Finding | Current state |
|---|---|---|---|
| D12-01 | Medium | Discounted refund cent rounding | Fixed and regression-tested. |
| D12-02 | Low | MySQL invoice rollback fault-injection coverage | Portable model-event injection passes. |
| G12-01 | High | Missing inventory issue/receiving/adjustment/count operations | Implemented and tested; full unused-issue return supported. |
| G12-02 | High | Missing suppliers/purchases/GRNs/settlement | Implemented and tested, including opening settlement and return credits. |
| D12-03 | High | Approved quotation did not transfer job selling totals | Fixed; receipt-to-paid-invoice test uses real service calls, without manually setting job prices. |
| D12-04 | Medium | Completion/delivery statuses could leave timestamps empty | Fixed; timestamp assertions pass. |
| D12-05 | Medium | Older returned stock could replace newer purchase cost | Fixed with owner-approved latest-purchase source selection. |
| D12-06 | Medium | Accounting payment reversal reduced reported sales | Fixed; collections change while issued sale remains intact. |
| G12-04 | Medium | Intake PDF gaps, fonts, statement title/paper propagation | Implemented and tested; short fixture PDFs visually reviewed. Physical acceptance pending. |
| G12-03 | High | Operational backup destination/cloud account unset | Awaiting approved private folder; Drive remains blank by request. No host scheduler activated. |
| G12-05 | Medium | Purchase-wide cost allocation / ambiguous financial treatment | Latest purchase method approved; freight/discount allocation still awaits answer. Affected profit stays incomplete. |
| G12-06 | Medium | Reception/technician/repair-POS/GRN concurrency | Two-worker native cases pass. Realistic-volume capacity remains unmeasured. |
| G12-07 | Medium | Physical printing/scanning and business sign-off | External UAT pending; no signatures fabricated. |
| L12-01 | Low | Partial return of one repair issue / repeated-part historical purchase return | Full unused repair issue can be reversed; ambiguous repeated-part purchase return is refused pending source reconciliation. |


# User acceptance test cases

Tester for automated evidence: Codex local test runner, 2026-10-20. Business tester/signature: pending. Automation does not substitute for business acceptance. Execute manual cases only in an approved isolated/UAT database.

## UAT-01: Register individual and company

- BRD reference: 4.

- Preconditions: Receptionist with customer permission.

- Steps: Enter required contacts; save both types; search by ID/name/mobile.

- Expected: Unique IDs, validation and matching search results.

- Actual: Automated customer suite; manual sign-off pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `CustomerManagementTest`; final execution logs.

## UAT-02: Atomic receipt and job/card

- BRD reference: 5–6.

- Preconditions: Registered customer and intake role.

- Steps: Enter manual number, appliance, advance; save; repeat number; print A5/A6.

- Expected: One linked job/receipt, no duplicate advance; correct duplicate label.

- Actual: Automated intake/advance assertions; physical output pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `RepairReceiptAndJobManagementTest; PaymentManagementTest`; final execution logs.

## UAT-03: Inspection and quotation approval

- BRD reference: 6–8.

- Preconditions: Assigned technician and authorized decision recorder.

- Steps: Record inspection; draft/issue quote; approve; revise another quote.

- Expected: History appended; exact prices; no stock or payment created by approval.

- Actual: Automated quotation/inspection suite; manual acceptance pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `QuotationManagementTest`; final execution logs.

## UAT-04: Repair with approved part issue

- BRD reference: 12,20.

- Preconditions: Stock available; approved quotation.

- Steps: Approve part request; issue once; repeat request; complete job.

- Expected: Only issued approved quantity deducted once; auditable cost.

- Actual: Issue workflow missing.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `G12-01`; final execution logs.

## UAT-05: Multi-job invoice and payment

- BRD reference: 9–11.

- Preconditions: Two completed same-customer jobs; one advance.

- Steps: Issue invoice; partial cash/bank payment; collect balance; reverse authorized payment.

- Expected: No double billing/advance; total minus net payments; immutable original.

- Actual: Automated invoice/payment tests; manual acceptance pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `InvoiceManagementTest; PaymentManagementTest`; final execution logs.

## UAT-06: Delivery and reports

- BRD reference: 6,20.

- Preconditions: Paid repair ready for collection.

- Steps: Record collector/vehicle/date; deliver; filter reports and export.

- Expected: Correct delivery status/history and totals.

- Actual: Status/report tests exist; full workflow blocked by stock issue.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `RepairReceiptAndJobManagementTest; Step11ReportingTest`; final execution logs.

## UAT-07: POS sale and return

- BRD reference: 12,14,23.

- Preconditions: Cashier; manager; isolated stock.

- Steps: Scan; sell; retry checkout; return resalable/damaged parts; refund discounted sale.

- Expected: One sale/payment/movement; authorized exact refund; original retained.

- Actual: Automated POS and two-worker races; physical scanner/manual approval pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `POSManagementTest; verify-pos-concurrency.php`; final execution logs.

## UAT-08: Supplier purchase and partial receiving

- BRD reference: 13.

- Preconditions: Storekeeper/management scope agreed.

- Steps: Create supplier and draft purchase; partial GRN twice; pay partially; reverse payment.

- Expected: Draft no stock; each GRN posts once; correct supplier balance.

- Actual: Supplier/GRN/settlement services missing.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `G12-02`; final execution logs.

## UAT-09: Backup and disposable recovery

- BRD reference: 17.

- Preconditions: Approved destination and administrator.

- Steps: Create dump; verify hash; alter disposable probe; confirm/authenticate restore.

- Expected: Probe recovered, safety copy and audit present; no live DB change.

- Actual: Real automated disposable recovery executed; operational configuration pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `verify-step11-recovery.php`; final execution logs.

## UAT-10: Permissions and concurrent users

- BRD reference: 14,17,23.

- Preconditions: All six test-role users.

- Steps: Try allowed/direct denied URLs; run two cashiers on same stock/invoice.

- Expected: No unauthorized access, duplicate posting or overpayment.

- Actual: Role tests and selected native races; whole-shop manual pass pending.

- Status: Blocked for business acceptance.

- Tester: automated runner where cited; business tester pending.

- Date: 2026-10-20 audit; manual date pending.

- Evidence: `EmployeeManagementTest; verify-step12-concurrency.php`; final execution logs.

## Pending-work follow-up

The earlier descriptions of missing repair stock issue and supplier/GRN services are historical and are superseded. Automated workflow cases now pass in StockPurchasingWorkflowTest (including pricing transfer and customer advances), LatestPurchaseCostTest and PrintGapCompletionTest. Native two-user races pass in verify-pending-concurrency.php. Manual UAT statuses remain blocked/pending owner/hardware participation; they were not converted into signed business approval by automation.


# Final BRD compliance report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

The missing stock, supplier and purchase software workflows are now implemented and verified. Customer/repair/quotation/billing/POS flows integrate with the existing inventory and financial ledgers. Receipt/card, supplier/GRN and statement printing gaps have concrete PDF and history evidence. Latest purchase cost is owner-approved and applied to new transaction snapshots and valuation; existing records are preserved.

The matrix retains every original requirement ID across all 27 BRD sections. Missing operational-module rows now reference their workflow tests. No 100% compliance claim is made: owner UAT, physical devices, approved operational backup storage and unresolved purchase-cost allocation remain external limitations. Optional recommended features and excluded deployment/commercial items retain their explicit statuses.

Database audit: same schema/ERD, native constraint coverage and read-only before/after checks; no operational migrations. Financial/stock accuracy: fixture assertions plus native races, not certification of real accounts. Security: inherited route protections plus service authorization, retained audit history and existing authentication/CSRF/rate-limit tests. Performance: actual bounded local timings; no capacity claim. Printing: rendered fixture PDFs reviewed; no physical test. Backup: real disposable recovery passes; live/cloud configuration remains incomplete. UAT cases are prepared; business owner approval has not been fabricated.

See pending-completion-report.md for implemented functions and evidence, defect-register.md for remaining limitations, and modified-files.md for the full follow-up file list. Stop after this local completion work; no deployment/build/installer was run.
An approved live-backup folder has not been supplied; Google Drive folder/account/OAuth fields remain blank as requested. No host/production scheduler, production build, installer, packaging or deployment was performed. Physical printer/scanner checks, realistic-volume capacity acceptance and owner signatures remain pending. Latest purchase cost was explicitly selected by the owner. Purchase-wide discount/freight allocation remains a separate unanswered decision; affected profit estimates remain incomplete rather than assuming a policy. Existing role permissions were preserved: storekeepers can receive/issue/count/adjust, while purchase approval and supplier money operations require `purchases.approve`.
