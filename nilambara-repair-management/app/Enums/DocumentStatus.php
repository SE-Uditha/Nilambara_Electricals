<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
