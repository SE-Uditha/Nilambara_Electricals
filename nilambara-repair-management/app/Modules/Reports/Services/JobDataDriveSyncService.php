<?php

namespace App\Modules\Reports\Services;

use App\Modules\BackupRestore\Services\GoogleDriveBackupService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class JobDataDriveSyncService
{
    private const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private readonly JobDataReportService $report,
        private readonly XlsxReportExporter $exporter,
        private readonly GoogleDriveBackupService $googleDrive,
    ) {}

    /**
     * @return array{status: string, rows?: int, date_from?: ?string, date_to?: ?string, finished_at: string}
     */
    public function sync(): array
    {
        $startedAt = now('UTC');
        try {
            $result = $this->perform();
            $status = [
                'status' => 'succeeded',
                'rows' => $result['rows'],
                'date_from' => $result['date_from'],
                'date_to' => $result['date_to'],
                'finished_at' => now('UTC')->toIso8601String(),
            ];
            $this->recordStatus($status);

            return $status;
        } catch (Throwable $exception) {
            $status = [
                'status' => 'failed',
                'error' => $this->safeError($exception),
                'finished_at' => now('UTC')->toIso8601String(),
            ];
            $this->recordStatus($status);

            throw $exception;
        }
    }

    /**
     * @return array{rows: int, date_from: ?string, date_to: ?string}
     */
    private function perform(): array
    {
        if (! config('job_data.enabled')) {
            throw new RuntimeException('Job data Google Drive synchronization is disabled.');
        }

        $workbookId = trim((string) config('job_data.workbook_id'));
        Validator::make(['workbook_id' => $workbookId], [
            'workbook_id' => ['required', 'string', 'max:256', 'regex:/^[A-Za-z0-9_-]+$/'],
        ])->validate();

        if (! $this->googleDrive->oauthConfigured()) {
            throw new RuntimeException('Google Drive OAuth configuration is incomplete.');
        }

        $dates = Validator::make([
            'date_from' => filled(config('job_data.date_from')) ? config('job_data.date_from') : null,
            'date_to' => filled(config('job_data.date_to')) ? config('job_data.date_to') : null,
        ], [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ])->validate();
        $filters = [
            'date_from' => $dates['date_from'] ?? null,
            'date_to' => $dates['date_to'] ?? null,
        ];
        $data = $this->report->build($filters);
        /** @var Builder $query */
        $query = $data['query'];
        $rowCount = (clone $query)->count();
        if ($rowCount > 50000) {
            throw new RuntimeException('The configured workbook scope exceeds the 50,000-job export limit.');
        }

        $path = $this->exporter->create($this->report->rows($query), $data['columns']);
        try {
            $this->uploadWorkbook($path, $workbookId);
        } finally {
            if (is_file($path) && ! @unlink($path)) {
                throw new RuntimeException('The temporary job workbook could not be removed.');
            }
        }

        return ['rows' => $rowCount, 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']];
    }

    private function uploadWorkbook(string $path, string $workbookId): void
    {
        $size = filesize($path);
        $checksum = md5_file($path);
        if (! is_int($size) || $size < 1 || ! is_string($checksum)) {
            throw new RuntimeException('The generated job workbook could not be verified.');
        }

        $token = $this->googleDrive->accessToken();
        $url = 'https://www.googleapis.com/upload/drive/v3/files/'.rawurlencode($workbookId).'?uploadType=resumable&fields=id,mimeType,size,md5Checksum';
        $session = Http::withToken($token)
            ->timeout(30)
            ->retry((int) config('job_data.attempts', 3), (int) config('job_data.retry_delay_ms', 1000), throw: false)
            ->withHeaders([
                'X-Upload-Content-Type' => self::MIME_TYPE,
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->patch($url);
        $location = $session->header('Location');
        if (! $session->successful() || ! is_string($location)
            || parse_url($location, PHP_URL_SCHEME) !== 'https'
            || parse_url($location, PHP_URL_HOST) !== 'www.googleapis.com') {
            throw new RuntimeException('Google Drive could not start the job workbook update (HTTP '.$session->status().').');
        }

        $uploaded = null;
        $attempts = max(1, (int) config('job_data.attempts', 3));
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                throw new RuntimeException('The generated job workbook could not be opened.');
            }
            try {
                $response = Http::withToken($token)
                    ->timeout(600)
                    ->withBody($stream, self::MIME_TYPE)
                    ->put($location);
            } catch (ConnectionException $exception) {
                if ($attempt === $attempts) {
                    throw new RuntimeException('Google Drive workbook upload failed after retries.', previous: $exception);
                }
                usleep(max(0, (int) config('job_data.retry_delay_ms', 1000)) * 1000);

                continue;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if ($response->successful()) {
                $uploaded = $response;

                break;
            }
            if (($response->status() < 500 && $response->status() !== 429) || $attempt === $attempts) {
                throw new RuntimeException('Google Drive workbook upload failed (HTTP '.$response->status().').');
            }
            usleep(max(0, (int) config('job_data.retry_delay_ms', 1000)) * 1000);
        }

        if ($uploaded === null
            || ! hash_equals($workbookId, (string) $uploaded->json('id'))
            || (string) $size !== (string) $uploaded->json('size')
            || ! hash_equals($checksum, (string) $uploaded->json('md5Checksum'))) {
            throw new RuntimeException('Google Drive workbook update failed verification.');
        }
    }

    private function recordStatus(array $status): void
    {
        $statusPath = trim((string) config('job_data.status_path'));
        $logPath = trim((string) config('job_data.log_path'));
        if ($statusPath === '' || $logPath === '') {
            throw new RuntimeException('Job data synchronization status paths are not configured.');
        }

        File::ensureDirectoryExists(dirname($statusPath), 0700, true);
        File::ensureDirectoryExists(dirname($logPath), 0700, true);
        $encoded = json_encode($status, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        File::replace($statusPath, $encoded.PHP_EOL);
        if (file_put_contents($logPath, $encoded.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Job data synchronization status could not be logged.');
        }
    }

    private function safeError(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return 'The configured workbook ID or date range is invalid.';
        }

        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'Synchronization failed; see the application log for details.';
    }
}
