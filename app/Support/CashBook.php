<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of the cash book (FR-9.2). Every sale payment, due receipt, purchase payment,
 * expense, salary, refund, transfer and manual entry ends up here as one append-only row.
 */
final class CashBook
{
    /** Payment method → account code. */
    private const METHOD_ACCOUNT = ['cash' => 'cash', 'qr' => 'qr', 'card' => 'card', 'bank' => 'bank'];

    public static function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    public static function accountForMethod(string $method): Account
    {
        $code = self::METHOD_ACCOUNT[$method] ?? throw new InvalidArgumentException("No account for payment method {$method}");

        return self::account($code);
    }

    public static function post(Account $account, int $paisa, string $sourceType, ?int $sourceId, User $by, ?string $note = null, ?string $category = null, ?string $businessDate = null): AccountTransaction
    {
        $row = new AccountTransaction;
        $row->forceFill([
            'account_id' => $account->id,
            'amount' => Money::fromPaisa($paisa),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'category' => $category,
            'note' => $note,
            'business_date' => $businessDate ?? now()->toDateString(),
            'created_by' => $by->id,
            'created_at' => now(),
        ])->save();

        return $row;
    }

    /**
     * Balance in paisa, optionally only rows on or before a date, or created after a moment.
     */
    public static function balance(Account $account, ?string $upToDate = null, ?string $sinceTimestamp = null, bool $excludeDayClose = false): int
    {
        $sum = DB::table('account_transactions')
            ->where('account_id', $account->id)
            ->when($upToDate !== null, fn ($q) => $q->where('business_date', '<=', $upToDate))
            ->when($sinceTimestamp !== null, fn ($q) => $q->where('created_at', '>=', $sinceTimestamp))
            ->when($excludeDayClose, fn ($q) => $q->where('source_type', '!=', 'day_close'))
            ->sum('amount');

        return Money::fromDbSum($sum);
    }
}
