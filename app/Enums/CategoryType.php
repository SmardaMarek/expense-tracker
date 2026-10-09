<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Expense => __('Expenses'),
            self::Income => __('Incomes'),
            self::Transfer => __('Transfers'),
        };
    }
}
