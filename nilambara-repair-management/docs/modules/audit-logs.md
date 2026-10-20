# Audit logs

`/admin/audit-logs` (and legacy `/audit-logs`) requires `audit.view`. The viewer provides dates, actor/username/employee, module/action, record identity, redacted changes and available IP address. Filter by dates, actor, action, entity/module, record ID or reference text. Results are paginated and ordered by event time and ID.

Existing authentication, employee/permission, job, quotation, invoice, payment, POS and setting services retain their audit events. Backup completion/failure, configuration, download, cleanup and restore actions also record events. Future supplier/stock mutation workflows must use the same audit mechanism when implemented; this viewer does not create fake missing events.

The model preserves sensitive history and offers no ordinary edit/delete UI. Secrets are redacted before display/storage where recognized. Application auditing cannot make records tamper-proof against an unrestricted database administrator. No automatic audit purge is introduced; capacity monitoring and an owner-approved retention/archive policy are required.
