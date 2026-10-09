<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';

    public function categoryType(): CategoryType
    {
        return match ($this) {
            self::Expense => CategoryType::Expense,
            self::Income => CategoryType::Income,
            self::Transfer => CategoryType::Transfer,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Expense => __('Expense'),
            self::Income => __('Income'),
            self::Transfer => __('Transfer'),
        };
    }
}
