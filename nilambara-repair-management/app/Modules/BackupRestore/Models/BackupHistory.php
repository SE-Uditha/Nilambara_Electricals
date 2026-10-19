<?php

namespace App\Modules\BackupRestore\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupHistory extends BusinessModel
{
    protected $table = 'backup_histories';

    protected $fillable = [
        'backup_at',
        'file_reference',
        'backup_type',
        'status',
        'storage_location',
        'checksum_sha256',
        'verified_at',
        'error_details',
        'initiated_by_user_id',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'backup_at' => 'datetime',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function initiatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function backupRestores(): HasMany
    {
        return $this->hasMany(BackupRestore::class, 'backup_history_id');
    }
}
