# Administrator manual

## 1. Scope and current state
This handover describes the existing local Laravel application as verified on 20 October 2026. It is not a production deployment or approval. The active workspace used for this verification is D:/xampp/htdocs/Nilambara_Electricals/nilambara-repair-management. Earlier F: and D: workspace references should be adjusted to the actual installation. Do not create another database merely to resolve a path mismatch.

The application uses Laravel 13, PHP 8.5 (dependencies require at least PHP 8.4), the MySQL connection with local XAMPP MariaDB 10.4.32, InnoDB and utf8mb4, and phpMyAdmin as an administration interface. The operational database is nilambara_repair_management. Application tables are defined by migrations; phpMyAdmin is not a second schema-authoring workflow.

## 2. Start the existing local installation
Start the existing MySQL service through XAMPP or the approved service configuration. Avoid starting a duplicate server on port 3306. In PowerShell, change to the application directory and use the tested PHP executable/configuration:

```powershell
cd D:\xampp\htdocs\Nilambara_Electricals\nilambara-repair-management
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Open http://127.0.0.1:8000/login. Existing compiled assets are sufficient if present; Vite development can be run separately with npm run dev when developing. Restart Artisan after .env changes. Ctrl+C stops the development server. This local development command does not establish unattended production hosting.

If the port is occupied, check the existing process before changing ports. A missing driver usually means the wrong PHP/configuration is loaded. If assets are missing, refer to README prerequisites and involve the developer; no production build/deployment is performed by this documentation task.

## 3. Database and change protection
Keep credentials in the local .env file, never this manual or a shared screenshot. Confirm DB_CONNECTION=mysql, DB_HOST/DB_PORT for the existing server and DB_DATABASE=nilambara_repair_management. Inspect php artisan migrate:status before upgrades. Inspect existing tables/data and pending SQL before any additive migration. Preserve APP_KEY and authentication tables. Do not use migrate:fresh, reset, operational rollback or demo seeders on the shop database.

phpMyAdmin can view the same tables when connected to the same server/database. Do not manually recreate application tables already described by migrations. The latest audit recorded 69 tables, 199 exact foreign-key constraints and zero orphan relationships. Before/after row counts matched; this is not a byte-for-byte record comparison.

## 4. Employees, accounts and permissions
Use Administration employee/user screens to create staff and optional linked accounts. An employee may have no login. Assign the minimum necessary role and a unique username; do not reuse another person's account. Use administrator password reset for recovery. Disabling an employee or account invalidates access and stored sessions while preserving history. Re-enabling an employee does not automatically re-enable a separately disabled login.

Only if no active administrator exists, php artisan app:create-user bootstraps the initial linked administrator. The interactive command requests a strong confirmed password. app:assign-initial-admin can promote an existing unassigned account with explicit confirmation when no active administrator exists. These commands refuse while an active administrator is present.

Use Roles & Permissions to review grants and individual overrides. Explicit denial takes precedence. An administrator account cannot receive individual overrides; final administrator protection prevents locking out all administration. Technicians remain restricted to assigned jobs. Existing customized role grants are preserved. Storekeeper receiving/stock permission does not grant purchase approval or supplier payments. Any change to approval authority requires an explicit business decision and a review of existing accounts.

## 5. Business settings and print templates
Authorized settings users maintain business name/address/telephone, PNG/JPEG logo, currency, timezone, language, formats, paper and footer. Database secrets and OAuth tokens are not ordinary editable settings. The supplied address is 482/A/3, Colombo Road, Kaduwela; verify any contact number rather than inventing one. Uploaded logos need writable storage and the configured public storage link.

Review active templates for each document: title, logo, margins, paper/orientation, font size, footer and duplicate marker. Template overrides may supersede business defaults for new documents. Check English/Sinhala, long descriptions, page breaks and signature areas. Receipt/card PDFs and statement/purchase/GRN PDFs were visually checked in fixtures; physical printer and scanner checks still need shop acceptance.

## 6. Backup configuration and daily checks
An approved private operational BACKUP_ROOT is still pending. Select a protected absolute folder outside application/web roots with the owner; restrict filesystem access. SQL dumps contain confidential business/authentication data. Destination metadata encryption does not encrypt the SQL dump itself. Use protected/encrypted storage. MYSQLDUMP_BINARY and MYSQL_BINARY must point to the actual clients, locally D:/xampp/mysql/bin.

Authorized staff use /admin/backups to create backups, inspect history, verify integrity and download only with download permission. Check completion, nonempty size and recorded SHA-256. A checksum is not proof of restore. Local backup success and cloud success are separate states. Retention defaults to 30 days/30 scheduled versions and protects manual copies; review failures and missed schedules.

Google Drive is intentionally inactive. Keep BACKUP_GOOGLE_DRIVE_FOLDER_ID, BACKUP_GOOGLE_DRIVE_EMAIL, BACKUP_GOOGLE_CLIENT_ID, BACKUP_GOOGLE_CLIENT_SECRET and BACKUP_GOOGLE_REFRESH_TOKEN blank until an approved account/folder and OAuth setup are provided. Do not claim an off-computer copy exists while blank.

Default policy time is 02:00 Asia/Colombo. The host scheduler has not been activated in this phase. After approval, an operator can review scripts/install-windows-scheduler.ps1 and configure one task invoking Laravel every minute with an appropriate account and private-folder permissions. Do not activate duplicate daily jobs. Confirm the computer, MySQL and service account remain available at the scheduled time.

## 7. Restore and incident handling
Restore is an authorized destructive recovery operation, not a routine test on live data. Obtain explicit approval for the target and recovery point and ensure a verified safety copy. The CLI command php artisan backup:restore ID requires confirmation, the exact database name, active administrator identity, restore permission and password verification. It creates a safety backup and uses maintenance mode.

Successful recovery reconnects, checks core tables/administrator identity and records recovery/audit history. Failure leaves maintenance mode enabled for investigation; preserve logs and involve the maintainer before reopening. Validate sample customer balances, stock, document histories, administrator access and backup metadata against the recovery plan. The isolated test proved fixture recovery, not recovery of the live shop database.

## 8. Audit, testing and support
Audit access is permission restricted. Search by actor/action/date/reference and retain relevant events for investigation. Posted financial and stock corrections preserve original records. Never clear logs or delete documents to hide an error. Report the business reference and time; redact credentials and confidential customer information when sharing support evidence.

The final disposable MySQL suite passed 218 tests and 3566 assertions. Native concurrency and actual test dump/restore checks passed separately. See the Test Report for evidence and limits. Tests must use isolated fixtures, not operational customer data. The developer verification helper is php scripts/verify-mysql.php --all-step11 --step12 --pending; review its safeguards and use only an approved local test environment. Do not change the runtime database to a test fixture database.

## 9. Open decisions and handover
Owner-selected costing is latest purchase cost. Purchase-wide discount/delivery allocation is not yet decided, so affected profit remains incomplete. Preserve the existing role matrix pending any approval-authority decision. Obtain the private backup destination, configure/verify real off-computer backup later, complete physical hardware checks and realistic-volume acceptance, then obtain owner UAT signatures. None of these approvals are implied by this manual.
