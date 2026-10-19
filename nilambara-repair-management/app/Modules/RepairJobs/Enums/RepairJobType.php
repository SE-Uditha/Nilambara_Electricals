<?php

namespace App\Modules\RepairJobs\Enums;

enum RepairJobType: string
{
    case Normal = 'normal';
    case Special = 'special';
    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
