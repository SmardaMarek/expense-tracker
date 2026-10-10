<?php

declare(strict_types=1);

namespace App\Reports;

final readonly class MoneyFlow
{
    public const HUB = 'hub';

    public const SAVED = 'saved';

    public const INVESTED = 'invested';

    public const LEFT_OVER = 'left_over';

    public const FROM_SAVINGS = 'from_savings';

    public const FROM_RESERVES = 'from_reserves';

    public const OTHER_EXPENSES = 'expense:other';

    /**
     * @param  list<array{key: string, label: string, group: string, categoryId: int|null}>  $nodes
     * @param  list<array{source: string, target: string, value: int}>  $links
     */
    public function __construct(
        public array $nodes = [],
        public array $links = [],
    ) {}

    /**
     * @param  list<CategoryAmount>  $incomes
     * @param  list<CategoryAmount>  $expenses
     */
    public static function from(Totals $totals, array $incomes, array $expenses, int $topExpenses): self
    {
        if ($totals->income === 0 && $totals->expenses === 0 && $totals->saved === 0 && $totals->invested === 0) {
            return new self;
        }

        $nodes = [self::node(self::HUB, __('Available money'), 'hub')];
        $links = [];

        foreach ($incomes as $income) {
            $key = 'income:'.($income->categoryId ?? 'none');
            $nodes[] = self::node($key, $income->name, 'income', $income->categoryId);
            $links[] = self::link($key, self::HUB, $income->amount);
        }

        $fromSavings = max(-$totals->saved, 0);
        $outgoing = $totals->expenses + max($totals->saved, 0) + $totals->invested;
        $available = $totals->income + $fromSavings;

        if ($fromSavings > 0) {
            $nodes[] = self::node(self::FROM_SAVINGS, __('Taken from savings'), 'savings');
            $links[] = self::link(self::FROM_SAVINGS, self::HUB, $fromSavings);
        }

        if ($outgoing > $available) {
            $nodes[] = self::node(self::FROM_RESERVES, __('From earlier money'), 'reserves');
            $links[] = self::link(self::FROM_RESERVES, self::HUB, $outgoing - $available);
        }

        $otherExpenses = 0;

        foreach ($expenses as $index => $expense) {
            if ($index >= $topExpenses) {
                $otherExpenses += $expense->amount;

                continue;
            }

            $key = 'expense:'.($expense->categoryId ?? 'none');
            $nodes[] = self::node($key, $expense->name, 'expense', $expense->categoryId);
            $links[] = self::link(self::HUB, $key, $expense->amount);
        }

        if ($otherExpenses > 0) {
            $nodes[] = self::node(self::OTHER_EXPENSES, __('Other expenses'), 'expense');
            $links[] = self::link(self::HUB, self::OTHER_EXPENSES, $otherExpenses);
        }

        if ($totals->saved > 0) {
            $nodes[] = self::node(self::SAVED, __('Saved'), 'savings');
            $links[] = self::link(self::HUB, self::SAVED, $totals->saved);
        }

        if ($totals->invested > 0) {
            $nodes[] = self::node(self::INVESTED, __('Invested'), 'investment');
            $links[] = self::link(self::HUB, self::INVESTED, $totals->invested);
        }

        if ($available > $outgoing) {
            $nodes[] = self::node(self::LEFT_OVER, __('Left over'), 'left_over');
            $links[] = self::link(self::HUB, self::LEFT_OVER, $available - $outgoing);
        }

        return new self($nodes, $links);
    }

    public function isEmpty(): bool
    {
        return $this->links === [];
    }

    /**
     * @return array{key: string, label: string, group: string, categoryId: int|null}
     */
    private static function node(string $key, string $label, string $group, ?int $categoryId = null): array
    {
        return ['key' => $key, 'label' => $label, 'group' => $group, 'categoryId' => $categoryId];
    }

    /**
     * @return array{source: string, target: string, value: int}
     */
    private static function link(string $source, string $target, int $value): array
    {
        return ['source' => $source, 'target' => $target, 'value' => $value];
    }
}
