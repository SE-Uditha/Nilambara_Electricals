<?php

namespace Tests\Feature;

use App\Modules\Reports\Services\JobDataDriveSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class JobDataDriveSyncTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private string $syncDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncDirectory = storage_path('framework/testing/job-data-sync-'.Str::uuid());
        config([
            'job_data.enabled' => true,
            'job_data.workbook_id' => 'designated-workbook-123',
            'job_data.date_from' => '2026-10-18',
            'job_data.date_to' => '2026-10-18',
            'job_data.status_path' => $this->syncDirectory.DIRECTORY_SEPARATOR.'status.json',
            'job_data.log_path' => $this->syncDirectory.DIRECTORY_SEPARATOR.'sync.jsonl',
            'backup.google_drive.client_id' => 'test-client-id',
            'backup.google_drive.client_secret' => 'test-client-secret',
            'backup.google_drive.refresh_token' => 'test-refresh-token',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->syncDirectory.DIRECTORY_SEPARATOR.'status.json');
        @unlink($this->syncDirectory.DIRECTORY_SEPARATOR.'sync.jsonl');
        @rmdir($this->syncDirectory);
        parent::tearDown();
    }

    public function test_sync_updates_the_designated_workbook_with_inclusive_scope_without_private_notes(): void
    {
        $this->job(null, [
            'job_number' => 'HISTORIC-JOB-42',
            'received_at' => '2026-10-18 17:45:00',
            'private_notes' => 'INTERNAL-ONLY-DO-NOT-EXPORT',
        ]);

        $workbook = '';
        Http::fake(function (ClientRequest $request) use (&$workbook) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'test-access-token'], 200);
            }
            if (str_contains($request->url(), 'uploadType=resumable')) {
                return Http::response('', 200, [
                    'Location' => 'https://www.googleapis.com/upload/drive/v3/files/resumable-session',
                ]);
            }
            if (str_contains($request->url(), '/resumable-session')) {
                $workbook = $request->body();

                return Http::response([
                    'id' => 'designated-workbook-123',
                    'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'size' => (string) strlen($workbook),
                    'md5Checksum' => md5($workbook),
                ], 200);
            }

            return Http::response([], 404);
        });

        $status = app(JobDataDriveSyncService::class)->sync();

        $this->assertSame('succeeded', $status['status']);
        $this->assertSame(1, $status['rows']);
        $this->assertSame('2026-10-18', $status['date_from']);
        $this->assertSame('2026-10-18', $status['date_to']);
        $this->assertFileExists(config('job_data.status_path'));
        $this->assertFileExists(config('job_data.log_path'));
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/files/designated-workbook-123?uploadType=resumable'));

        $xlsxPath = tempnam(sys_get_temp_dir(), 'job-data-test-');
        $this->assertIsString($xlsxPath);
        file_put_contents($xlsxPath, $workbook);
        try {
            $spreadsheet = IOFactory::load($xlsxPath);
            $values = $spreadsheet->getActiveSheet()->toArray();
            $flattened = implode("\n", array_map(
                fn (array $row): string => implode(' ', array_map(static fn ($value): string => (string) $value, $row)),
                $values,
            ));
            $spreadsheet->disconnectWorksheets();
            $this->assertStringContainsString('HISTORIC-JOB-42', $flattened);
            $this->assertStringNotContainsString('INTERNAL-ONLY-DO-NOT-EXPORT', $flattened);
        } finally {
            @unlink($xlsxPath);
        }
    }

    public function test_sync_failure_is_recorded_and_propagated(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([], 503),
        ]);

        try {
            app(JobDataDriveSyncService::class)->sync();
            $this->fail('A failed Google Drive authentication must not report a successful sync.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Google Drive authentication failed.', $exception->getMessage());
        }

        $status = json_decode(file_get_contents(config('job_data.status_path')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('failed', $status['status']);
        $this->assertSame('Google Drive authentication failed.', $status['error']);
        $this->assertFileExists(config('job_data.log_path'));
    }
}
