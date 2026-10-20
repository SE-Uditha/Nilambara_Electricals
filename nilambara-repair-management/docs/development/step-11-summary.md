# Step 11 implementation and verification

Date: 2026-10-20. Existing Laravel 13 application, PHP 8.5.11, local XAMPP MariaDB 10.4.32 through Laravel's MySQL connection. No second project, stock balance or financial ledger was created. No operational demo transactions were inserted. Work stops at Step 11.

## Delivery

The dashboard uses real date-filtered records, current job-status cohorts, receipt/reversal-date collections, positive per-customer credit balances and shared stock movements. Year/custom presets and ready/recent lists are available. Reports include repair/technician/status/completion, daily/monthly/yearly/invoice/repair/cash-credit/cashier sales, discounts/refunds, collections/advances, customer/supplier balances and statements, overdue credit, purchases/expenses and inventory movements/usage. Profit and valuation remain explicitly incomplete pending approved accounting policies.

Backend filters, sorting, pagination, reset, print, A4 PDF with Sinhala font/page numbering, numeric/date-formatted streamed XLSX and safe CSV enforce report permissions. Cashier collections and technician jobs are employee-scoped. Multi-job invoices and advances are not counted twice.

Settings, logo upload, English/Sinhala preference, paper/footer defaults, versioned print layout controls, safe signature/column JSON, audit search and redaction are integrated. Missing document-type templates are installed without replacing existing versions. Invoice/job-card paper rules remain A5/A6.

Backup software supports private consistent MySQL dumps, SHA-256, metadata/history, encrypted destination references, protected manual copies, scheduled retention, missed-schedule/failure warnings and controlled password-authenticated CLI restore. The daily definition is 02:00 Asia/Colombo, 30 days, maximum 30 scheduled copies, with overlap protection. Windows installer/runner and Linux cron instructions are provided. Google Drive resumable upload/checksum support is implemented but its account, folder and OAuth settings stay blank at the owner's request. No real Drive upload is claimed.

## Local deployment and activation

`2026_10_20_100000_extend_step11_backup_metadata` was inspected and applied to `nilambara_repair_management`. Fingerprints/counts confirmed preservation of original columns and records across 12 core business/authentication/history tables. Permissions and missing-template defaults were installed; no database export was created.

Live backup activation still requires approval of a private destination. Automatic approval review rejected exporting the complete live database to `D:\NilambaraBackups` because that exact location was not explicitly authorized. A user question is pending; no alternate or indirect export bypassed the decision. The Laravel schedule exists, but the Windows host task is not activated while destination approval is pending. Do not claim operational backups are running yet.

## Verification

Earlier completed focused MySQL verification: 42 passed / 2,133 assertions, native rollback/remigration, three separate-process checkout races, and a real isolated dump/restore. Recovery verified refusal without confirmation, refusal of the wrong password, safety backup, maintenance lifecycle, restored data and audit/history. Only a newly created randomized test database was restored and removed.

Latest focused SQLite report/document/backup run: 28 passed / 232 assertions. Vite production build passed. Focused Pint formatting and patch whitespace checks passed. A Sinhala report PDF was rendered and visually inspected. The local login page responds HTTP 200. `migrate:status` shows Step 11 applied; `schedule:list` shows `0 2 * * *` with Asia/Colombo specified in code. Final complete regression results are appended after the running checks finish.

Legacy MySQL-only test assumptions were corrected: rolled-back tests do not reset MySQL auto-increment IDs, and job stock references use `job_part_usages` rather than a nonexistent direct movement column. Those test fixes do not change production numbering/accounting behavior.

## Run locally

Final verification completed:

| Check | Result |
| --- | --- |
| Complete disposable MySQL suite | 195 passed, 1 SQLite-only fault-injection test skipped; 3,381 assertions. |
| Complete isolated SQLite suite | 193 passed, 3 native-MySQL storage checks skipped; 2,364 assertions. |
| Native migration rollback/remigration | Passed in disposable database. |
| Native checkout races and authenticated CLI dump/restore | Passed after the complete MySQL suite. |
| Final navigation/dashboard/report/backup regression | 20 passed, 110 assertions. |
| Authenticated local page smoke | Eleven dashboard/report/settings/template/audit/backup URLs returned HTTP 200 against real local records; no business records created. |
| Visual checks | Production-stylesheet dashboard preview and Sinhala PDF inspected. Preview data came only from isolated fixtures. |
| Build/style/scheduler | Vite build passed; focused Pint/whitespace checks passed; Windows installer syntax validated. Host task activation still pending. |

Detailed logs are local under `storage/logs/step11-complete-mysql.log`, `step11-complete-sqlite.log`, and `step11-navigation-check.log`. Native MySQL 8 and shop printer hardware were not tested; the native database server here is XAMPP MariaDB 10.4.32. The full native verification command was `php scripts/verify-mysql.php --all-step11`.

Start MySQL/Apache in XAMPP. From this application directory in PowerShell:

```powershell
$env:PHPRC = (Resolve-Path '..\.tools\php.ini').Path
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Open `http://localhost:8000` and sign in using an existing authorized account. Use Dashboard, Reports, Settings, Print Templates, Audit Logs and Backup & Restore. phpMyAdmin uses the same database; do not recreate migration-defined tables manually.

Checks: `php artisan optimize:clear`, `migrate:status`, `route:list`, `schedule:list`, `php scripts/verify-mysql.php --all-step11`, and `npm.cmd run build`. The native helper creates/removes only its own fresh local test database. SQLite tests require process-only `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` and empty `DB_URL`; keep operational `.env` on MySQL.

## Files and documentation

Main changes: Dashboard/Reports services/controllers/views, reusable invoice allocation expression, report export formatting, Settings/PrintTemplates/AuditLogs controllers/views, document branding/layout integration, role/template seeders, navigation, backup services/controller/models/restore command/configuration, metadata migration, scheduler scripts, isolated verification helpers and Step 11 tests. Environment placeholders contain no Drive credentials. The modified-file list is maintained separately beside this summary.

Guides: [dashboard](../modules/dashboard.md), [repair](../modules/repair-reports.md), [sales](../modules/sales-reports.md), [financial](../modules/financial-reports.md), [inventory](../modules/inventory-reports.md), [settings](../modules/system-settings.md), [templates](../modules/print-templates.md), [audit](../modules/audit-logs.md), [backup/restore](../modules/backup-restore.md).

External decisions: approve private live-backup storage and activate one host scheduler; configure Google Drive later; approve costing/expense recognition, mixed-allocation refund attribution and audit archival rules. Wider Step 9 workflows remain separate dependencies. Actual thermal/A5/A6 printers and Sinhala complex-script output require shop-side hardware acceptance. No Step 12 work was started.
