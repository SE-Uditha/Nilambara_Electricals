<?php

namespace App\Modules\RolesPermissions\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = ['code', 'name', 'label', 'module', 'description', 'guard_name'];

    public function getLabelAttribute(): string
    {
        return $this->attributes['label'] ?? str($this->name)->replace('.', ' ')->headline()->toString();
    }

    protected function casts(): array
    {
        return [];
    }
}
