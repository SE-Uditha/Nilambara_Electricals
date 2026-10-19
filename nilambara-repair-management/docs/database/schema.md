# Database schema

The application database is `nilambara_repair_management`, accessed with Laravel's `mysql` connection and administered through phpMyAdmin. The current XAMPP server is MariaDB 10.4.32. MySQL 8.0.16+ is the MySQL deployment target because CHECK enforcement is required; a separate MySQL 8 server has not been executed here.

Step 2 adds **58 tables**, **58 module models**, **7 enums**, and **16 additive migrations**. Existing authentication and queue/cache/session tables remain in place. There are 67 tables including the migration repository (66 application/infrastructure tables).

## Design and source of truth

Versioned Laravel migrations are the executable schema. phpMyAdmin is an administration interface over the same database, not a second database or an alternative table-creation mechanism. Do not manually create tables whose migrations already exist.

Native SQL is used inside standard Laravel Migration classes to express CHECKs, generated unique opening markers, and composite foreign keys consistently on SQLite and MySQL/MariaDB. No framework/vendor file, trigger, stored procedure, or schema-dump import is required. Every new table explicitly selects InnoDB and utf8mb4 on the production driver.

Money totals are DECIMAL(19,2); unit costs/prices are DECIMAL(19,4); source quantities are DECIMAL(18,3). Exact model decimal casts return strings. Fractional storage capacity is a proposed technical provision; it does not approve fractional quantities for every unit.

## Entity catalogue

| Table | Module | Eloquent model |
| --- | --- | --- |
| `roles` | RolesPermissions | [Role](../../app/Modules/RolesPermissions/Models/Role.php) |
| `permissions` | RolesPermissions | [Permission](../../app/Modules/RolesPermissions/Models/Permission.php) |
| `role_user` | RolesPermissions | [UserRole](../../app/Modules/RolesPermissions/Models/UserRole.php) |
| `permission_role` | RolesPermissions | [RolePermission](../../app/Modules/RolesPermissions/Models/RolePermission.php) |
| `permission_user` | RolesPermissions | [UserPermission](../../app/Modules/RolesPermissions/Models/UserPermission.php) |
| `employees` | Employees | [Employee](../../app/Modules/Employees/Models/Employee.php) |
| `customers` | Customers | [Customer](../../app/Modules/Customers/Models/Customer.php) |
| `suppliers` | Suppliers | [Supplier](../../app/Modules/Suppliers/Models/Supplier.php) |
| `spare_part_categories` | Inventory | [SparePartCategory](../../app/Modules/Inventory/Models/SparePartCategory.php) |
| `spare_part_brands` | Inventory | [SparePartBrand](../../app/Modules/Inventory/Models/SparePartBrand.php) |
| `units_of_measure` | Inventory | [UnitOfMeasure](../../app/Modules/Inventory/Models/UnitOfMeasure.php) |
| `stock_locations` | Inventory | [StockLocation](../../app/Modules/Inventory/Models/StockLocation.php) |
| `spare_parts` | Inventory | [SparePart](../../app/Modules/Inventory/Models/SparePart.php) |
| `document_sequences` | Settings | [DocumentSequence](../../app/Modules/Settings/Models/DocumentSequence.php) |
| `print_templates` | PrintTemplates | [PrintTemplate](../../app/Modules/PrintTemplates/Models/PrintTemplate.php) |
| `system_settings` | Settings | [SystemSetting](../../app/Modules/Settings/Models/SystemSetting.php) |
| `backup_histories` | BackupRestore | [BackupHistory](../../app/Modules/BackupRestore/Models/BackupHistory.php) |
| `backup_restores` | BackupRestore | [BackupRestore](../../app/Modules/BackupRestore/Models/BackupRestore.php) |
| `audit_logs` | AuditLogs | [AuditLog](../../app/Modules/AuditLogs/Models/AuditLog.php) |
| `repair_jobs` | RepairJobs | [RepairJob](../../app/Modules/RepairJobs/Models/RepairJob.php) |
| `repair_receipts` | RepairReceipts | [RepairReceipt](../../app/Modules/RepairReceipts/Models/RepairReceipt.php) |
| `job_status_histories` | JobTracking | [JobStatusHistory](../../app/Modules/JobTracking/Models/JobStatusHistory.php) |
| `job_inspections` | RepairJobs | [JobInspection](../../app/Modules/RepairJobs/Models/JobInspection.php) |
| `job_cards` | JobCards | [JobCard](../../app/Modules/JobCards/Models/JobCard.php) |
| `job_approvals` | RepairJobs | [JobApproval](../../app/Modules/RepairJobs/Models/JobApproval.php) |
| `quotations` | Quotations | [Quotation](../../app/Modules/Quotations/Models/Quotation.php) |
| `quotation_items` | Quotations | [QuotationItem](../../app/Modules/Quotations/Models/QuotationItem.php) |
| `quotation_approvals` | Quotations | [QuotationApproval](../../app/Modules/Quotations/Models/QuotationApproval.php) |
| `quotation_history` | Quotations | [QuotationHistory](../../app/Modules/Quotations/Models/QuotationHistory.php) |
| `purchases` | Purchases | [Purchase](../../app/Modules/Purchases/Models/Purchase.php) |
| `purchase_items` | Purchases | [PurchaseItem](../../app/Modules/Purchases/Models/PurchaseItem.php) |
| `goods_received_notes` | Purchases | [GoodsReceivedNote](../../app/Modules/Purchases/Models/GoodsReceivedNote.php) |
| `goods_received_note_items` | Purchases | [GoodsReceivedNoteItem](../../app/Modules/Purchases/Models/GoodsReceivedNoteItem.php) |
| `invoices` | Invoices | [Invoice](../../app/Modules/Invoices/Models/Invoice.php) |
| `invoice_jobs` | Invoices | [InvoiceJob](../../app/Modules/Invoices/Models/InvoiceJob.php) |
| `invoice_items` | Invoices | [InvoiceItem](../../app/Modules/Invoices/Models/InvoiceItem.php) |
| `pos_sales` | POS | [PosSale](../../app/Modules/POS/Models/PosSale.php) |
| `pos_sale_items` | POS | [PosSaleItem](../../app/Modules/POS/Models/PosSaleItem.php) |
| `payments` | Payments | [Payment](../../app/Modules/Payments/Models/Payment.php) |
| `payment_allocations` | Payments | [PaymentAllocation](../../app/Modules/Payments/Models/PaymentAllocation.php) |
| `payment_reversals` | Payments | [PaymentReversal](../../app/Modules/Payments/Models/PaymentReversal.php) |
| `payment_allocation_releases` | Payments | [PaymentAllocationRelease](../../app/Modules/Payments/Models/PaymentAllocationRelease.php) |
| `supplier_payments` | Suppliers | [SupplierPayment](../../app/Modules/Suppliers/Models/SupplierPayment.php) |
| `supplier_payment_allocations` | Suppliers | [SupplierPaymentAllocation](../../app/Modules/Suppliers/Models/SupplierPaymentAllocation.php) |
| `supplier_payment_reversals` | Suppliers | [SupplierPaymentReversal](../../app/Modules/Suppliers/Models/SupplierPaymentReversal.php) |
| `supplier_allocation_releases` | Suppliers | [SupplierAllocationRelease](../../app/Modules/Suppliers/Models/SupplierAllocationRelease.php) |
| `customer_account_entries` | CreditManagement | [CustomerAccountEntry](../../app/Modules/CreditManagement/Models/CustomerAccountEntry.php) |
| `supplier_account_entries` | Suppliers | [SupplierAccountEntry](../../app/Modules/Suppliers/Models/SupplierAccountEntry.php) |
| `job_part_usages` | Inventory | [JobPartUsage](../../app/Modules/Inventory/Models/JobPartUsage.php) |
| `stock_adjustments` | Inventory | [StockAdjustment](../../app/Modules/Inventory/Models/StockAdjustment.php) |
| `stock_adjustment_items` | Inventory | [StockAdjustmentItem](../../app/Modules/Inventory/Models/StockAdjustmentItem.php) |
| `stock_counts` | Inventory | [StockCount](../../app/Modules/Inventory/Models/StockCount.php) |
| `stock_count_items` | Inventory | [StockCountItem](../../app/Modules/Inventory/Models/StockCountItem.php) |
| `stock_returns` | Inventory | [StockReturn](../../app/Modules/Inventory/Models/StockReturn.php) |
| `stock_return_items` | Inventory | [StockReturnItem](../../app/Modules/Inventory/Models/StockReturnItem.php) |
| `stock_movements` | Inventory | [StockMovement](../../app/Modules/Inventory/Models/StockMovement.php) |
| `expense_categories` | Payments | [ExpenseCategory](../../app/Modules/Payments/Models/ExpenseCategory.php) |
| `expenses` | Payments | [Expense](../../app/Modules/Payments/Models/Expense.php) |
| `document_print_histories` | PrintTemplates | [DocumentPrintHistory](../../app/Modules/PrintTemplates/Models/DocumentPrintHistory.php) |

