<?php

namespace App\Modules\RolesPermissions\Models;

use App\Models\BusinessModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends BusinessModel
{
    protected $table = 'permission_role';

    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
