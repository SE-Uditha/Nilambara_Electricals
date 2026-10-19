<?php

namespace App\Modules\RolesPermissions\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends BusinessModel
{
    protected $table = 'permissions';

    protected $fillable = [
        'code',
        'name',
        'module',
        'description',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function permissionRole(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'permission_id');
    }

    public function permissionUser(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'permission_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role', 'permission_id', 'role_id')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user', 'permission_id', 'user_id')->withPivot(['effect'])->withTimestamps();
    }
}
