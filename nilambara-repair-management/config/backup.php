<?php

return [
    'root' => env('BACKUP_ROOT'),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'retention_days' => 30,
    'maximum_backups' => 30,
    'schedule_time' => '02:00',
    'timezone' => 'Asia/Colombo',
];
