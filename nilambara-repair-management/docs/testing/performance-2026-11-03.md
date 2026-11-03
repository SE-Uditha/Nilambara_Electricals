# Performance review ? 3 November 2026

Measured the existing local MySQL application as an active administrator using `scripts/benchmark-read-pages.php`. Each route receives one warm-up render followed by four measured renders; values below are their medians. This measures authenticated Laravel server rendering through the HTTP kernel. PHP process startup, network transfer, browser rendering, mobile hardware and concurrent-user load are excluded. Timing varies with local load.

| Page | Before (ms) | After (ms) | Query count before ? after |
| --- | ---: | ---: | ---: |
| `/dashboard` | 172.52 | 54.57 | 78 ? 17 |
| `/repair-jobs` | 255.47 | 72.96 | 105 ? 16 |
| `/customers` | 150.86 | 47.29 | 67 ? 10 |
| `/invoices` | 203.13 | 61.93 | 99 ? 17 |
| `/payments` | 213.62 | 67.88 | 95 ? 18 |
| `/repair-jobs/create` | 138.95 | 41.59 | 56 ? 9 |

## Changes

- Repeated permission-override lookups share a single query for the authenticated HTTP request. Every new request reloads the overrides, while checks for other users and console calls retain their live database lookup. Denials still override role grants.
- Invoice list balances are calculated in one batch, keeping decimal arithmetic, advance allocations and released-payment handling unchanged.
- Payment lists eager-load allocation releases instead of loading them row by row.
- Dashboard sales, collection and stock totals use consolidated aggregates. The collection query retains the existing cashier restrictions; dashboard previews fetch only the four rows they display.
- Customer browsing uses next-page pagination without an unnecessary full count.
- The browser logo uses a 374,342-byte lossless WebP instead of the 727,632-byte PNG. Visible pixels and dimensions were verified unchanged. The original PNG is retained for fallback and printed documents. Updated/custom PNGs bypass an outdated WebP.
- Apache configuration compresses text responses and permits long-lived caching only for content-hashed application CSS/JS. These Apache directives do not apply to `php artisan serve`.
- Project-local `.tools/php.ini` enables OPcache for the local PHP server/CLI. Timestamp validation remains enabled with zero revalidation delay, so edits are still checked immediately. The before measurements used the previous setting; the after measurements include this change. Restart an existing `php artisan serve` process once to load the revised PHP configuration.

## Verification

- Complete isolated suite: **282 passed, 3 skipped, 3,213 assertions**. The three skipped checks require MySQL-specific storage verification; they were not run in this SQLite suite.
- Additional fresh-process checks with OPcache enabled: permission revocation/restoration between requests and batch invoice release/advance totals passed (2 tests, 18 assertions).
- Final benchmark ran against the existing local MySQL database without creating or changing business records; sessions used an in-memory driver.
- Frontend build and diff whitespace checks passed.
- Browser automation is unavailable, so no browser paint, Lighthouse or network-speed score is claimed.

To reproduce the final read-only measurement from the application directory:

```powershell
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php scripts/benchmark-read-pages.php after
```
