<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Member;
use App\Models\Transaction;
use App\Transactions\TransactionFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MonthlySummary
{
    public const TREND_MONTHS = 12;

    public const TOP_FLOW_EXPENSES = 8;

    private const MONTH_KEY = 'Y-m';

    public function build(TransactionFilter $filter): MonthlySummaryData
    {
        $month = $filter->month;
        $trendStart = $month->subMonths(self::TREND_MONTHS - 1);

        $transactions = $filter->applyAccountScope(Transaction::query())
            ->with(['category:id,name,type,purpose', 'bankAccount:id,member_id'])
            ->where('booked_on', '>=', $trendStart->toDateString())
            ->where('booked_on', '<', $month->addMonth()->toDateString())
            ->get();

        $byMonth = $transactions->groupBy(fn (Transaction $transaction): string => $transaction->booked_on->format(self::MONTH_KEY));
        $currentMonth = $byMonth->get($month->format(self::MONTH_KEY), collect());

        $current = Totals::of($currentMonth);
        $expenseCategories = $this->categoryAmounts($currentMonth, TransactionType::Expense);
        $incomeCategories = $this->categoryAmounts($currentMonth, TransactionType::Income);

        return new MonthlySummaryData(
            current: $current,
            previous: Totals::of($byMonth->get($month->subMonth()->format(self::MONTH_KEY), collect())),
            expenseCategories: $expenseCategories,
            incomeCategories: $incomeCategories,
            transferCategories: $this->transferAmounts($currentMonth),
            owners: $filter->coversAllAccounts() ? $this->ownerTotals($currentMonth) : [],
            trend: $this->trend($byMonth, $trendStart),
            flow: MoneyFlow::from($current, $incomeCategories, $expenseCategories, self::TOP_FLOW_EXPENSES),
        );
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<CategoryAmount>
     */
    private function categoryAmounts(Collection $transactions, TransactionType $type): array
    {
        $ofType = $transactions->filter(fn (Transaction $transaction): bool => $transaction->type === $type);
        $total = abs((int) $ofType->sum('amount'));

        return $ofType
            ->groupBy(fn (Transaction $transaction): int => $transaction->category_id ?? 0)
            ->map(function (Collection $group) use ($total): CategoryAmount {
                $first = $group->first();
                $amount = abs((int) $group->sum('amount'));

                return new CategoryAmount(
                    $first?->category_id,
                    $first?->category->name ?? __('Uncategorized'),
                    $amount,
                    $total > 0 ? $amount / $total : 0.0,
                );
            })
            ->sortByDesc(fn (CategoryAmount $row): int => $row->amount)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<TransferAmount>
     */
    private function transferAmounts(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->type === TransactionType::Transfer)
            ->groupBy(fn (Transaction $transaction): int => $transaction->category_id ?? 0)
            ->map(function (Collection $group): TransferAmount {
                $first = $group->first();

                return new TransferAmount(
                    $first?->category_id,
                    $first?->category->name ?? __('Uncategorized'),
                    -(int) $group->where('amount', '<', 0)->sum('amount'),
                    (int) $group->where('amount', '>', 0)->sum('amount'),
                );
            })
            ->sortByDesc(fn (TransferAmount $row): int => max($row->outgoing, $row->incoming))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<OwnerTotals>
     */
    private function ownerTotals(Collection $transactions): array
    {
        $ownerIds = BankAccount::query()->distinct()->pluck('member_id');
        $owners = Member::query()
            ->whereIn('id', $ownerIds->filter())
            ->orderBy('position')
            ->get()
            ->map(fn (Member $member): array => [(string) $member->id, $member->name, $member->id])
            ->all();

        if ($ownerIds->contains(null)) {
            $owners[] = [TransactionFilter::SHARED, __('Shared'), null];
        }

        return array_map(function (array $owner) use ($transactions): OwnerTotals {
            [$key, $name, $memberId] = $owner;
            $owned = $transactions->filter(fn (Transaction $transaction): bool => $transaction->bankAccount->member_id === $memberId);
            $transfers = $owned->filter(fn (Transaction $transaction): bool => $transaction->type === TransactionType::Transfer);

            return new OwnerTotals(
                $key,
                $name,
                Totals::of($owned),
                -(int) $transfers->where('amount', '<', 0)->sum('amount'),
                (int) $transfers->where('amount', '>', 0)->sum('amount'),
            );
        }, $owners);
    }

    /**
     * @param  Collection<string, Collection<int, Transaction>>  $byMonth
     * @return list<TrendPoint>
     */
    private function trend(Collection $byMonth, CarbonImmutable $trendStart): array
    {
        $points = [];

        for ($offset = 0; $offset < self::TREND_MONTHS; $offset++) {
            $month = $trendStart->addMonths($offset);
            $key = $month->format(self::MONTH_KEY);

            $points[] = new TrendPoint(
                $key,
                $month->locale(app()->getLocale())->isoFormat('MMM YY'),
                Totals::of($byMonth->get($key, collect())),
            );
        }

        return $points;
    }
}
