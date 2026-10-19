<?php

namespace App\Modules\RolesPermissions\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends BusinessModel
{
    protected $table = 'roles';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected array $immutableFields = ['id', 'code'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function roleUser(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function permissionRole(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id')->withTimestamps();
    }
}
