# Relationship and integrity rules

## Scope and authority

These rules explain the Step 2 data foundation and the contracts future operations must follow. They do not implement CRUD, document generation, stock deduction, payment processing, reporting, audit listeners, permission evaluation, or backup/restore execution. The [BRD](../../../Repair%20Shop%20Management%20Final.pdf) references below use physical PDF page numbers. Exact column definitions and constraint names are maintained with the schema/data dictionary; unresolved policies are recorded in [open-decisions.md](open-decisions.md).

Distinguish three layers:

1. **Database integrity**: primary/foreign keys, unique keys, supported enum/check values, row-local nonblank/sign/amount constraints, and deletion restrictions.
2. **Model integrity**: decimal/date/boolean casts, Eloquent relationships, and explicit identifier/history immutability guards where provided. Direct SQL and query-builder updates can bypass model hooks.
3. **Future operation integrity**: locked database transactions, cross-row totals, paired postings, status history, approvals, role decisions, and concurrency controls. These are not established merely by migration success or relationship tests.

There are no triggers or implicit model observers that secretly perform financial or stock postings.

## Ownership and cardinality

| Relationship | Cardinality and rule |
| --- | --- |
| Customer to repair job | One customer has many jobs; each repair job belongs to one customer. Customer selection must be explicit. |
| Repair job to receiving receipt | One job has one receiving receipt; each receipt identifies exactly one job. A unique job reference prevents duplicate receiving receipts. Receipt/customer ownership must match the job. Future intake creates both records in one transaction; the one-to-one link alone does not force every raw job insertion to include a receipt. |
| Repair job to inspection | A job can have multiple inspection records. Each preserves the employee, date, result and technical details rather than overwriting earlier inspections. |
| Repair job to status history | A job has many status transitions; each records previous/new status, user and change time. Current status is a query convenience that must be reconciled to the latest valid history. |
| Repair job to parts usage | A job has many usage rows linked to the shared spare-part catalogue. Requested/approved/issued/returned facts must remain distinguishable from stock movements. Creating a usage draft does not itself deduct stock. |
| Repair job to quotation | A job can have multiple quotations/revisions. Composite foreign keys keep each quotation and its prior revision on the same job/customer; approval identifies the exact quotation/version. |
| Quotation to items and approvals | A quotation has many priced/described lines and approval/history records. Approved facts used downstream must remain traceable. |
| Invoice to repair jobs | One invoice may contain many same-customer jobs through `invoice_jobs`; each link preserves its job-wise amount. Current conservative uniqueness permits at most one invoice link per job across all invoice states. |
| Invoice to lines | An invoice has many line snapshots. Lines preserve printed item/work/price facts and applicable source references. They must not create a second stock issue for parts already issued to the repair job. |
| Payment to allocations | One payment may have multiple allocations. Each allocation has one valid typed target, such as an invoice or a job advance; a payment may also retain an unapplied amount. |
| Allocation to releases | An allocation may have release records that make some/all of it available for reapplication without deleting the original allocation. |
| Payment to reversals | A payment may have auditable full/partial reversals/refunds according to the eventual approved policy; preserve actor, time, amount, reason and source. |
| Customer to account entries | A customer has many canonical signed `customer_account_entries`, including opening balance and original-source/compensating postings. Outstanding balance is derived, not another independently writable account. |
| Spare part to movements | One part has many signed stock movements in the shared repair/POS stock ledger; each movement identifies date, reference, user, reason, idempotency and a valid source. |
| Supplier to purchases and payments | A supplier has many purchases and payments; supplier opening, purchase, settlement and reversal history feeds `supplier_account_entries`. |
| Purchase to purchase items | A purchase has many item lines; it may have multiple goods-received notes to support partial receipt. |
| Goods-received note to received items | A GRN has many received lines that identify the purchased part/line and quantity. A finalized goods receipt produces source-linked positive stock movements; a purchase draft does not. |
| POS sale to sale items | A sale has many lines using the same `spare_parts` records as repair parts. A customer link may be absent for a genuine walk-in sale; do not fabricate a customer to satisfy reporting. |
| Returns to original sources | `stock_returns` selects exactly one original job, POS sale or purchase according to its return type; `stock_return_items` identifies part/location/quantity/condition. `stock_movements.reverses_movement_id` can preserve original movement lineage. Original source-line/returned-quantity coherence and ceilings still need future validation. |
| Employee to Laravel user | An employee may link to one existing login account; preserve one unambiguous employee link per user. Employment and login identity are distinct. No employee password column duplicates Laravel credentials. |
| Users to roles/permissions | One custom RBAC model supports role membership, role permissions and per-user customization. Membership storage is not permission enforcement; Step 3 defines evaluation and precedence. |
| Templates and document history | Template settings/revisions and snapshots retain historical printable facts. A job card derives core facts from its job, inspections and parts instead of maintaining another job master. |

