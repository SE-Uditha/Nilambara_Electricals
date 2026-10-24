<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    /** Not stored on payments: credit moves an invoice balance to credit management. */
    case Credit = 'credit';

    /** Methods offered for new payments; cheque is kept only so historical records still load. */
    public static function selectable(): array
    {
        return [self::Cash, self::BankTransfer, self::Credit];
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
