# Defect register

| ID | Severity | Module | Finding / root cause | Correction / verification | State |
|---|---|---|---|---|---|
| D12-01 | Medium | POS refunds | Rounding each return independently down can strand a cent; independent discounted line shares can also sum below paid total. Example: LKR 1 total, three units, three caps of 0.33. | Cumulative line and quantity entitlements; two regression tests assert 0.33+0.33+0.34 and 0.49+0.50 complete refunds without overpayment. No existing records changed. | Fixed in code; see final MySQL log for regression result. |
| D12-02 | Low | Test coverage | Invoice insert-failure rollback test was SQLite-only, leaving MySQL atomicity untested. First attempted trigger adaptation implicitly committed MySQL fixtures and failed. | Isolated model-event fault injection raises QueryException inside the transaction; original event dispatcher restored in finally, with no DDL. | Corrected and retested; final MySQL log is authoritative. |
| G12-01 | High | Inventory | Missing approved repair issue/receiving/adjustment/count workflows. | Record as missing; a full new module is outside a small audit correction. | Open |
| G12-02 | High | Suppliers / purchases | Missing supplier CRUD, purchase drafts, GRNs and settlement workflows. | BRD matrix records individual gaps. | Open |
| G12-03 | High | Recovery readiness | Operational backup destination and Drive account unset; no active host scheduler evidenced. | Isolated recovery verified; no unauthorized operational export or scheduler activation. | Blocked on configuration/approval |
| G12-04 | Medium | Print settings | Complete title/font/paper propagation and physical print acceptance not established; receipt/card PDF download not verified. | Retain working output, document bounded coverage. | Open / unverified |
| G12-05 | Medium | Accounting policy | Profit/valuation and mixed-allocation refund classification need agreed business treatment. | Incomplete-profit warning retained; no invented accounting assumptions. | Open |
| G12-06 | Medium | Concurrency and performance | Reception/technician races and realistic-volume capacity not measured. | POS and cashier races bounded to two workers; no unsupported throughput claims. | Open / untested |
| G12-07 | Medium | UAT | No owner approval or hardware print/scanner acceptance. | Prepared cases; no fabricated signatures. | Blocked |

D12-01 reproduction follows the original arithmetic and request-validation path; no historical refund was edited. Executed regression output is authoritative. No failed tests were hidden or removed.