Where a transaction repeats an owner ID for referential checks, the redundant ID is an integrity key, not an independent source of ownership. Where a document snapshots a name/address/description, the copy preserves historical output rather than serving as a second editable customer or stock master.

## Same-customer invoice links and duplicate billing

The invoice/job rule is mandatory on BRD page 9 and in Step 2 section 9. A job and its invoice must have the same customer. The design uses an `invoice_jobs` customer key with composite references to both `(invoice, customer)` and `(repair job, customer)`, backed by the required parent unique keys. It must be impossible to link a job from customer B into customer A's invoice through a direct insert, not only through a form check.

One invoice can have several links; the unique job reference prevents a job from being billed by two invoices. This is stricter than finalized-only prevention: a draft invoice link reserves the job too. Cancellation/rebilling and link release require a later explicit adjustment design (OD03), rather than deletion of finalized history.

Each job's billed amount belongs to its invoice link/snapshot. `quotation_items`, `invoice_items` and `pos_sale_items` enforce `discount_amount <= quantity * unit_price` and `line_total = ROUND(quantity * unit_price - discount_amount, 2)`; `purchase_items` uses `unit_cost` in the same rule. Quantities are positive, prices/costs/discounts are nonnegative, and the stored line total uses two decimals. This is the proposed net-line rounding storage rule recorded in OD05, not an assertion that the BRD specified a final rounding policy.

Document headers also constrain their own subtotal/discount/total arithmetic, including purchase other costs. These row checks do not prove that a header subtotal equals the sum of all its lines, that separate labour charges have been included exactly once, or that job-wise invoice amounts reconcile. A future finalization transaction must validate line ownership, aggregate sums, discounts, approved rounding, prior billing and document state before committing.

## Customer and supplier account ledger

Use one authoritative account-entry source for each party balance: `customer_account_entries` and `supplier_account_entries`. These are separate canonical ledgers for the two account perspectives, not disconnected copies of one balance. Each entry identifies its party, signed `amount`, `occurred_at`, source/reference and `idempotency_key`. Amounts currently use configured LKR; no per-entry currency or FX columns are present.

The stored sign convention is **positive = outstanding in that account perspective**: customer positive means receivable from the customer; supplier positive means payable by the business to the supplier. These are two account perspectives, not a complete double-entry general ledger. Row checks require customer invoice/POS/refund entries and supplier purchase/refund entries to be positive; payment entries are negative on both sides. Opening, adjustment and reversal entries may have either nonzero sign. Correct compensation against the original amount, recognition and aggregate reconciliation still require future operation rules.

| Event | Customer account effect | Supplier account effect |
| --- | --- | --- |
| Opening balance | Opening receivable/credit entry with explicit sign; excluded from sales. | Opening payable/credit entry with explicit sign; excluded from purchases. |
| Posted invoice / customer-account POS sale | Increase the customer receivable once from the final source document. | Not applicable. |
| Money received from customer | Reduce receivable/increase customer credit once from the original payment. | Not applicable. |
| Apply or reapply existing money | No new cash receipt and no second customer-ledger credit; allocation changes settlement attribution. | No new supplier settlement merely because an existing payment is allocated again. |
| Purchase/recognized supplier payable | Not applicable. | Increase payable once from the defined posted purchase/payable event. Exact recognition timing remains a policy decision. |
| Supplier payment | Not applicable. | Reduce payable once. |
| Authorized refund/reversal/cancellation | A linked compensating entry reverses the affected original amount; do not post both a refund and a full payment reversal for the same economic event. | A linked compensating entry reverses the original purchase/payment effect as appropriate. |

The immutable `customers.opening_balance` and `suppliers.opening_balance` retain original opening reference values. Reconcile a nonzero opening to the relevant opening ledger entry; `opening_owner_id` uniqueness permits at most one such entry per party. A zero opening needs no zero-amount ledger row, because ledger entries reject zero amounts. They do not create sales, income, purchases, expenses or invoice payments. Invoice payment status is derived from effective allocations; the complete customer account also includes opening balances, unapplied funds and other legitimate account entries. Therefore a party statement and the sum of invoice outstanding amounts have related but distinct meanings, and reconciliation must explain the difference.

