# Custom local backup location

As an administrator, open Backup & Restore, click Browse folders, choose a drive and navigate to a private writable folder. Optionally enter one new subfolder name, click Use this folder, then Save backup policy. The subfolder is created when the first backup runs. A full absolute folder path can also be typed after enabling Change backup location. Unchecking that option keeps the current location when saving schedule or retention changes.

The picker browses the computer running Laravel/PHP; external drives must be connected and accessible to that process. It lists folders only and does not open a desktop dialog or require an external plugin. Folder listing is administrator-only, rate limited and returned with no-store caching headers. Existing backups keep their original destinations and remain verifiable/downloadable there; changing the policy does not move or delete them. Google Drive settings are unchanged.

Destinations must be absolute, writable and outside the web/application roots. Paths pointing to files, missing parents, control characters and reserved Windows folder names are rejected as field errors. The selected path remains encrypted in system settings and is redacted from audit records. No database migration is needed.

Verification: BackupFolderSelectionTest, Step11BackupTest and BackupManagementTest cover permissions, folder navigation, encrypted settings, invalid paths, new subfolders and preservation of existing backup files. Chrome checks exercise the actual picker JavaScript using a mocked folder API.
