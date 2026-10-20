<?php

namespace App\Modules\BackupRestore\Services;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\BackupRestore\Models\BackupHistory;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BackupService
{
    private const STORAGE_LOCATION = 'private-backup-root';

    public function isConfigured(): bool
    {
        return $this->safeRoot() !== null
            && in_array(config('database.default'), ['mysql', 'mariadb'], true)
            && $this->executable('mysqldump_binary') !== null;
    }

    public function create(bool $scheduled, ?User $actor, bool $cleanup = true): BackupHistory
    {
        $connection = config('database.default');
        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Automated database backups are supported only for MySQL or MariaDB.');
        }

        $filename = 'nilambara-'.$this->timestamp().'-'.bin2hex(random_bytes(6)).'.sql';
        $path = null;
        $history = BackupHistory::query()->create([
            'backup_at' => now(config('backup.timezone')),
            'file_reference' => $filename,
            'backup_type' => $scheduled ? 'scheduled' : 'manual',
            'status' => 'running',
            'storage_location' => self::STORAGE_LOCATION,
            'initiated_by_user_id' => $actor?->getKey(),
            'is_protected' => ! $scheduled,
        ]);
        $credentialPath = null;
        $output = null;
        try {
            $root = $this->requireRoot();
            $history->update(['destination_path' => $root]);
            $path = $root.DIRECTORY_SEPARATOR.$filename;
            $binary = $this->executable('mysqldump_binary');
            if ($binary === null) {
                throw new RuntimeException('The configured database dump utility is unavailable.');
            }
            $credentialPath = $this->credentialFile($root);
            $output = fopen($path, 'xb');
            if ($output === false) {
                throw new RuntimeException('Could not create the private backup file.');
            }
            $command = [
                $binary,
                '--defaults-extra-file='.$credentialPath,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                (string) config('database.connections.'.$connection.'.database'),
            ];
            $process = new Process($command, base_path(), null, null, null);
            $process->run(function (string $type, string $buffer) use ($output): void {
                if ($type === Process::OUT) {
                    $this->writeDumpChunk($output, $buffer);
                }
            });
            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump failed with exit code '.$process->getExitCode().'.');
            }
            if (! fflush($output)) {
                throw new RuntimeException('The database dump could not be flushed to disk.');
            }
            clearstatcache(true, $path);
            if ((filesize($path) ?: 0) === 0) {
                throw new RuntimeException('The database dump is empty or incomplete.');
            }
            $checksum = hash_file('sha256', $path);
            if ($checksum === false) {
                throw new RuntimeException('The database dump could not be verified.');
            }
            $this->removeTemporaryCredentialFile($credentialPath);
            $credentialPath = null;

