# Step 2 file summary

Step 2 adds schema/model/relationship/reference-data/test/documentation files. The existing User model receives relationships only; its authentication behavior and password hashing remain intact. Existing Step 1 frontend/routes/authentication changes in the working tree are preserved. No business page, CRUD route, inventory transaction service, payment processor, or report is added.

## Files created or updated in this step

- `README.md`.
- `app/Enums/ApprovalStatus.php`.
- `app/Enums/CustomerType.php`.
- `app/Enums/DocumentStatus.php`.
- `app/Enums/JobStatus.php`.
- `app/Enums/PaymentMethod.php`.
- `app/Enums/PaymentStatus.php`.
- `app/Enums/PaymentTerms.php`.
- `app/Models/BusinessModel.php`.
- `app/Models/User.php`.
- `app/Modules/AuditLogs/Models/AuditLog.php`.
- `app/Modules/BackupRestore/Models/BackupHistory.php`.
- `app/Modules/BackupRestore/Models/BackupRestore.php`.
- `app/Modules/CreditManagement/Models/CustomerAccountEntry.php`.
- `app/Modules/Customers/Models/Customer.php`.
- `app/Modules/Employees/Models/Employee.php`.
- `app/Modules/Inventory/Models/JobPartUsage.php`.
- `app/Modules/Inventory/Models/SparePart.php`.
- `app/Modules/Inventory/Models/SparePartBrand.php`.
- `app/Modules/Inventory/Models/SparePartCategory.php`.
- `app/Modules/Inventory/Models/StockAdjustment.php`.
- `app/Modules/Inventory/Models/StockAdjustmentItem.php`.
- `app/Modules/Inventory/Models/StockCount.php`.
- `app/Modules/Inventory/Models/StockCountItem.php`.
- `app/Modules/Inventory/Models/StockLocation.php`.
- `app/Modules/Inventory/Models/StockMovement.php`.
- `app/Modules/Inventory/Models/StockReturn.php`.
- `app/Modules/Inventory/Models/StockReturnItem.php`.
- `app/Modules/Inventory/Models/UnitOfMeasure.php`.
- `app/Modules/Invoices/Models/Invoice.php`.
- `app/Modules/Invoices/Models/InvoiceItem.php`.
- `app/Modules/Invoices/Models/InvoiceJob.php`.
- `app/Modules/JobCards/Models/JobCard.php`.
- `app/Modules/JobTracking/Models/JobStatusHistory.php`.
- `app/Modules/POS/Models/PosSale.php`.
- `app/Modules/POS/Models/PosSaleItem.php`.
- `app/Modules/Payments/Models/Expense.php`.
- `app/Modules/Payments/Models/ExpenseCategory.php`.
- `app/Modules/Payments/Models/Payment.php`.
- `app/Modules/Payments/Models/PaymentAllocation.php`.
- `app/Modules/Payments/Models/PaymentAllocationRelease.php`.
- `app/Modules/Payments/Models/PaymentReversal.php`.
- `app/Modules/PrintTemplates/Models/DocumentPrintHistory.php`.
- `app/Modules/PrintTemplates/Models/PrintTemplate.php`.
- `app/Modules/Purchases/Models/GoodsReceivedNote.php`.
- `app/Modules/Purchases/Models/GoodsReceivedNoteItem.php`.
- `app/Modules/Purchases/Models/Purchase.php`.
- `app/Modules/Purchases/Models/PurchaseItem.php`.
- `app/Modules/Quotations/Models/Quotation.php`.
- `app/Modules/Quotations/Models/QuotationApproval.php`.
- `app/Modules/Quotations/Models/QuotationItem.php`.
- `app/Modules/RepairJobs/Models/JobApproval.php`.
- `app/Modules/RepairJobs/Models/JobInspection.php`.
- `app/Modules/RepairJobs/Models/RepairJob.php`.
- `app/Modules/RepairReceipts/Models/RepairReceipt.php`.
- `app/Modules/RolesPermissions/Models/Permission.php`.
- `app/Modules/RolesPermissions/Models/Role.php`.
- `app/Modules/RolesPermissions/Models/RolePermission.php`.
- `app/Modules/RolesPermissions/Models/UserPermission.php`.
- `app/Modules/RolesPermissions/Models/UserRole.php`.
- `app/Modules/Settings/Models/DocumentSequence.php`.
- `app/Modules/Settings/Models/SystemSetting.php`.
- `app/Modules/Suppliers/Models/Supplier.php`.
- `app/Modules/Suppliers/Models/SupplierAccountEntry.php`.
- `app/Modules/Suppliers/Models/SupplierAllocationRelease.php`.
- `app/Modules/Suppliers/Models/SupplierPayment.php`.
- `app/Modules/Suppliers/Models/SupplierPaymentAllocation.php`.
- `app/Modules/Suppliers/Models/SupplierPaymentReversal.php`.
- `app/Support/SensitiveData.php`.
- `config/numbering.php`.
- `database/migrations/2026_10_18_100001_create_access_tables.php`.
- `database/migrations/2026_10_18_100002_create_customers_suppliers_tables.php`.
- `database/migrations/2026_10_18_100003_create_inventory_reference_tables.php`.
- `database/migrations/2026_10_18_100004_create_system_support_tables.php`.
- `database/migrations/2026_10_18_100005_create_repair_tables.php`.
- `database/migrations/2026_10_18_100006_create_quotation_tables.php`.
- `database/migrations/2026_10_18_100007_create_purchasing_tables.php`.
- `database/migrations/2026_10_18_100008_create_sales_tables.php`.
- `database/migrations/2026_10_18_100009_create_customer_payment_tables.php`.
- `database/migrations/2026_10_18_100010_create_supplier_payment_tables.php`.
- `database/migrations/2026_10_18_100011_create_account_ledger_tables.php`.
- `database/migrations/2026_10_18_100012_create_inventory_operations_tables.php`.
- `database/migrations/2026_10_18_100013_create_stock_ledger_tables.php`.
- `database/migrations/2026_10_18_100014_create_expense_tables.php`.
- `database/migrations/2026_10_18_100015_create_document_print_history_tables.php`.
- `database/migrations/2026_10_19_100016_add_reporting_indexes_to_business_tables.php`.
- `database/seeders/RoleSeeder.php`.
- `docs/database/README.md`.
- `docs/database/change-summary.md`.
- `docs/database/data-dictionary.md`.
- `docs/database/erd.md`.
- `docs/database/open-decisions.md`.
- `docs/database/relationship-rules.md`.
- `docs/database/requirements-traceability.md`.
- `docs/database/schema.md`.
- `docs/database/verification.md`.
- `scripts/verify-mysql.php`.
- `tests/Feature/Database/BusinessConstraintsTest.php`.
- `tests/Feature/Database/BusinessRelationshipsTest.php`.
- `tests/Feature/Database/CreatesDatabaseRecords.php`.
- `tests/Feature/Database/MigrationLifecycleTest.php`.
- `tests/Feature/Database/SchemaIntegrityTest.php`.
- `tests/Unit/AuditSafetyTest.php`.

