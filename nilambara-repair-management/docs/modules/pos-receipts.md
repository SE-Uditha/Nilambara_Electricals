# POS receipts

Cheque receipts explicitly state that bank clearance is not confirmed. Tests generate thermal/A4/A5 PDF responses and verify that catalog changes do not alter sold descriptions or totals. Physical scanner and printer testing requires shop hardware.

Finalized POS sales can be printed in the configured receipt layout or exported to PDF. Available paper choices are thermal, A4, and A5. The receipt uses the sale snapshot and active POS print-template settings, including configured business details, logo, font size, width, and footer.

Every print/export is recorded in `document_print_histories` and the business audit log. An explicit reprint is marked as a duplicate; a later direct print is also marked as a duplicate when a prior print record exists. Pending sales cannot be printed as finalized sales receipts.

Payment receipts remain available from payment history and include the associated POS sale number. See the [payment guide](pos-payments.md) for the shared payment receipt behavior.
