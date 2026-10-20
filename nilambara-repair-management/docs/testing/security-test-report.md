# Security test report

Safe local testing only. AuthenticationTest executes password rejection, CSRF enforcement, five-attempt rate limiting, session regeneration, logout invalidation, disabled-account rejection and no public registration. EmployeeManagementTest tests all six role definitions, per-user denial precedence, direct route restrictions, assigned technician access and last-administrator protection. Payment/POS/backup tests exercise sensitive operation permissions.

Step12AuditTest sends an SQL-looking search value and verifies it remains data, then verifies stored script markup is escaped in customer details. Report sort fields are allowlisted. These narrow probes do not constitute exhaustive penetration testing of every field.

SystemSupportTest rejects unknown/secret editable settings; AuditSafetyTest covers redaction and historical log safety. Backup tests reject web-root destinations and traversal, conceal private paths, verify checksums and require authenticated restore confirmation. Google Drive transport is mocked; no actual account access is configured.

PHP syntax and Composer validation results are in their evidence logs. Composer audit reported no security vulnerability advisories at execution time; this is a dependency advisory result, not proof the application has no vulnerabilities. No package versions were changed.

Unverified: full hostile upload corpus, every stored/reflected XSS sink, end-to-end browser session timeout, local filesystem permission guarantees for every Windows account, production HTTPS/cookie configuration (outside this phase). No security mechanism was disabled to pass business tests. Supplier/stock authorization cannot be accepted for absent workflows. The conflicting management/storekeeper purchase rule requires owner agreement.