## Integrity and lifecycle

Step 6 adds the append-only quotation history table and additive workflow/snapshot fields to the existing quotation tables. See the Step 6 migration and [quotation guide](../modules/quotation-management.md) for the implemented behavior.

- Required starred BRD inputs use NOT NULL/conditional/nonblank CHECKs. Individual/company names remain distinct; receiving requires an item name, brand or model, and a registered receiving employee.
- Job identifiers, document numbers, SKU/barcode, source idempotency keys, and relevant composite pairs are unique. Ordinary business cancellation retains the original rows and their identifiers.
- invoice_jobs carries customer_id, references matching candidate keys on both invoice and repair job, and uniquely reserves each repair_job_id. This deliberately also prevents duplicate draft reservations until a rebilling/adjustment policy is approved.
- Customer scope keys close NULL-owner bypasses for anonymous POS allocations. Reversal ownership and release-to-payment ownership are checked through composite foreign keys.
- Stock movements share the parts catalogue, retain a typed source line, and match its part/location. Source-line unique constraints and operation keys prevent duplicate source posting.
- Financial records and source transactions use RESTRICT delete/update. Eloquent guards add immutable/append-only behavior, but raw privileged SQL is not protected by those guards.
- No independent current-stock or outstanding-balance cache is stored. Immutable ledgers and allocation releases are the authoritative calculation inputs.
- Aggregate limits, concurrent overselling prevention, finalized-source checks, header/line reconciliation, authorization, and transaction orchestration remain future service responsibilities. Step 2 does not claim they are implemented or tested.

## Documents and decisions

See [complete ERD](erd.md), [all-field dictionary](data-dictionary.md), [relationship rules](relationship-rules.md), [BRD traceability](requirements-traceability.md), [open decisions](open-decisions.md), [verification](verification.md), and [file summary](change-summary.md).
