<?php

namespace App\Support;

use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class RepairJobTransactionGuard
{
    public const MESSAGE = 'This operation is temporarily unavailable because the repair-job database needs administrator attention. No changes were made.';

    public function ensureSafe(?ConnectionInterface $connection = null): void
    {
        $connection ??= (new RepairJob)->getConnection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $engine = $connection->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', 'repair_jobs')
            ->value('ENGINE');

        if (! is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
            Log::error('Repair job operation blocked: the repair_jobs table is not transactional InnoDB.', [
                'connection' => $connection->getName(),
                'engine' => $engine,
            ]);

            throw new ServiceUnavailableHttpException(null, self::MESSAGE);
        }
    }
}
