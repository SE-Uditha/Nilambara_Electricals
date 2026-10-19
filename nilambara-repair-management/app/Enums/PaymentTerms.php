<?php

namespace App\Enums;

enum PaymentTerms: string
{
    case Cash = 'cash';
    case Credit = 'credit';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
