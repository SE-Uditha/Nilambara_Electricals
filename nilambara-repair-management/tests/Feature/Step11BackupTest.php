<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\BackupRestore\Models\BackupHistory;
use App\Modules\BackupRestore\Services\BackupService;
use App\Modules\BackupRestore\Services\GoogleDriveBackupService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Step11BackupTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    private ?string $root = null;

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if ($this->root !== null && is_dir($this->root)) {
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function backup(int $days, bool $manual = false): BackupHistory
    {
        if ($this->root === null) {
            $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nilambara-backup-test-'.bin2hex(random_bytes(6));
            mkdir($this->root, 0700);
            config(['backup.root' => $this->root]);
        }
        $name = bin2hex(random_bytes(6)).'.sql';
        $path = $this->root.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, '-- isolated backup verification fixture');
        $this->files[] = $path;

        return BackupHistory::create(['backup_at' => now()->subDays($days), 'file_reference' => $name,
            'backup_type' => $manual ? 'manual' : 'scheduled', 'status' => 'completed',
            'storage_location' => 'private-backup-root', 'checksum_sha256' => hash_file('sha256', $path),
            'verified_at' => now()->subDays($days), 'file_size' => filesize($path), 'is_protected' => $manual,
            'destination_path' => $this->root, 'verification_status' => 'checksum_verified']);
    }

    public function test_retention_preserves_manual_and_latest_recoverable_copy_and_keeps_history(): void
    {
        $manual = $this->backup(90, true);
        $expired = $this->backup(31);
        $latest = $this->backup(0);
        app(BackupService::class)->cleanupVerifiedBackups();
        $this->assertTrue(app(BackupService::class)->verify($manual));
        $this->assertTrue(app(BackupService::class)->verify($latest));
        $this->assertFalse(app(BackupService::class)->verify($expired->fresh()));
        $this->assertNotNull($expired->fresh()->deleted_at);
        $this->assertDatabaseCount('backup_histories', 3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.cleanup.deleted']);
    }

    public function test_changed_missing_or_path_traversal_backups_fail_verification(): void
    {
        $backup = $this->backup(0);
        $this->assertTrue(app(BackupService::class)->verify($backup));
        file_put_contents($this->files[0], 'changed');
        $this->assertFalse(app(BackupService::class)->verify($backup));
        $backup->file_reference = '../other.sql';
        $this->assertFalse(app(BackupService::class)->verify($backup));
        $this->assertArrayNotHasKey('destination_path', $backup->toArray());
    }

    public function test_private_destination_validation_rejects_all_web_roots(): void
    {
        $service = app(BackupService::class);
        $this->assertFalse($service->isSafeDestination(public_path('backups')));
        $this->assertFalse($service->isSafeDestination(dirname(dirname(base_path())).DIRECTORY_SEPARATOR.'backups'));
        $this->assertFalse($service->isSafeDestination('../backups'));
    }

    public function test_google_drive_stays_disabled_when_configuration_is_blank(): void
    {
        config(['backup.google_drive' => []]);
        Http::preventStrayRequests();
        $backup = $this->backup(0);
        $this->assertFalse(app(GoogleDriveBackupService::class)->configured());
        app(GoogleDriveBackupService::class)->copy($backup, $this->files[0]);
        Http::assertNothingSent();
        $this->assertSame('not_configured', $backup->fresh()->cloud_status);
    }

    public function test_google_drive_adapter_checks_remote_checksum_and_size_with_mocked_api(): void
    {
        $backup = $this->backup(0);
        config(['backup.google_drive' => ['folder_id' => 'test-folder', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'refresh_token' => 'test-refresh']]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'www.googleapis.com/upload/drive/v3/files?*' => Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/session-test']),
            'www.googleapis.com/upload/session-test' => Http::response(['id' => 'test-file', 'md5Checksum' => md5_file($this->files[0]), 'size' => (string) filesize($this->files[0])]),
        ]);
        app(GoogleDriveBackupService::class)->copy($backup, $this->files[0]);
        $this->assertSame('verified', $backup->fresh()->cloud_status);
        $this->assertSame('test-file', $backup->fresh()->cloud_file_id);
    }

    public function test_backup_configuration_is_administrator_only_and_does_not_reveal_paths(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user)->post(route('backups.configure'), [])->assertForbidden();
        $this->get('/admin/backups')->assertForbidden();
        $user->syncRoles(['administrator']);
        $this->backup(0);
        $this->post(route('backups.configure'), ['root' => $this->root, 'schedule_time' => '02:00', 'retention_days' => 30, 'maximum_backups' => 30])->assertSessionHasNoErrors();
        $this->get('/admin/backups')->assertOk()->assertDontSee($this->root)->assertSee('Google Drive');
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.configuration.updated']);
    }
}
