<?php

declare(strict_types=1);

namespace App\Reports;

use App\Recurring\RecurringState;

final readonly class MonthlySummaryData
{
    /**
     * @param  list<CategoryAmount>  $expenseCategories
     * @param  list<CategoryAmount>  $incomeCategories
     * @param  list<TransferAmount>  $transferCategories
     * @param  list<OwnerTotals>  $owners
     * @param  list<TrendPoint>  $trend
     * @param  list<RecurringState>  $recurring
     */
    public function __construct(
        public Totals $current,
        public Totals $previous,
        public array $expenseCategories,
        public array $incomeCategories,
        public array $transferCategories,
        public array $owners,
        public array $trend,
        public MoneyFlow $flow,
        public array $recurring = [],
        public int $fixedExpenses = 0,
        public int $previousFixedExpenses = 0,
    ) {}

    public function leftAfterFixed(): int
    {
        return $this->current->income - $this->fixedExpenses;
    }

    public function previousLeftAfterFixed(): int
    {
        return $this->previous->income - $this->previousFixedExpenses;
    }
}
