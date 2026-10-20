# Integration test report

The tested path is customer → atomic receipt/job/card → inspection/quotation → approval → completed fixture job → multi-job invoice → advance/payment allocation → credit balance → reports. A completed fixture is not evidence that physical repair work or inventory issue occurred.

PaymentManagementTest verifies an advance posts once and is not counted again on invoice issue; InvoiceManagementTest verifies cross-customer prevention, quote price reuse, immutable issued snapshots and transaction rollback. QuotationManagementTest verifies no payment/stock side effects. POSManagementTest verifies sale/payment/allocation/movement atomicity, returns and refunds.

Native POS concurrency uses two PHP/MySQL workers: competing stock, unique sale numbers and identical retries. Additional Step 12 native invoice and payment races have their actual results in `step12-mysql-step12.log`. Read the log before interpreting a race as passed. These checks do not establish a general multiuser capacity limit.

Blocked integrations: repair issue ↔ POS competition, purchase → GRN → inventory → supplier settlement, multiple concurrent GRNs and storekeeper issues. Two receptionist/technician races have not been executed. Source transactions alone are not concurrency evidence.
