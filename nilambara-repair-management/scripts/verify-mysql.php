<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Exercise the suite and rollback only in a database created by this process.
 * Credentials come from Laravel configuration and are never printed.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if ($app->configurationIsCached()) {
    throw new RuntimeException('Run php artisan config:clear before isolated database verification.');
}
if ($app->isProduction() || ! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || ! empty($config['url'])) {
    throw new RuntimeException('This helper requires an explicit local, nonproduction MySQL connection without DB_URL.');
}
$testDatabase = 'nilambara_step2_test_'.bin2hex(random_bytes(6));
if (! preg_match('/\Anilambara_step2_test_[0-9a-f]{12}\z/', $testDatabase) || $testDatabase === $config['database']) {
    throw new RuntimeException('Unsafe test database identifier.');
}
$pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$exists->execute([$testDatabase]);
if ((int) $exists->fetchColumn() !== 0) {
    throw new RuntimeException('Refusing to reuse an existing test database.');
}
$environment = array_merge(getenv(), [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $testDatabase,
    'DB_HOST' => (string) $config['host'], 'DB_PORT' => (string) $config['port'],
    'DB_USERNAME' => (string) $config['username'], 'DB_PASSWORD' => (string) $config['password'], 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
]);
$prefix = [PHP_BINARY];
if (php_ini_loaded_file()) {
    array_push($prefix, '-c', php_ini_loaded_file());
}
$run = function (array $arguments) use ($prefix, $environment): void {
    $command = array_merge($prefix, ['artisan'], $arguments);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start verification command.');
    }
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Verification command failed: artisan '.implode(' ', $arguments));
    }
};
$created = false;
$result = 0;
try {
    $pdo->exec('CREATE DATABASE `'.$testDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    echo 'Created disposable database: '.$testDatabase.PHP_EOL;
    $run(['test', '--no-ansi']);
    $run(['migrate:rollback', '--force', '--no-interaction', '--no-ansi']);
    $remaining = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> ?');
    $remaining->execute([$testDatabase, 'migrations']);
    if ((int) $remaining->fetchColumn() !== 0) {
        throw new RuntimeException('Rollback left application tables in the disposable database.');
    }
    $run(['migrate', '--force', '--no-interaction', '--no-ansi']);
    $run(['migrate:status', '--no-ansi']);
    echo 'Disposable MySQL-compatible suite, rollback, and remigration succeeded.'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    $result = 1;
} finally {
    if ($created) {
        // Only the fresh, randomized database created above is ever dropped.
        $pdo->exec('DROP DATABASE `'.$testDatabase.'`');
        echo 'Removed this process\'s disposable database.'.PHP_EOL;
    }
}
exit($result);
