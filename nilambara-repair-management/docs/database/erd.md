# Entity relationship diagram

The full diagram includes every new entity, its primary/foreign-key columns, and every declared foreign-key relationship. Composite FK labels list the complete child tuple. Non-key fields, CHECKs, indexes, optionality details, and accounting meaning are in the [data dictionary](data-dictionary.md).

Existing Laravel infrastructure is retained; only users is referenced by the business schema. Audit entity_type/entity_id deliberately records historical polymorphic identity without pretending to have a target foreign key.

```mermaid
erDiagram
    cache {
        string key PK
    }
    cache_locks {
        string key PK
    }
    failed_jobs {
        bigint id PK
    }
    jobs {
        bigint id PK
    }
    job_batches {
        string id PK
    }
    migrations {
        bigint id PK
    }
    password_reset_tokens {
        string email PK
    }
    sessions {
        string id PK
    }
    users {
        bigint id PK
    }
    roles {
        bigint id PK
        string code UK
    }
    permissions {
        bigint id PK
        string code UK
    }
    role_user {
        bigint id PK
        bigint user_id FK
        bigint role_id FK
    }
    permission_role {
        bigint id PK
        bigint role_id FK
        bigint permission_id FK
    }
    permission_user {
        bigint id PK
        bigint user_id FK
        bigint permission_id FK
    }
    employees {
        bigint id PK
        string employee_code UK
        bigint user_id FK, UK
    }
    customers {
        bigint id PK
        string customer_code UK
    }
    suppliers {
        bigint id PK
        string supplier_code UK
    }
    spare_part_categories {
        bigint id PK
        string name UK
    }
    spare_part_brands {
        bigint id PK
        string name UK
    }
    units_of_measure {
        bigint id PK
        string code UK
    }
    stock_locations {
        bigint id PK
        string code UK
    }
    spare_parts {
        bigint id PK
        string sku UK
        string barcode UK
        bigint brand_id FK
        bigint category_id FK
        bigint unit_id FK
        bigint supplier_id FK
        bigint default_location_id FK
    }
    document_sequences {
        bigint id PK
        string scope UK
    }
    print_templates {
        bigint id PK
    }
    system_settings {
        bigint id PK
        bigint updated_by_user_id FK
    }
    backup_histories {
        bigint id PK
        bigint initiated_by_user_id FK
    }
    backup_restores {
        bigint id PK
        bigint backup_history_id FK
        bigint initiated_by_user_id FK
    }
    audit_logs {
        bigint id PK
        bigint user_id FK
    }
    repair_jobs {
        bigint id PK
        string job_number UK
        string automatic_number UK
        bigint customer_id FK
        bigint received_by_employee_id FK
        bigint technician_id FK
        bigint cancelled_by_user_id FK
    }
    repair_receipts {
        bigint id PK
        bigint repair_job_id FK, UK
        bigint customer_id FK
        string receipt_number UK
    }
    job_status_histories {
        bigint id PK
        bigint repair_job_id FK
        bigint changed_by_user_id FK
    }
    job_inspections {
        bigint id PK
        bigint repair_job_id FK
        bigint inspected_by_employee_id FK
        bigint repaired_by_employee_id FK
    }
    job_cards {
        bigint id PK
        bigint repair_job_id FK, UK
        bigint print_template_id FK
    }
    job_approvals {
        bigint id PK
        bigint repair_job_id FK
        bigint recorded_by_user_id FK
    }
    quotations {
        bigint id PK
        string quotation_number UK
        bigint revises_quotation_id FK, UK
        bigint repair_job_id FK
        bigint customer_id FK
        bigint created_by_user_id FK
    }
    quotation_items {
        bigint id PK
        bigint quotation_id FK
        bigint spare_part_id FK
    }
    quotation_approvals {
        bigint id PK
        bigint quotation_id FK
        bigint recorded_by_user_id FK
    }
    purchases {
        bigint id PK
        string purchase_number UK
        bigint supplier_id FK
        bigint created_by_user_id FK
    }
    purchase_items {
        bigint id PK
        bigint purchase_id FK
        bigint spare_part_id FK
    }
    goods_received_notes {
        bigint id PK
        string grn_number UK
        bigint purchase_id FK
        bigint supplier_id FK
        bigint received_by_employee_id FK
    }
    goods_received_note_items {
        bigint id PK
        bigint goods_received_note_id FK
        bigint purchase_id FK
        bigint purchase_item_id FK
        bigint spare_part_id FK
        bigint location_id FK
    }
    invoices {
        bigint id PK
        string invoice_number UK
        bigint customer_id FK
        bigint cashier_employee_id FK
        bigint cancelled_by_user_id FK
    }
    invoice_jobs {
        bigint id PK
        bigint invoice_id FK
        bigint repair_job_id FK, UK
        bigint customer_id FK
    }
    invoice_items {
        bigint id PK
        bigint invoice_id FK
        bigint invoice_job_id FK
        bigint quotation_item_id FK
        bigint spare_part_id FK
    }
    pos_sales {
        bigint id PK
        string sale_number UK
        bigint customer_id FK
        bigint cashier_employee_id FK
        bigint customer_scope_id
    }
    pos_sale_items {
        bigint id PK
        bigint pos_sale_id FK
        bigint spare_part_id FK
        bigint location_id FK
    }
    payments {
        bigint id PK
        string payment_number UK
        bigint customer_id FK
        bigint received_by_employee_id FK
        string idempotency_key UK
        bigint customer_scope_id
    }
    payment_allocations {
        bigint id PK
        bigint payment_id FK
        bigint customer_id FK
        bigint repair_job_id FK
        bigint invoice_id FK
        bigint pos_sale_id FK
        bigint allocated_by_user_id FK
        string idempotency_key UK
        bigint customer_scope_id FK
    }
    payment_reversals {
        bigint id PK
        bigint payment_id FK
        bigint customer_id FK
        bigint reversed_by_user_id FK
        string idempotency_key UK
        bigint customer_scope_id FK
    }
    payment_allocation_releases {
        bigint id PK
        bigint payment_allocation_id FK
        bigint payment_id FK
        bigint payment_reversal_id FK
        bigint released_by_user_id FK
        string idempotency_key UK
    }
    supplier_payments {
        bigint id PK
        string payment_number UK
        bigint supplier_id FK
        bigint paid_by_user_id FK
        string idempotency_key UK
    }
    supplier_payment_allocations {
        bigint id PK
        bigint supplier_payment_id FK
        bigint supplier_id FK
        bigint purchase_id FK
        bigint allocated_by_user_id FK
        string idempotency_key UK
    }
    supplier_payment_reversals {
        bigint id PK
        bigint supplier_payment_id FK
        bigint supplier_id FK
        bigint reversed_by_user_id FK
        string idempotency_key UK
    }
    supplier_allocation_releases {
        bigint id PK
        bigint supplier_payment_allocation_id FK
        bigint supplier_payment_id FK
        bigint supplier_payment_reversal_id FK
        bigint released_by_user_id FK
        string idempotency_key UK
    }
    customer_account_entries {
        bigint id PK
        bigint customer_id FK
        bigint recorded_by_user_id FK
        bigint invoice_id FK, UK
        bigint pos_sale_id FK, UK
        bigint payment_id FK, UK
        bigint payment_reversal_id FK, UK
        bigint reverses_entry_id FK, UK
        string idempotency_key UK
        bigint opening_owner_id UK
    }
    supplier_account_entries {
        bigint id PK
        bigint supplier_id FK
        bigint recorded_by_user_id FK
        bigint purchase_id FK, UK
        bigint supplier_payment_id FK, UK
        bigint supplier_payment_reversal_id FK, UK
        bigint reverses_entry_id FK, UK
        string idempotency_key UK
        bigint opening_owner_id UK
    }
    job_part_usages {
        bigint id PK
        bigint repair_job_id FK
        bigint spare_part_id FK
        bigint location_id FK
        bigint requested_by_user_id FK
        bigint approved_by_user_id FK
        string idempotency_key UK
    }
    stock_adjustments {
        bigint id PK
        string adjustment_number UK
        bigint created_by_user_id FK
        bigint approved_by_user_id FK
    }
    stock_adjustment_items {
        bigint id PK
        bigint stock_adjustment_id FK
        bigint spare_part_id FK
        bigint location_id FK
    }
    stock_counts {
        bigint id PK
        string count_number UK
        bigint counted_by_user_id FK
    }
    stock_count_items {
        bigint id PK
        bigint stock_count_id FK
        bigint spare_part_id FK
        bigint location_id FK
    }
    stock_returns {
        bigint id PK
        string return_number UK
        bigint repair_job_id FK
        bigint pos_sale_id FK
        bigint purchase_id FK
        bigint recorded_by_user_id FK
    }
    stock_return_items {
        bigint id PK
        bigint stock_return_id FK
        bigint spare_part_id FK
        bigint location_id FK
    }
    stock_movements {
        bigint id PK
        bigint spare_part_id FK
        bigint location_id FK
        bigint recorded_by_user_id FK
        string idempotency_key UK
        bigint grn_item_id FK, UK
        bigint job_part_usage_id FK, UK
        bigint pos_sale_item_id FK, UK
        bigint return_item_id FK, UK
        bigint adjustment_item_id FK, UK
        bigint count_item_id FK, UK
        bigint reverses_movement_id FK
    }
    expense_categories {
        bigint id PK
        string name UK
    }
    expenses {
        bigint id PK
        string expense_number UK
        bigint expense_category_id FK
        bigint recorded_by_user_id FK
    }
    document_print_histories {
        bigint id PK
        bigint print_template_id FK
        bigint repair_receipt_id FK
        bigint job_card_id FK
        bigint quotation_id FK
        bigint invoice_id FK
        bigint pos_sale_id FK
        bigint payment_id FK
        bigint customer_id FK
        bigint purchase_id FK
        bigint printed_by_user_id FK
    }
    users ||--o{ role_user : "user_id"
    roles ||--o{ role_user : "role_id"
    roles ||--o{ permission_role : "role_id"
    permissions ||--o{ permission_role : "permission_id"
    users ||--o{ permission_user : "user_id"
    permissions ||--o{ permission_user : "permission_id"
    users o|--o| employees : "user_id"
    spare_part_brands o|--o{ spare_parts : "brand_id"
    spare_part_categories o|--o{ spare_parts : "category_id"
    units_of_measure ||--o{ spare_parts : "unit_id"
    suppliers o|--o{ spare_parts : "supplier_id"
    stock_locations o|--o{ spare_parts : "default_location_id"
    users o|--o{ system_settings : "updated_by_user_id"
    users o|--o{ backup_histories : "initiated_by_user_id"
    backup_histories ||--o{ backup_restores : "backup_history_id"
    users ||--o{ backup_restores : "initiated_by_user_id"
    users o|--o{ audit_logs : "user_id"
    customers ||--o{ repair_jobs : "customer_id"
    employees ||--o{ repair_jobs : "received_by_employee_id"
    employees o|--o{ repair_jobs : "technician_id"
    users o|--o{ repair_jobs : "cancelled_by_user_id"
    repair_jobs ||--o{ repair_receipts : "repair_job_id,customer_id"
    repair_jobs ||--o| repair_receipts : "repair_job_id"
    customers ||--o{ repair_receipts : "customer_id"
    repair_jobs ||--o{ job_status_histories : "repair_job_id"
    users ||--o{ job_status_histories : "changed_by_user_id"
    repair_jobs ||--o{ job_inspections : "repair_job_id"
    employees ||--o{ job_inspections : "inspected_by_employee_id"
    employees o|--o{ job_inspections : "repaired_by_employee_id"
    repair_jobs ||--o| job_cards : "repair_job_id"
    print_templates o|--o{ job_cards : "print_template_id"
    repair_jobs ||--o{ job_approvals : "repair_job_id"
    users ||--o{ job_approvals : "recorded_by_user_id"
    repair_jobs ||--o{ quotations : "repair_job_id,customer_id"
    quotations o|--o{ quotations : "revises_quotation_id,repair_job_id,customer_id"
    quotations o|--o| quotations : "revises_quotation_id"
    repair_jobs ||--o{ quotations : "repair_job_id"
    customers ||--o{ quotations : "customer_id"
    users ||--o{ quotations : "created_by_user_id"
    quotations ||--o{ quotation_items : "quotation_id"
    spare_parts o|--o{ quotation_items : "spare_part_id"
    quotations ||--o{ quotation_approvals : "quotation_id"
    users ||--o{ quotation_approvals : "recorded_by_user_id"
    suppliers ||--o{ purchases : "supplier_id"
    users ||--o{ purchases : "created_by_user_id"
    purchases ||--o{ purchase_items : "purchase_id"
    spare_parts ||--o{ purchase_items : "spare_part_id"
    purchases ||--o{ goods_received_notes : "purchase_id,supplier_id"
    purchases ||--o{ goods_received_notes : "purchase_id"
    suppliers ||--o{ goods_received_notes : "supplier_id"
    employees ||--o{ goods_received_notes : "received_by_employee_id"
    goods_received_notes ||--o{ goods_received_note_items : "goods_received_note_id,purchase_id"
    purchase_items ||--o{ goods_received_note_items : "purchase_item_id,purchase_id"
    purchase_items ||--o{ goods_received_note_items : "purchase_item_id,spare_part_id"
    goods_received_notes ||--o{ goods_received_note_items : "goods_received_note_id"
    purchases ||--o{ goods_received_note_items : "purchase_id"
    purchase_items ||--o{ goods_received_note_items : "purchase_item_id"
    spare_parts ||--o{ goods_received_note_items : "spare_part_id"
    stock_locations ||--o{ goods_received_note_items : "location_id"
    customers ||--o{ invoices : "customer_id"
    employees ||--o{ invoices : "cashier_employee_id"
    users o|--o{ invoices : "cancelled_by_user_id"
    invoices ||--o{ invoice_jobs : "invoice_id,customer_id"
    repair_jobs ||--o{ invoice_jobs : "repair_job_id,customer_id"
    invoices ||--o{ invoice_jobs : "invoice_id"
    repair_jobs ||--o| invoice_jobs : "repair_job_id"
    customers ||--o{ invoice_jobs : "customer_id"
    invoice_jobs ||--o{ invoice_items : "invoice_job_id,invoice_id"
    invoices ||--o{ invoice_items : "invoice_id"
    invoice_jobs ||--o{ invoice_items : "invoice_job_id"
    quotation_items o|--o{ invoice_items : "quotation_item_id"
    spare_parts o|--o{ invoice_items : "spare_part_id"
    customers o|--o{ pos_sales : "customer_id"
    employees ||--o{ pos_sales : "cashier_employee_id"
    pos_sales ||--o{ pos_sale_items : "pos_sale_id"
    spare_parts ||--o{ pos_sale_items : "spare_part_id"
    stock_locations ||--o{ pos_sale_items : "location_id"
    customers o|--o{ payments : "customer_id"
    employees ||--o{ payments : "received_by_employee_id"
    payments o|--o{ payment_allocations : "payment_id,customer_id"
    repair_jobs o|--o{ payment_allocations : "repair_job_id,customer_id"
    invoices o|--o{ payment_allocations : "invoice_id,customer_id"
    pos_sales o|--o{ payment_allocations : "pos_sale_id,customer_id"
    payments ||--o{ payment_allocations : "payment_id,customer_scope_id"
    pos_sales o|--o{ payment_allocations : "pos_sale_id,customer_scope_id"
    payments ||--o{ payment_allocations : "payment_id"
    customers o|--o{ payment_allocations : "customer_id"
    repair_jobs o|--o{ payment_allocations : "repair_job_id"
    invoices o|--o{ payment_allocations : "invoice_id"
    pos_sales o|--o{ payment_allocations : "pos_sale_id"
    users ||--o{ payment_allocations : "allocated_by_user_id"
    payments ||--o{ payment_reversals : "payment_id,customer_scope_id"
    payments ||--o{ payment_reversals : "payment_id"
    customers o|--o{ payment_reversals : "customer_id"
    users ||--o{ payment_reversals : "reversed_by_user_id"
    payment_allocations ||--o{ payment_allocation_releases : "payment_allocation_id,payment_id"
    payment_reversals o|--o{ payment_allocation_releases : "payment_reversal_id,payment_id"
    payment_allocations ||--o{ payment_allocation_releases : "payment_allocation_id"
    payments ||--o{ payment_allocation_releases : "payment_id"
    payment_reversals o|--o{ payment_allocation_releases : "payment_reversal_id"
    users ||--o{ payment_allocation_releases : "released_by_user_id"
    suppliers ||--o{ supplier_payments : "supplier_id"
    users ||--o{ supplier_payments : "paid_by_user_id"
    supplier_payments ||--o{ supplier_payment_allocations : "supplier_payment_id,supplier_id"
    purchases o|--o{ supplier_payment_allocations : "purchase_id,supplier_id"
    supplier_payments ||--o{ supplier_payment_allocations : "supplier_payment_id"
    suppliers ||--o{ supplier_payment_allocations : "supplier_id"
    purchases o|--o{ supplier_payment_allocations : "purchase_id"
    users ||--o{ supplier_payment_allocations : "allocated_by_user_id"
    supplier_payments ||--o{ supplier_payment_reversals : "supplier_payment_id,supplier_id"
    supplier_payments ||--o{ supplier_payment_reversals : "supplier_payment_id"
    suppliers ||--o{ supplier_payment_reversals : "supplier_id"
    users ||--o{ supplier_payment_reversals : "reversed_by_user_id"
    supplier_payment_allocations ||--o{ supplier_allocation_releases : "supplier_payment_allocation_id,supplier_payment_id"
    supplier_payment_reversals o|--o{ supplier_allocation_releases : "supplier_payment_reversal_id,supplier_payment_id"
    supplier_payment_allocations ||--o{ supplier_allocation_releases : "supplier_payment_allocation_id"
    supplier_payments ||--o{ supplier_allocation_releases : "supplier_payment_id"
    supplier_payment_reversals o|--o{ supplier_allocation_releases : "supplier_payment_reversal_id"
    users ||--o{ supplier_allocation_releases : "released_by_user_id"
    payment_reversals o|--o{ customer_account_entries : "payment_reversal_id,customer_id"
    invoices o|--o{ customer_account_entries : "invoice_id,customer_id"
    pos_sales o|--o{ customer_account_entries : "pos_sale_id,customer_id"
    payments o|--o{ customer_account_entries : "payment_id,customer_id"
    customer_account_entries o|--o{ customer_account_entries : "reverses_entry_id,customer_id"
    customers ||--o{ customer_account_entries : "customer_id"
    users ||--o{ customer_account_entries : "recorded_by_user_id"
    invoices o|--o| customer_account_entries : "invoice_id"
    pos_sales o|--o| customer_account_entries : "pos_sale_id"
    payments o|--o| customer_account_entries : "payment_id"
    payment_reversals o|--o| customer_account_entries : "payment_reversal_id"
    customer_account_entries o|--o| customer_account_entries : "reverses_entry_id"
    supplier_payment_reversals o|--o{ supplier_account_entries : "supplier_payment_reversal_id,supplier_id"
    purchases o|--o{ supplier_account_entries : "purchase_id,supplier_id"
    supplier_payments o|--o{ supplier_account_entries : "supplier_payment_id,supplier_id"
    supplier_account_entries o|--o{ supplier_account_entries : "reverses_entry_id,supplier_id"
    suppliers ||--o{ supplier_account_entries : "supplier_id"
    users ||--o{ supplier_account_entries : "recorded_by_user_id"
    purchases o|--o| supplier_account_entries : "purchase_id"
    supplier_payments o|--o| supplier_account_entries : "supplier_payment_id"
    supplier_payment_reversals o|--o| supplier_account_entries : "supplier_payment_reversal_id"
    supplier_account_entries o|--o| supplier_account_entries : "reverses_entry_id"
    repair_jobs ||--o{ job_part_usages : "repair_job_id"
    spare_parts ||--o{ job_part_usages : "spare_part_id"
    stock_locations ||--o{ job_part_usages : "location_id"
    users ||--o{ job_part_usages : "requested_by_user_id"
    users o|--o{ job_part_usages : "approved_by_user_id"
    users ||--o{ stock_adjustments : "created_by_user_id"
    users o|--o{ stock_adjustments : "approved_by_user_id"
    stock_adjustments ||--o{ stock_adjustment_items : "stock_adjustment_id"
    spare_parts ||--o{ stock_adjustment_items : "spare_part_id"
    stock_locations ||--o{ stock_adjustment_items : "location_id"
    users ||--o{ stock_counts : "counted_by_user_id"
    stock_counts ||--o{ stock_count_items : "stock_count_id"
    spare_parts ||--o{ stock_count_items : "spare_part_id"
    stock_locations ||--o{ stock_count_items : "location_id"
    repair_jobs o|--o{ stock_returns : "repair_job_id"
    pos_sales o|--o{ stock_returns : "pos_sale_id"
    purchases o|--o{ stock_returns : "purchase_id"
    users ||--o{ stock_returns : "recorded_by_user_id"
    stock_returns ||--o{ stock_return_items : "stock_return_id"
    spare_parts ||--o{ stock_return_items : "spare_part_id"
    stock_locations ||--o{ stock_return_items : "location_id"
    goods_received_note_items o|--o{ stock_movements : "grn_item_id,spare_part_id,location_id"
    job_part_usages o|--o{ stock_movements : "job_part_usage_id,spare_part_id,location_id"
    pos_sale_items o|--o{ stock_movements : "pos_sale_item_id,spare_part_id,location_id"
    stock_return_items o|--o{ stock_movements : "return_item_id,spare_part_id,location_id"
    stock_adjustment_items o|--o{ stock_movements : "adjustment_item_id,spare_part_id,location_id"
    stock_count_items o|--o{ stock_movements : "count_item_id,spare_part_id,location_id"
    stock_movements o|--o{ stock_movements : "reverses_movement_id,spare_part_id,location_id"
    spare_parts ||--o{ stock_movements : "spare_part_id"
    stock_locations ||--o{ stock_movements : "location_id"
    users ||--o{ stock_movements : "recorded_by_user_id"
    goods_received_note_items o|--o| stock_movements : "grn_item_id"
    job_part_usages o|--o| stock_movements : "job_part_usage_id"
    pos_sale_items o|--o| stock_movements : "pos_sale_item_id"
    stock_return_items o|--o| stock_movements : "return_item_id"
    stock_adjustment_items o|--o| stock_movements : "adjustment_item_id"
    stock_count_items o|--o| stock_movements : "count_item_id"
    stock_movements o|--o{ stock_movements : "reverses_movement_id"
    expense_categories o|--o{ expenses : "expense_category_id"
    users ||--o{ expenses : "recorded_by_user_id"
    print_templates o|--o{ document_print_histories : "print_template_id"
    repair_receipts o|--o{ document_print_histories : "repair_receipt_id"
    job_cards o|--o{ document_print_histories : "job_card_id"
    quotations o|--o{ document_print_histories : "quotation_id"
    invoices o|--o{ document_print_histories : "invoice_id"
    pos_sales o|--o{ document_print_histories : "pos_sale_id"
    payments o|--o{ document_print_histories : "payment_id"
    customers o|--o{ document_print_histories : "customer_id"
    purchases o|--o{ document_print_histories : "purchase_id"
    users ||--o{ document_print_histories : "printed_by_user_id"
```

No cascade deletion is used on the new foreign keys. Dashed or inferred reporting balances are not additional tables. Multi-row invariants and deferred policies are described in [relationship rules](relationship-rules.md).
