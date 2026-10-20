# Defect register

Final evidence: 218 passed (3566 assertions); see pending-complete-mysql.log. Earlier failures remain in their original evidence logs and were not hidden.

| ID | Severity | Finding | Current state |
|---|---|---|---|
| D12-01 | Medium | Discounted refund cent rounding | Fixed and regression-tested. |
| D12-02 | Low | MySQL invoice rollback fault-injection coverage | Portable model-event injection passes. |
| G12-01 | High | Missing inventory issue/receiving/adjustment/count operations | Implemented and tested; full unused-issue return supported. |
| G12-02 | High | Missing suppliers/purchases/GRNs/settlement | Implemented and tested, including opening settlement and return credits. |
| D12-03 | High | Approved quotation did not transfer job selling totals | Fixed; receipt-to-paid-invoice test uses real service calls, without manually setting job prices. |
| D12-04 | Medium | Completion/delivery statuses could leave timestamps empty | Fixed; timestamp assertions pass. |
| D12-05 | Medium | Older returned stock could replace newer purchase cost | Fixed with owner-approved latest-purchase source selection. |
| D12-06 | Medium | Accounting payment reversal reduced reported sales | Fixed; collections change while issued sale remains intact. |
| G12-04 | Medium | Intake PDF gaps, fonts, statement title/paper propagation | Implemented and tested; short fixture PDFs visually reviewed. Physical acceptance pending. |
| G12-03 | High | Operational backup destination/cloud account unset | Awaiting approved private folder; Drive remains blank by request. No host scheduler activated. |
| G12-05 | Medium | Purchase-wide cost allocation / ambiguous financial treatment | Latest purchase method approved; freight/discount allocation still awaits answer. Affected profit stays incomplete. |
| G12-06 | Medium | Reception/technician/repair-POS/GRN concurrency | Two-worker native cases pass. Realistic-volume capacity remains unmeasured. |
| G12-07 | Medium | Physical printing/scanning and business sign-off | External UAT pending; no signatures fabricated. |
| L12-01 | Low | Partial return of one repair issue / repeated-part historical purchase return | Full unused repair issue can be reversed; ambiguous repeated-part purchase return is refused pending source reconciliation. |
