<?php

return [
    'root' => env('BACKUP_ROOT'),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'maximum_backups' => (int) env('BACKUP_MAXIMUM_DAILY', 30),
    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),
    'timezone' => 'Asia/Colombo',
    'google_drive' => [
        'folder_id' => env('BACKUP_GOOGLE_DRIVE_FOLDER_ID'),
        'email' => env('BACKUP_GOOGLE_DRIVE_EMAIL'),
        'client_id' => env('BACKUP_GOOGLE_CLIENT_ID'),
        'client_secret' => env('BACKUP_GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('BACKUP_GOOGLE_REFRESH_TOKEN'),
    ],
];
