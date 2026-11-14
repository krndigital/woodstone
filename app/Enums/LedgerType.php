<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerType: string
{
    case BillDue = 'bill_due';
    case Payment = 'payment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case SalesReturn = 'sales_return';
    case Opening = 'opening';

    public function label(): string
    {
        return match ($this) {
            self::BillDue => 'Bill on due',
            self::Payment => 'Payment received',
            self::TransferIn => 'Due moved in',
            self::TransferOut => 'Due moved out',
            self::SalesReturn => 'Bill returned',
            self::Opening => 'Opening balance',
        };
    }
}
