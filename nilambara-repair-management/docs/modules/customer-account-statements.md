# Customer account statements

The customer statement at `/customers/{customer}/statement` reads the existing `customer_account_entries` ledger. It includes the opening-balance entry made during customer registration, issued invoices, payments, refunds, and linked reversals. It never adds `customers.opening_balance` on top of its ledger entry.

The statement is ordered oldest-to-newest by `occurred_at`, then database ID. Date filters use the same chronology. Paginated rows show their running balance after all earlier customer ledger activity, including entries before the selected start date. The header distinguishes the registered opening balance, balance brought forward, and closing account balance. Invoice rows also show the invoice balance service's advance-applied total as explanatory metadata; it does not add another amount to the ledger.

`/customers/{customer}/statement/print` provides a print preview and `/pdf` exports the filtered statement. All three routes require `customers.statement`. Opening balances remain opening balances, not sales or new payment records. No statement line is fabricated from an invoice allocation that has no corresponding ledger transaction.
