<?php

declare(strict_types=1);

namespace App\Reports;

use App\Transactions\TransactionFilter;

final readonly class DashboardCharts
{
    public const DONUT_SLICES = 8;

    public function __construct(private TransactionFilter $filter) {}

    /**
     * @return array{total: int, totalLabel: string, items: list<array{name: string, value: int, url: string|null}>}
     */
    public function expenseDonut(MonthlySummaryData $summary): array
    {
        $items = [];
        $other = 0;

        foreach ($summary->expenseCategories as $index => $row) {
            if ($index >= self::DONUT_SLICES) {
                $other += $row->amount;

                continue;
            }

            $items[] = ['name' => $row->name, 'value' => $row->amount, 'url' => $this->categoryUrl($row->categoryId)];
        }

        if ($other > 0) {
            $items[] = ['name' => __('Other expenses'), 'value' => $other, 'url' => null];
        }

        return ['total' => $summary->current->expenses, 'totalLabel' => __('Expenses'), 'items' => $items];
    }

    /**
     * @return array{labels: list<string>, series: list<array{key: string, name: string, values: list<int>}>}
     */
    public function trend(MonthlySummaryData $summary): array
    {
        $series = [
            'income' => __('Incomes'),
            'expenses' => __('Expenses'),
            'saved' => __('Saved'),
            'invested' => __('Invested'),
        ];

        return [
            'labels' => array_map(fn (TrendPoint $point): string => $point->label, $summary->trend),
            'series' => array_map(fn (string $key, string $name): array => [
                'key' => $key,
                'name' => $name,
                'values' => array_map(fn (TrendPoint $point): int => $point->totals->{$key}, $summary->trend),
            ], array_keys($series), $series),
        ];
    }

    /**
     * @return array{nodes: list<array{name: string, label: string, group: string, url: string|null}>, links: list<array{source: string, target: string, value: int}>}
     */
    public function flow(MonthlySummaryData $summary): array
    {
        return [
            'nodes' => array_map(fn (array $node): array => [
                'name' => $node['key'],
                'label' => $node['label'],
                'group' => $node['group'],
                'url' => in_array($node['group'], ['income', 'expense'], true) && $node['key'] !== MoneyFlow::OTHER_EXPENSES
                    ? $this->categoryUrl($node['categoryId'])
                    : null,
            ], $summary->flow->nodes),
            'links' => $summary->flow->links,
        ];
    }

    public function categoryUrl(?int $categoryId): string
    {
        return route('transactions', array_filter([
            'month' => $this->filter->month->format('Y-m'),
            'account' => $this->filter->accountId,
            'owner' => $this->filter->owner,
            'category' => $categoryId ?? TransactionFilter::UNCATEGORIZED,
        ], fn (mixed $value): bool => $value !== null));
    }
}
