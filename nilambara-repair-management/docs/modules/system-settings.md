# System settings

`/settings` uses `settings.view` and `settings.update`. Business name, address, telephone, PNG/JPEG logo, currency, timezone, default English/Sinhala language, date/time formats, default paper and footer are allowlisted and validated. Values, types, actor and timestamps use existing `system_settings`; changes create audit events. Database credentials, OAuth tokens and arbitrary Laravel configuration are not editable here.

The supplied address is 482/A/3, Colombo Road, Kaduwela; no telephone is invented. Existing configured branding is preserved. New document generation uses business defaults unless a document template has an explicit override. Historical amounts remain unchanged. Public logo uploads require the Laravel storage link and filesystem write permissions. PNG/JPEG avoids unsupported PDF logo formats.

Backup policy is separately restricted to administrators with `backups.configure`. Destination paths are encrypted at rest and omitted from history serialization and ordinary page output. Google Drive credentials remain deployment environment values, not ordinary settings.
