<?php

namespace App\Modules\BackupRestore\Services;

use App\Modules\BackupRestore\Models\BackupHistory;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveBackupService
{
    public function configured(): bool
    {
        foreach (['folder_id', 'client_id', 'client_secret', 'refresh_token'] as $key) {
            if (trim((string) config('backup.google_drive.'.$key)) === '') {
                return false;
            }
        }

        return true;
    }

    public function copy(BackupHistory $backup, string $path): void
    {
        if (! $this->configured()) {
            return;
        }
        $backup->update(['cloud_status' => 'uploading']);
        $tokenResponse = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('backup.google_drive.client_id'),
            'client_secret' => config('backup.google_drive.client_secret'),
            'refresh_token' => config('backup.google_drive.refresh_token'),
        ]);
        $token = $tokenResponse->json('access_token');
        if (! $tokenResponse->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Google Drive authentication failed.');
        }
        $session = Http::withToken($token)->timeout(30)->withHeaders([
            'X-Upload-Content-Type' => 'application/sql', 'X-Upload-Content-Length' => (string) filesize($path),
        ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,md5Checksum,size', [
            'name' => basename($path), 'parents' => [config('backup.google_drive.folder_id')],
            'appProperties' => ['sha256' => $backup->checksum_sha256],
        ]);
        $location = $session->header('Location');
        if (! $session->successful() || ! is_string($location)
            || parse_url($location, PHP_URL_SCHEME) !== 'https'
            || parse_url($location, PHP_URL_HOST) !== 'www.googleapis.com') {
            throw new RuntimeException('Google Drive upload could not be started.');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The verified local backup could not be read.');
        }
        try {
            $uploaded = Http::withToken($token)->timeout(600)->withBody($stream, 'application/sql')->put($location);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $uploaded->successful() || ! $uploaded->json('id')
            || ! hash_equals((string) md5_file($path), (string) $uploaded->json('md5Checksum'))
            || (string) filesize($path) !== (string) $uploaded->json('size')) {
            throw new RuntimeException('Google Drive copy did not pass checksum and size verification.');
        }
        $backup->update(['cloud_file_id' => $uploaded->json('id'), 'cloud_status' => 'verified']);
    }
}
