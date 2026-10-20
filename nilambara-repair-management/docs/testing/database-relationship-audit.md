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

Financial, stock and history records use restrictive delete behavior tested in SchemaIntegrityTest; authentication/permission infrastructure has its own cascade rules. Polymorphic audit identities intentionally have no target FK. Models/relationships are tested separately; supplier/purchase relationships do not establish operational CRUD support.
