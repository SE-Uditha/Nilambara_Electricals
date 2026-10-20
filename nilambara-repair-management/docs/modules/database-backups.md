# Private database backups

This is the historical guide. Use the current [Step 11 backup/restore procedures](backup-restore.md) for protected manual copies, encrypted destination metadata, cloud configuration, authenticated restore and host scheduler installation. Live destination approval remains a separate activation requirement.

Backups are disabled until `BACKUP_ROOT` points to an absolute private destination outside the repository and public web root. Configure `MYSQLDUMP_BINARY` and `MYSQL_BINARY` when the tools are not on `PATH`. Prefer an access-controlled, off-host destination and restrict the directory to the application operator.

The scheduler creates a MySQL/MariaDB dump daily at 02:00 in `Asia/Colombo`. Each dump is recorded with a SHA-256 checksum and verified before it becomes downloadable. Retention is 30 days and at most 30 backup files. Cleanup runs only after a recent backup verifies; corrupt or unverifiable files are not removed by cleanup.

The web interface can create, browse, and download checksum-verified backups. Restore is intentionally not exposed over HTTP. The `backup:restore {backupId}` command requires interactive confirmation, creates a pre-restore backup, enters maintenance mode, verifies the selected file, and leaves maintenance mode enabled if the restore fails. It must only be run by an authorized operator after selecting the correct database and target.

No backup destination is committed to the repository or stored in system settings.
