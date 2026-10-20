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
        'file_size', 'completed_at', 'deleted_at', 'is_protected', 'verification_status', 'cloud_file_id', 'cloud_status', 'destination_path',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected $hidden = ['destination_path'];

    protected function casts(): array
    {
        return [
            'backup_at' => 'datetime',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'completed_at' => 'datetime', 'deleted_at' => 'datetime', 'is_protected' => 'boolean', 'file_size' => 'integer',
            'destination_path' => 'encrypted',
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
