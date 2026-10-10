<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryPurpose: string
{
    case SavingsDeposit = 'savings_deposit';
    case SavingsWithdrawal = 'savings_withdrawal';
    case Investment = 'investment';

    public function label(): string
    {
        return match ($this) {
            self::SavingsDeposit => __('Savings – deposit'),
            self::SavingsWithdrawal => __('Savings – withdrawal'),
            self::Investment => __('Investment'),
        };
    }
}