The schema provides source lineage and precision. Composite foreign keys constrain a refund ledger entry to the same customer/supplier as its reversal source, and allocation releases to allocations/reversals belonging to the same original payment. The row-level entry-type signs are database-checked; aggregate ceilings, matching compensation amounts and approved economic treatment still require future validation. The schema does not post ledger rows automatically or define a final accounting recognition, valuation, tax, refund or expense allocation policy.

## Payment allocation, release and reversal

Cash/Credit are payment **terms**. Cash/Bank Transfer/Cheque are payment **methods**. Unpaid/Partially Paid/Paid describes **settlement state**. Keep these dimensions separate.

`payments`, `pos_sales`, `payment_allocations` and `payment_reversals` use non-null `customer_scope_id` checked against `COALESCE(customer_id, 0)`. Composite foreign keys bind allocations to the payment and POS sale with the same scope, and reversals to their original payment scope. This closes the SQL nullable-composite-key gap: a named customer cannot be replaced with a null owner to skip ownership matching. Zero is an anonymous scope marker and does not reference a fake customer. Writers must supply the matching scope for named customers; the default zero does not satisfy that row's check. Additional row checks require a customer on every non-POS payment and on a Credit POS sale, so anonymous capacity is limited to the supported POS context rather than anonymous credit.

`payment_allocation_releases.payment_id` binds the release to the same payment as its allocation and optional reversal. `supplier_allocation_releases.supplier_payment_id` provides the equivalent supplier-side constraint. These row/source ownership guarantees do not enforce cumulative released or reversed amount limits.

The payment is the original record of money received. The allocation records how that existing money is applied. `payment_allocations.target_type` supports `job_advance`, `invoice`, `pos_sale` and `opening_balance`; supplier allocations target `purchase` or `opening_balance`. Settling an opening balance does not turn that balance into a sale or purchase. A later change of target must retain both the old and new attribution:

1. Record the payment once, retaining party, date, method, receiving employee and notes.
2. Allocate some/all of it to a valid target, or leave it unapplied if the approved policy permits.
3. To move a job advance to an invoice, append an allocation release against the old job allocation and a new invoice allocation; do not count both allocations as simultaneously effective.
4. Append a reversal/refund if authorized; preserve the original payment and add corresponding compensation/history. Release affected allocations as required by the transaction's semantics.

For an allocation `a`, define its effective amount as `a.amount - SUM(a.release amounts)`. Each release is positive and cumulative releases must not exceed the original allocation. Effective allocations against a payment must not exceed its remaining valid funds after reversals. Effective allocations against an invoice must not exceed the settlement allowed by the agreed policy. Customer/target ownership must be consistent within the current single-currency LKR design.

For an invoice, effective settlement is `SUM(payment_allocations.amount) - SUM(payment_allocation_releases.amount)` over allocations targeting that invoice. Outstanding is its `total` minus effective settlement. There is no stored `payment_status` column: Unpaid/Partially Paid/Paid must be derived from zero/partial/full effective settlement, with over-allocation or negative effective values treated as an integrity error instead of silently clamped. A zero-total document and any refund/credit nuances require the policy in OD05/OD10.

Those sum-based rules require a future locked transaction; a foreign key or unique key cannot enforce a cross-row sum. Invoice outstanding is based on document total and effective valid allocations. An allocation release restores outstanding on its old invoice; allocating the same money elsewhere reduces outstanding only on its new target. A partial settlement cannot set Paid. If a payment reversal and allocation release both refer to the same amount, calculate their settlement effects once according to the defined query/posting contract.

Supplier settlements use `supplier_payments`, `supplier_payment_allocations`, `supplier_allocation_releases` and `supplier_payment_reversals` with the same original-payment, source-lineage and single-account-effect principles. No payment processing or reapplication service is implemented in Step 2.

## Shared stock ledger and idempotency

BRD pages 10-11 require repairs and POS to draw from one stock source. Current stock is the sum of signed stock movements for the part/location scope. An opening movement is the starting quantity, not a parallel mutable `current_stock` value. Purchase drafts and unissued job/POS lines do not themselves change stock.

