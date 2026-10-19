<?php

namespace App\Modules\RolesPermissions\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['code', 'name', 'label', 'description', 'is_active', 'guard_name'];

    public function getLabelAttribute(): string
    {
        return $this->attributes['label'] ?? str($this->name)->headline()->toString();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
