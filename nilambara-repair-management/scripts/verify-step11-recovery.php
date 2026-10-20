<?php

use App\Console\Commands\RestoreDatabaseBackupCommand;
use App\Models\User;
use App\Modules\BackupRestore\Services\BackupService;
use App\Modules\Settings\Models\SystemSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || ! preg_match('/\Anilambara_step2_test_[0-9a-f]{12}\z/', DB::getDatabaseName())) {
    throw new RuntimeException('Recovery verification requires the disposable verify-mysql.php database.');
}
$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nilambara-recovery-'.bin2hex(random_bytes(6));
mkdir($root, 0700);
$runtime = $root.DIRECTORY_SEPARATOR.'runtime';
mkdir($runtime, 0700);
mkdir($runtime.DIRECTORY_SEPARATOR.'framework', 0700);
mkdir($runtime.DIRECTORY_SEPARATOR.'logs', 0700);
$app->useStoragePath($runtime);
config(['backup.root' => $root, 'backup.google_drive' => [], 'app.maintenance.driver' => 'file']);
$dump = getenv('MYSQLDUMP_BINARY') ?: 'D:/xampp/mysql/bin/mysqldump.exe';
$mysql = getenv('MYSQL_BINARY') ?: 'D:/xampp/mysql/bin/mysql.exe';
config(['backup.mysqldump_binary' => $dump, 'backup.mysql_binary' => $mysql]);
(new RoleSeeder)->run();
$password = bin2hex(random_bytes(16));
$actor = User::factory()->create(['username' => 'recovery_test_admin', 'password' => $password]);
$actor->assignRole('administrator');
SystemSetting::updateOrCreate(['group' => 'verification', 'key' => 'restore_probe'], ['value' => 'before-backup', 'value_type' => 'string', 'description' => 'Disposable recovery test only.']);
$backups = app(BackupService::class);
$backup = $backups->create(false, $actor, false);
if (! $backups->verify($backup) || $backup->file_size < 100) {
    throw new RuntimeException('Native dump verification failed.');
}
echo "PASS: real MySQL dump created, nonempty, SHA-256 verified, private and protected.\n";
SystemSetting::where('group', 'verification')->where('key', 'restore_probe')->update(['value' => json_encode('after-backup')]);

// Supply deterministic interactive answers in this isolated process. The actual
// command still performs authorization, password verification, safety backup,
// maintenance mode, mysql import, connection checks and recovery logging.
$command = new class extends RestoreDatabaseBackupCommand
{
    public string $testPassword = '';

    public bool $approve = true;

    public function confirm($question, $default = false)
    {
        return $this->approve;
    }

    public function ask($question, $default = null)
    {
        return str_contains($question, 'database name') ? DB::getDatabaseName() : 'recovery_test_admin';
    }

    public function secret($question, $fallback = true)
    {
        return $this->testPassword;
    }
};
$command->setLaravel($app);
$kernel->registerCommand($command);
$command->approve = false;
if (Artisan::call('backup:restore', ['backupId' => $backup->id]) === 0) {
    throw new RuntimeException('Restore ran without explicit confirmation.');
}
$command->approve = true;
$command->testPassword = 'deliberately-wrong-password';
if (Artisan::call('backup:restore', ['backupId' => $backup->id]) === 0) {
    throw new RuntimeException('Restore ran without administrator authentication.');
}
$command->testPassword = $password;
if (Artisan::call('backup:restore', ['backupId' => $backup->id]) !== 0) {
    throw new RuntimeException('Isolated restore failed: '.Artisan::output());
}
if (SystemSetting::where('group', 'verification')->where('key', 'restore_probe')->firstOrFail()->value !== 'before-backup') {
    throw new RuntimeException('Restored record did not match the dump.');
}
if ($app->isDownForMaintenance() || ! DB::table('backup_restores')->where('status', 'completed')->exists()
    || ! DB::table('audit_logs')->where('action', 'backup.restore.completed')->exists()) {
    throw new RuntimeException('Post-restore verification or recovery logging failed.');
}
echo "PASS: confirmation and wrong-password refusals; authenticated CLI restore, safety dump, maintenance lifecycle, data recovery and audit history.\n";
// Remove only files created in this unique test directory; never recurse.
foreach (glob($root.DIRECTORY_SEPARATOR.'*.sql') as $file) {
    unlink($file);
}
foreach (glob($runtime.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'*') as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
rmdir($runtime.DIRECTORY_SEPARATOR.'logs');
rmdir($runtime.DIRECTORY_SEPARATOR.'framework');
rmdir($runtime);
rmdir($root);