            $history->update([
                'status' => 'completed',
                'checksum_sha256' => $checksum,
                'verified_at' => now(config('backup.timezone')),
                'file_size' => filesize($path),
                'completed_at' => now(config('backup.timezone')),
                'verification_status' => 'checksum_verified',
            ]);
        } catch (\Throwable $exception) {
            $history->update([
                'status' => 'failed',
                'error_details' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(config('backup.timezone')),
                'verification_status' => 'failed',
            ]);
            $this->recordAudit($actor, 'backup.failed', $history, ['reason' => 'Database backup failed. Check protected server logs.']);
            if (is_string($path) && is_file($path) && ! unlink($path)) {
                throw new RuntimeException('The failed database dump could not be removed from the private destination.', 0, $exception);
            }
            throw $exception;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
            if (is_string($credentialPath)) {
                $this->removeTemporaryCredentialFile($credentialPath);
            }
        }

        if (! $this->verify($history)) {
            $history->update(['status' => 'failed', 'error_details' => 'The completed backup failed checksum verification.']);
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException('The unverified database dump could not be removed from the private destination.');
            }
            throw new RuntimeException('The completed backup failed checksum verification.');
        }

        $this->recordAudit($actor, 'backup.completed', $history, [
            'backup_type' => $history->backup_type,
            'checksum_sha256' => $history->checksum_sha256,
        ]);
        try {
            app(GoogleDriveBackupService::class)->copy($history, $path);
        } catch (\Throwable) {
            $history->update(['cloud_status' => 'failed']);
            $this->recordAudit($actor, 'backup.cloud.failed', $history, ['reason' => 'Cloud copy failed; the verified local backup remains available.']);
        }
        if ($cleanup) {
            try {
                $this->cleanupVerifiedBackups();
            } catch (\Throwable) {
                $this->recordAudit($actor, 'backup.cleanup.failed', $history, ['reason' => 'Retention cleanup failed; backup retained.']);
            }
        }

        return $history->fresh();
    }

    public function verify(BackupHistory $history): bool
    {
        if ($history->deleted_at !== null || $history->status !== 'completed'
            || $history->storage_location !== self::STORAGE_LOCATION
            || ! is_string($history->checksum_sha256)
            || strlen($history->checksum_sha256) !== 64) {
            return false;
        }

        try {
            $path = $this->backupPath($history);
        } catch (RuntimeException) {
            return false;
        }
        $checksum = is_file($path) && is_readable($path) && filesize($path) > 0 ? hash_file('sha256', $path) : false;

        return is_string($checksum) && hash_equals($history->checksum_sha256, $checksum);
    }

    public function downloadPath(BackupHistory $history): string
    {
        if (! $this->verify($history)) {
            throw new RuntimeException('The backup file is missing or failed checksum verification.');
        }

        return $this->backupPath($history);
    }

    public function restoreDump(BackupHistory $history): void
    {
        if (! $this->verify($history)) {
            throw new RuntimeException('The selected backup is missing or failed checksum verification.');
        }
        $connection = config('database.default');
        $binary = $this->executable('mysql_binary');
        if (! in_array($connection, ['mysql', 'mariadb'], true) || $binary === null) {
            throw new RuntimeException('The configured MySQL client is not available for restore.');
        }

        $root = $this->requireRoot();
        $credentialPath = $this->credentialFile($root);
        $input = fopen($this->backupPath($history), 'rb');
        if ($input === false) {
            @unlink($credentialPath);
            throw new RuntimeException('The verified backup file could not be opened.');
        }

        try {
            $database = config('database.connections.'.$connection.'.database');
            $process = new Process([
                $binary,
                '--defaults-extra-file='.$credentialPath,
                '--default-character-set=utf8mb4',
                (string) $database,
            ], base_path(), null, $input, null);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('The MySQL restore process failed with exit code '.$process->getExitCode().'.');
            }
        } finally {
            fclose($input);
            $this->removeTemporaryCredentialFile($credentialPath);
        }
    }

    /** @param resource $output */
    private function writeDumpChunk($output, string $buffer): void
    {
        $length = strlen($buffer);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($output, substr($buffer, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the database dump.');
            }
            $offset += $written;
        }
    }

    private function removeTemporaryCredentialFile(string $path): void
    {
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('The temporary database credential file could not be removed.');
        }
    }

    public function cleanupVerifiedBackups(): void
    {
        $recentCutoff = now(config('backup.timezone'))->subDays((int) config('backup.retention_days'));
        $recent = BackupHistory::query()
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', $recentCutoff)
            ->orderByDesc('backup_at')
            ->first();
        if ($recent === null || ! $this->verify($recent)) {
            return;
        }

        $backups = BackupHistory::query()
            ->where('status', 'completed')
            ->where('storage_location', self::STORAGE_LOCATION)
            ->whereNull('deleted_at')->where('backup_type', 'scheduled')->where('is_protected', false)
            ->orderByDesc('backup_at')
            ->orderByDesc('id')
            ->get();
        $maximum = max(1, (int) config('backup.maximum_backups'));

        foreach ($backups as $index => $backup) {
            if ((int) $backup->getKey() === (int) $recent->getKey()) {
                continue;
            }
            $expired = $backup->backup_at->lt($recentCutoff);
            $overLimit = $index >= $maximum;
            if (! $expired && ! $overLimit) {
                continue;
            }

            try {
                $path = $this->backupPath($backup);
            } catch (RuntimeException) {
                continue;
            }
            if (is_file($path) && $this->verify($backup) && ! unlink($path)) {
                throw new RuntimeException('Verified backup retention cleanup could not remove an expired file.');
            }
            if (! is_file($path)) {
                $backup->update(['deleted_at' => now(), 'verification_status' => 'retained_history']);
                $this->recordAudit(null, 'backup.cleanup.deleted', $backup, ['backup_history_id' => $backup->getKey()]);
            }
        }
    }

    private function recordAudit(?User $actor, string $action, BackupHistory $history, array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => BackupHistory::class,
            'entity_id' => $history->getKey(),
            'occurred_at' => now(),
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
        ]);
    }

    private function credentialFile(string $root): string
    {
        $connection = config('database.default');
        $database = config('database.connections.'.$connection);
        $path = tempnam($root, '.db-client-');
        if ($path === false) {
            throw new RuntimeException('A private temporary database credential file could not be created.');
        }

        $escape = static fn (string $value): string => str_replace(
            ['\\', "\n", "\r", '"'],
            ['\\\\', '\\n', '\\r', '\\"'],
            $value,
        );
        $contents = "[client]\nuser=\"".$escape((string) ($database['username'] ?? ''))."\"\npassword=\"".$escape((string) ($database['password'] ?? ''))."\"\nhost=\"".$escape((string) ($database['host'] ?? '127.0.0.1'))."\"\nport=".(int) ($database['port'] ?? 3306)."\n";
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            @unlink($path);
            throw new RuntimeException('The private database credential file could not be written.');
        }
        @chmod($path, 0600);

        return $path;
    }

    private function backupPath(BackupHistory $history): string
    {
        if (basename($history->file_reference) !== $history->file_reference || str_contains($history->file_reference, "\0")) {
            throw new RuntimeException('The backup file reference is invalid.');
        }

        $root = $this->requireRoot($history->destination_path);
        $path = $root.DIRECTORY_SEPARATOR.$history->file_reference;
        $realPath = realpath($path);
        if ($realPath === false || ! $this->isWithin($realPath, $root)) {
            throw new RuntimeException('The backup file is outside the configured private destination.');
        }

        return $realPath;
    }

    private function requireRoot(?string $destination = null): string
    {
        $root = $this->safeRoot($destination);
        if ($root === null) {
            throw new RuntimeException('Backup destination is not configured or is not a private absolute path outside the application.');
        }

        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('The configured private backup directory could not be created.');
        }

        $realPath = realpath($root);
        if ($realPath === false || ! $this->safeRoot($realPath)) {
            throw new RuntimeException('The configured backup directory resolves to an unsafe location.');
        }

        return rtrim($realPath, DIRECTORY_SEPARATOR);
    }

    private function safeRoot(?string $path = null): ?string
    {
        $path ??= trim((string) config('backup.root'));
        if ($path === '' || preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\|\\/)/', $path) !== 1) {
            return null;
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            $parent = realpath(dirname($path));
            if ($parent === false) {
                return null;
            }
            $resolved = $parent.DIRECTORY_SEPARATOR.basename($path);
        }

        if ($this->isWithin($resolved, base_path())
            || $this->isWithin($resolved, dirname(base_path()))
            || $this->isWithin($resolved, dirname(dirname(base_path())))
            || $this->isWithin($resolved, public_path())) {
            return null;
        }

        return $resolved;
    }

    private function isWithin(string $path, string $directory): bool
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $directory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $directory = strtolower($directory);
        }

        return $path === $directory || str_starts_with($path, $directory.DIRECTORY_SEPARATOR);
    }

    private function timestamp(): string
    {
        return now(config('backup.timezone'))->format('Ymd-His');
    }

    public function isSafeDestination(string $destination): bool
    {
        return $this->safeRoot($destination) !== null;
    }

    private function executable(string $key): ?string
    {
        $name = (string) config('backup.'.$key);

        // Symfony's PATH finder does not resolve absolute Windows filenames.
        return is_file($name) ? (realpath($name) ?: null) : (new ExecutableFinder)->find($name);
    }
}
