<?php

namespace App\Console\Commands;

use App\Modules\Reports\Services\JobDataDriveSyncService;
use Illuminate\Console\Command;

class SyncJobDataWorkbook extends Command
{
    protected $signature = 'jobs:sync-drive';

    protected $description = 'Update the configured Google Drive job-data workbook';

    public function handle(JobDataDriveSyncService $sync): int
    {
        $status = $sync->sync();
        $this->info('Job workbook updated with '.$status['rows'].' jobs.');

        return self::SUCCESS;
    }
}
