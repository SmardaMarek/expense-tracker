<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\CategoryPurpose;
use App\Enums\TransactionType;
use App\Models\Transaction;

final readonly class Totals
{
    public function __construct(
        public int $income = 0,
        public int $expenses = 0,
        public int $saved = 0,
        public int $invested = 0,
        public int $movement = 0,
    ) {}

    /**
     * @param  iterable<Transaction>  $transactions
     */
    public static function of(iterable $transactions): self
    {
        $income = 0;
        $expenses = 0;
        $saved = 0;
        $invested = 0;
        $movement = 0;

        foreach ($transactions as $transaction) {
            $amount = $transaction->amount;
            $movement += $amount;

            match ($transaction->type) {
                TransactionType::Income => $income += $amount,
                TransactionType::Expense => $expenses -= $amount,
                TransactionType::Transfer => null,
            };

            if ($transaction->type !== TransactionType::Transfer) {
                continue;
            }

            $purpose = $transaction->category?->purpose;
            $outgoing = $amount < 0;

            if ($purpose === CategoryPurpose::SavingsDeposit && $outgoing) {
                $saved -= $amount;
            } elseif ($purpose === CategoryPurpose::SavingsWithdrawal && ! $outgoing) {
                $saved -= $amount;
            } elseif ($purpose === CategoryPurpose::Investment && $outgoing) {
                $invested -= $amount;
            }
        }

        return new self($income, $expenses, $saved, $invested, $movement);
    }

    public function balance(): int
    {
        return $this->income - $this->expenses;
    }
}
