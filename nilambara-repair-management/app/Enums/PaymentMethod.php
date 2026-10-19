<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
