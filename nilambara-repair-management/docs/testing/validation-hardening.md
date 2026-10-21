# Form validation hardening

Implemented shared phone validation for customer, employee and supplier create/update forms. Local and international display formats are accepted with 7?15 actual digits; this is format validation, not verification that a telephone number exists.

Shared exact decimal validation checks currency inputs against DECIMAL(19,2) using Brick Math (already installed), avoiding floating-point comparisons at the column boundary. Applied to customer opening balances, quotations, payments/reversals, receipt advances/labour, repair costs and POS amounts. Existing positivity, permission and financial workflow checks remain in force. Customer registration dates cannot be in the future. Stock request quantities are bounded.

Receipt customer/receiver, employee role and technician identifiers must be scalar integers. Payment and technician post-validation callbacks stop when basic validation fails, so malformed identifiers return validation errors rather than entering relational queries.

The application layout links server errors to matching fields, marks invalid fields, focuses the first available invalid field, reuses existing inline messages and supports nested array field names. Browser-native errors receive the same highlighting. Supplier forms now use email, telephone and numeric input types with appropriate limits.

Regression coverage: `tests/Feature/ValidationHardeningTest.php`, plus the existing affected workflow suites. Tests use the isolated SQLite in-memory database configured in phpunit.xml. Operational records are not changed by this work. Browser feedback checks use synthetic fields and cover focus, error association, message reuse, nested names, HTML escaping and native constraints.

No additional dependency or database migration is required.

Verification: 97 affected workflow tests passed (844 assertions). After adding the final receipt-ID case, the five validation regression tests passed again (64 assertions). Chrome feedback checks and PHP formatting/lint checks passed.
