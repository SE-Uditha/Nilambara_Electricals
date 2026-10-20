# Local performance test report

Driver: mysql; fixture: 1 customer, 25 jobs; 1 authenticated user; 1 iteration per measurement. Fixture construction excluded. Report timings include builder/summary and at most 50 returned rows; HTTP timings include test-kernel rendering. No network or browser latency is included.

| Operation | Milliseconds |
|---|---:|
| repair | 3.642 |
| technicians | 1.694 |
| sales | 3.231 |
| collections | 2.034 |
| advances | 2.162 |
| customer-credit | 1.631 |
| overdue-credit | 2.165 |
| supplier-outstanding | 1.639 |
| purchases | 2.944 |
| expenses | 2.193 |
| stock | 1.513 |
| stock-movements | 1.174 |
| repair-parts | 1.843 |
| pos-parts | 1.863 |
| adjustments | 1.339 |
| purchase-history | 1.889 |
| supplier-purchases | 1.205 |
| valuation | 1.473 |
| profit-estimate | 6.336 |
| daily-sales | 1.174 |
| monthly-sales | 1.106 |
| yearly-sales | 1.117 |
| repair-income | 3.218 |
| invoice-sales | 3.197 |
| cash-credit-sales | 1.193 |
| cashier-sales | 1.191 |
| discounts | 3.467 |
| refunds | 1.103 |
| cash-summary | 2.194 |
| cash-collections | 2.157 |
| customer-statement | 19.95 |
| supplier-statement | 23.256 |
| dashboard | 171.131 |
| customers.index | 103.362 |
| repair-jobs.index | 176.549 |

These are small local observations with mostly empty financial/stock tables, not percentiles or load/capacity claims. Native two-cashier invoice/payment barrier timings are recorded separately in the concurrency log. The operating system and server are shared with test runs; timings may vary substantially.

Invoice creation, standalone payment allocation, stock updates, POS checkout and PDF generation have correctness tests and whole-test durations, but isolated operation latency was not instrumented. Do not treat PHPUnit test duration as a service benchmark. Missing repair issue/GRN services cannot be benchmarked. No statistically defensible slow-query/N+1 baseline at realistic volume was established, so no speculative index or calculation changes were made. Existing schema/index inventory is in the database audit.
