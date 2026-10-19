# Customer management

Step 4 delivers individual and company customer registration, search/list/detail/edit, activation/deactivation, real-record history counts, and permission-protected account statements. It uses the existing `customers` table and `Customer` model; there is no duplicate customer system or customer-table migration.

## Registration and customer IDs

The `customer_type` is stored as `individual` or `company`. Individuals require `customer_name`; companies require `company_name` and may specify a `contact_person`. Address and mobile number are required. Alternative telephone, email, contact person, and special notes are optional. Telephone values remain strings, preserving leading zeroes and international prefixes.

Customer codes are generated as a configurable prefix followed by an uppercase ULID, for example `CUS-01K...`. Configure the prefix with `BUSINESS_CUSTOMER_CODE_PREFIX` (default `CUS-`). ULIDs avoid `MAX(id) + 1` races; the existing unique `customer_code` index is the final duplicate guard. Codes cannot be set from registration input or changed through the customer model.

Opening balance is a decimal amount entered only by users with `customers.statement`. A nonzero initial value creates one immutable `opening` entry in `customer_account_entries` in the same database transaction as the customer. Zero does not create a ledger row. Direct edits to opening balance are excluded from the update request and blocked by the model; a future ledger-adjustment workflow must handle corrections.

## Search, selection, and history

`GET /customers` supports partial search across customer code, individual name, company name, and mobile number, optional type/status filters, and 20-record database pagination. `GET /customers/search?q=...` returns at most 10 active customers for reuse in future transaction forms. The `<x-customer-search>` component can be embedded in those forms; inactive customers are excluded to prevent selection for new transactions.

Customer details show actual related repair-job counts. Invoice/payment counts and the ledger balance are loaded only for users allowed `customers.statement`. The read-only statement paginates the actual ledger entries. No values are inferred from customer records or fabricated when there is no history. Repair intake, invoice creation, payment processing, and future job-level history lists are outside Step 4.

## Permissions and lifecycle

- `customers.view`: list, details, and active-customer search.
- `customers.create`: registration.
- `customers.update`: contact-detail editing and activation.
- `customers.deactivate`: deactivation, with an explicit confirmation.
- `customers.statement`: opening-balance entry/view, account-ledger statement, and financial history counts.

The default role grants are configured in `RoleSeeder`: Receptionist can view/register/update customers; Cashier can view/search; Manager can view the financial statement; Administrator has every permission. Technician and Storekeeper receive no unrestricted customer access. An administrator may customize grants and per-user overrides through the Step 3 role management.

Deactivation is a POST action. It retains the customer and every foreign-key-linked historical row. Inactive customers remain available in list/detail and are filtered out of the reusable new-transaction selector.

## Audit and database

Create, edit, activate, and deactivate actions write audit events within the same transaction as their change. Audit snapshots omit contact details and credentials; they retain the customer code, type/display name where relevant, opening balance at creation, and status transitions. The existing schema uses DECIMAL(19,2), an indexed unique customer code, name/company/mobile search indexes, InnoDB, and `utf8mb4`.

## Testing

`tests/Feature/CustomerManagementTest.php` covers individual/company intake, code uniqueness/format, validation, optional fields, one-time opening-ledger posting, search and active filtering, pagination, immutable IDs/opening balances, role enforcement, financial-data protection, activation/deactivation, and audit/history retention. Tests use the isolated SQLite test connection and do not modify configured customer data.
