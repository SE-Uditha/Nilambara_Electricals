<?php

namespace App\Modules\BackupRestore\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestore extends BusinessModel
{
    protected $table = 'backup_restores';

    protected $fillable = [
        'backup_history_id',
        'initiated_by_user_id',
        'confirmed_at',
        'started_at',
        'completed_at',
        'status',
        'error_details',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function backupHistory(): BelongsTo
    {
        return $this->belongsTo(BackupHistory::class, 'backup_history_id');
    }

    public function initiatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
