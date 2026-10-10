<?php

declare(strict_types=1);

namespace App\Reports;

final readonly class MonthlySummaryData
{
    /**
     * @param  list<CategoryAmount>  $expenseCategories
     * @param  list<CategoryAmount>  $incomeCategories
     * @param  list<TransferAmount>  $transferCategories
     * @param  list<OwnerTotals>  $owners
     * @param  list<TrendPoint>  $trend
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
    ) {}
}
