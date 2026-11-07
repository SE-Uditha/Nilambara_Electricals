<?php

return [
    // Disabled until an administrator supplies an approved existing XLSX file
    // and the secure Google OAuth configuration already used by backups.
    'enabled' => (bool) env('JOB_DATA_DRIVE_SYNC_ENABLED', false),
    'workbook_id' => env('JOB_DATA_DRIVE_WORKBOOK_ID'),
    'date_from' => env('JOB_DATA_DATE_FROM'),
    'date_to' => env('JOB_DATA_DATE_TO'),
    'attempts' => 3,
    'retry_delay_ms' => 1000,
    'status_path' => storage_path('app/private/job-data-sync/status.json'),
    'log_path' => storage_path('app/private/job-data-sync/sync.jsonl'),
];
