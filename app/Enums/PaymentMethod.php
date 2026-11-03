<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum PaymentMethod: string
{
    use EnumOptions;

    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Online = 'online';
    case Card = 'card';
    case Other = 'other';

    /**
     * Get the display label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer (IBFT)',
            self::Cheque => 'Cheque / pay order',
            self::Cash => 'Cash',
            self::Online => 'Mobile wallet / online',
            self::Card => 'Card',
            self::Other => 'Other',
        };
    }
}