| Movement class | Quantity direction and source contract |
| --- | --- |
| Opening / finalized goods receipt | Positive; typed opening/GRN source identifies the original event. |
| Repair issue / finalized POS issue | Negative; tied to the exact usage or sale-line source and protected from repeated issue for that operation. |
| Restockable customer/job return | Positive; linked to the original issue and approved returned quantity. |
| Supplier return / damage | Negative; linked to the original stock source or authorized adjustment. |
| Authorized count/adjustment | Signed difference with user, reason, reference and physical-count/adjustment source. |
| Reversal through a typed return/adjustment | Opposite signed compensation may reference `reverses_movement_id`; there is no standalone `reversal` movement type. Source/idempotency uniqueness prevents repeat posting of the same approved operation. The original movement can have several partial compensations, so cumulative reversal/return ceilings require future validation. |
| Transfer, if enabled | Paired negative/positive location movements committed together. Transfer policy is conditional, not silently enabled by quantity storage. |

Typed source foreign keys avoid an unconstrained arbitrary source string being the only link to a financial/stock transaction. Stock source composite foreign keys match `spare_part_id` and `location_id` to the referenced receipt/usage/POS/return/adjustment/count line. The movement type/source shape and direction have row checks; agreement with original quantities and cumulative compensation limits still requires future validation. An idempotency key deduplicates retries of the same operation; applicable source uniqueness prevents a new key from silently issuing the same usage/sale/receipt event again. Separate legitimate receipt batches or partial operations need separate valid source records, not weakened uniqueness.

Stock source consistency and per-operation uniqueness are necessary but do not prove availability. Future issuance must lock a stable part/location row, read the authoritative stock in that transaction, validate quantity/unit precision and returns, then write movement/source/account changes atomically. Use a stable lock order and safe retry behavior. No `SUM` query followed by an unlocked insert can guarantee no oversell under concurrent sessions.

Quantity storage supports decimal capacity where needed; whole-unit restrictions depend on the part's unit metadata and future validation. A CHECK on one movement cannot consult an unrelated unit table. Valuation remains open; an unknown unit cost is nullable rather than zero, and revenue minus missing costs must not be presented as established profit.

## Status, identifiers and history

- Internal job IDs, manual job numbers and any automatic display numbers must not be reassigned after creation or reused after cancellation. Unique keys prevent duplicates; model guards and future controlled update paths preserve immutability. Direct SQL privileges remain an operational concern.
- Keep the 17 BRD statuses and previous/new/actor/time history. A future transition operation updates current status and appends history in the same transaction. Valid status membership does not itself authorize a transition.
- Append financial/stock compensation and allocation release history rather than deleting original facts. Model append-only rules are not equivalent to database-level denial of arbitrary UPDATE/DELETE.
- Restrict deletion of important customer/supplier/job/invoice/payment/stock/employee source records while they are referenced. Do not cascade-delete business history. Prefer inactive/cancelled states and authorized reversals.
- Explicitly distinguish business event dates from row-created timestamps and printed-at dates so reports can reconcile historical periods.

## Security and future validation

Customer type/name, address, mobile and required receiving identity/item facts must meet the BRD's mandatory rules. Non-null storage does not reject whitespace by itself; row checks and future Form Requests must enforce meaningful text. Email, phone formats and conditional date/state rules need explicit validation. Do not invent globally unique mobile numbers or insist that both customer and company name are always filled.

Store user passwords only in the existing hashed Laravel user field; never duplicate them on employees, settings, backups or audit payloads. Audit old/new values must redact passwords, tokens, secrets and unnecessary private data. Backup locations are identifiers/references, not embedded database/cloud passwords. Prepared RBAC tables do not grant rights until Step 3 defines and enforces policy.

## Migration delivery and phpMyAdmin

Apply application schema changes through the committed Laravel migrations in dependency order. Inspect existing tables first and preserve existing work/data; do not resolve migration conflicts by deleting tables. Use phpMyAdmin to inspect the database or administer access, not to create a second manually maintained application schema. Rollback/fresh/drop tests belong only on an explicitly disposable test database.

The local XAMPP server is MariaDB 10.4 behind Laravel's `mysql` driver. A local pass on that engine must be labelled MariaDB verification; it is not evidence of a MySQL 8 run. Server identification alone does not establish migration, rollback or test success.

## Verification limits

Database-focused tests can verify migration/rollback on disposable targets, relationships, decimal persistence, mandatory row constraints, unique keys, foreign keys, same-customer invoice links and source/idempotency checks. They must not be reported as proving unimplemented stock deduction, concurrent oversell prevention, payment processing, aggregate reconciliation, audit event capture, permission enforcement, or actual backup restoration.
