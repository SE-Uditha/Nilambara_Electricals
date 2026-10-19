<?php

namespace App\Enums;

enum QuotationPreparationStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
