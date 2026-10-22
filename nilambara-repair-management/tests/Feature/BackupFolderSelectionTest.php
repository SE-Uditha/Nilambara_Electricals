<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\BackupRestore\Models\BackupHistory;
use App\Modules\BackupRestore\Services\BackupService;
use App\Modules\Settings\Models\SystemSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class BackupFolderSelectionTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nilambara-picker-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0700);
        mkdir($this->root.DIRECTORY_SEPARATOR.'Old backups', 0700);
        mkdir($this->root.DIRECTORY_SEPARATOR.'New backups', 0700);
        file_put_contents($this->root.DIRECTORY_SEPARATOR.'private-file.txt', 'Do not list file contents.');
    }

    protected function tearDown(): void
    {
        foreach (['Old backups', 'New backups'] as $folder) {
            $path = $this->root.DIRECTORY_SEPARATOR.$folder;
            foreach (glob($path.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($path);
        }
        unlink($this->root.DIRECTORY_SEPARATOR.'private-file.txt');
        rmdir($this->root);
        parent::tearDown();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function policy(array $extra = []): array
    {
        return $extra + ['schedule_time' => '02:00', 'retention_days' => 30, 'maximum_backups' => 30];
    }

    public function test_only_authorized_administrators_can_browse_pc_folders(): void
    {
        $this->actor('cashier');
        $this->getJson(route('backups.folders', ['path' => $this->root]))->assertForbidden();
        $this->actor('cashier');
        $this->getJson(route('backups.folders', ['path' => $this->root]))->assertForbidden();
        $this->actor('administrator');
        $response = $this->getJson(route('backups.folders', ['path' => $this->root]))->assertOk()->assertJsonPath('path', realpath($this->root))->assertJsonPath('selectable', true)->assertJsonCount(2, 'folders');
        $response->assertDontSee('private-file.txt')->assertDontSee('Do not list file contents.');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->json('roots'));
    }

    public function test_picker_uses_current_folder_and_lists_child_directories(): void
    {
        $this->actor('administrator');
        config(['backup.root' => $this->root]);
        $this->getJson(route('backups.folders'))->assertOk()->assertJsonPath('path', realpath($this->root));
        $this->getJson(route('backups.folders', ['path' => $this->root.DIRECTORY_SEPARATOR.'Old backups']))->assertOk()->assertJsonCount(0, 'folders')->assertJsonPath('parent', realpath($this->root));
        $this->getJson(route('backups.folders', ['path' => '../relative']))->assertUnprocessable();
        $this->getJson(route('backups.folders', ['path' => $this->root.DIRECTORY_SEPARATOR.'not-found']))->assertUnprocessable();
        $this->getJson(route('backups.folders', ['path' => [$this->root]]))->assertUnprocessable();
    }

    public function test_custom_destination_is_saved_encrypted_and_no_backup_is_created_on_save(): void
    {
        $this->actor('administrator');
        $destination = $this->root.DIRECTORY_SEPARATOR.'New backups';
        $this->post(route('backups.configure'), $this->policy(['root' => '"'.$destination.'"']))->assertRedirect()->assertSessionHasNoErrors();
        $setting = SystemSetting::where('group', 'backup')->where('key', 'root')->sole();
        $this->assertSame(realpath($destination), Crypt::decryptString($setting->value));
        $this->assertNotSame($destination, $setting->value);
        $this->assertDatabaseCount('backup_histories', 0);
        $this->get(route('backups.index'))->assertOk()->assertSee('Browse folders')->assertSee('Change backup location');
        $this->post(route('backups.configure'), $this->policy())->assertSessionHasNoErrors();
        $this->assertSame($setting->value, $setting->fresh()->value);
    }

    public function test_new_subfolder_can_be_selected_without_creating_it_on_save(): void
    {
        $this->actor('administrator');
        $destination = $this->root.DIRECTORY_SEPARATOR.'Future backups';
        $this->post(route('backups.configure'), $this->policy(['root' => $destination]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDirectoryDoesNotExist($destination);
        $this->assertSame($destination, config('backup.root'));
    }

    public function test_invalid_destinations_return_field_errors_without_changing_policy(): void
    {
        $this->actor('administrator');
        foreach ([public_path('backups'), '../relative', $this->root.DIRECTORY_SEPARATOR.'private-file.txt', $this->root.DIRECTORY_SEPARATOR.'missing'.DIRECTORY_SEPARATOR.'child', $this->root."\0bad"] as $destination) {
            $this->postJson(route('backups.configure'), $this->policy(['root' => $destination]))->assertUnprocessable()->assertJsonValidationErrors('root');
        }
        if (chr(92) === DIRECTORY_SEPARATOR) {
            foreach (['CON', 'bad:name', 'trailing.'] as $name) {
                $this->postJson(route('backups.configure'), $this->policy(['root' => $this->root.DIRECTORY_SEPARATOR.$name]))->assertUnprocessable()->assertJsonValidationErrors('root');
            }
        }
        $this->assertSame(0, SystemSetting::where('group', 'backup')->count());
    }

    public function test_old_backups_remain_verifiable_after_destination_change(): void
    {
        $this->actor('administrator');
        $oldRoot = $this->root.DIRECTORY_SEPARATOR.'Old backups';
        $file = $oldRoot.DIRECTORY_SEPARATOR.'existing.sql';
        file_put_contents($file, '-- existing backup fixture');
        $backup = BackupHistory::create(['backup_at' => now(), 'file_reference' => 'existing.sql', 'backup_type' => 'manual', 'status' => 'completed', 'storage_location' => 'private-backup-root', 'destination_path' => $oldRoot, 'checksum_sha256' => hash_file('sha256', $file), 'verified_at' => now(), 'is_protected' => true]);
        $this->post(route('backups.configure'), $this->policy(['root' => $this->root.DIRECTORY_SEPARATOR.'New backups']))->assertSessionHasNoErrors();
        $this->assertTrue(app(BackupService::class)->verify($backup->fresh()));
        $this->assertFileExists($file);
        $this->assertSame($oldRoot, $backup->fresh()->destination_path);
    }
}
