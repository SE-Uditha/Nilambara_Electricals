<?php

namespace App\Enums;

enum CustomerType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