AuditLog and SystemSetting receive credential-safety mutators after initial model generation. Local tooling scripts/catalogs, environment credentials, metadata snapshots, and test logs remain ignored and are not runtime dependencies. Only six role names are seeded; no permission matrix, user role assignments, employees, customers, sales, or stock records are fabricated.

## Step 6 additive quotation extension

Migration `2026_10_19_100019_upgrade_quotation_workflow.php` extends the existing quotation, line-item, and approval tables with preparation state, issue/snapshot support, draft-line retention/display order, and decision method. It adds `quotation_history`; it does not replace or duplicate the existing quotation tables. Migration `2026_10_19_100020_backfill_quotation_preparation_status.php` maps legacy finalized/cancelled rows to the new preparation state. New PHP model: `app/Modules/Quotations/Models/QuotationHistory.php`. SQLite applies additive `ALTER TABLE` statements to avoid rebuilding the original composite-key quotation table; MySQL/MariaDB use the Laravel schema builder. See the [quotation data dictionary](data-dictionary.md#quotations) and [Step 6 summary](../development/step-6-summary.md).

## Step 7 additive invoice extension

Migration `2026_10_19_170000_add_invoice_workflow_support.php` adds nullable informational `payment_method` to the existing `invoices` table and creates append-only `invoice_history`; it does not replace invoice/job/item/payment tables or add payment-entry behavior. `InvoiceHistory` stores lifecycle, draft edit, and job-link evidence. See the [invoice data dictionary](data-dictionary.md#invoices), [Step 7 summary](../development/step-7-summary.md), and [invoice workflow guide](../modules/invoice-management.md).
