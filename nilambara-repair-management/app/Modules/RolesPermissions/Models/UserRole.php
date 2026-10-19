<?php

namespace App\Modules\RolesPermissions\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends BusinessModel
{
    protected $table = 'role_user';

    protected $fillable = [
        'user_id',
        'role_id',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
