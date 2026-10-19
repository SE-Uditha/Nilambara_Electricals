<?php

namespace App\Modules\RolesPermissions\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends BusinessModel
{
    protected $table = 'permission_user';

    protected $fillable = [
        'user_id',
        'permission_id',
        'effect',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
