<?php

declare(strict_types=1);

namespace App\Recurring;

use App\Enums\RecurringStatus;
use App\Enums\TransactionKind;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Transactions\TransactionFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RecurringOverview
{
    private const LOOKAROUND_MONTHS = 12;

    private const PROCESSING_DAYS = 3;

    /**
     * @return list<RecurringState>
     */
    public function forMonth(CarbonImmutable $month, ?TransactionFilter $scope = null): array
    {
        $month = $month->startOfMonth();

        $payments = RecurringPayment::query()
            ->active()
            ->with(['bankAccount.member', 'category'])
            ->when($scope !== null, fn (Builder $query) => $this->applyScope($query, $scope))
            ->get()
            ->sortByLocale('name');

        $linked = Transaction::query()
            ->whereIn('recurring_payment_id', $payments->modelKeys())
            ->where('booked_on', '>=', $month->subMonths(self::LOOKAROUND_MONTHS)->toDateString())
            ->where('booked_on', '<', $month->addMonths(self::LOOKAROUND_MONTHS + 1)->toDateString())
            ->get(['id', 'recurring_payment_id', 'booked_on', 'amount'])
            ->groupBy('recurring_payment_id');

        return $payments
            ->map(fn (RecurringPayment $payment): RecurringState => $this->state($payment, $month, $linked->get($payment->id, collect())))
            ->values()
            ->all();
    }

    /**
     * @param  list<RecurringState>  $states
     */
    public function fixedExpenses(array $states): int
    {
        return array_sum(array_map(
            fn (RecurringState $state): int => $state->expectedCost(),
            array_filter($states, fn (RecurringState $state): bool => $state->status !== RecurringStatus::NotDue
                && $state->payment->kind === TransactionKind::Expense),
        ));
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function state(RecurringPayment $payment, CarbonImmutable $month, Collection $transactions): RecurringState
    {
        $schedule = $payment->schedule();
        $dueDate = $schedule->dueDate($month);

        if (! $schedule->isDueIn($month)) {
            return new RecurringState($payment, RecurringStatus::NotDue, 0, null, null);
        }

        $inPeriod = $transactions->filter(
            fn (Transaction $transaction): bool => $schedule->periodFor($transaction->booked_on->toImmutable())?->equalTo($month) ?? false,
        );

        if ($inPeriod->isNotEmpty()) {
            return new RecurringState(
                $payment,
                RecurringStatus::Paid,
                abs((int) $inPeriod->sum('amount')),
                $dueDate,
                $inPeriod->max('booked_on')?->toImmutable(),
            );
        }

        $deadline = $dueDate?->addDays(self::PROCESSING_DAYS) ?? $month->endOfMonth()->startOfDay();
        $status = CarbonImmutable::today()->gt($deadline) ? RecurringStatus::Missing : RecurringStatus::Waiting;

        return new RecurringState($payment, $status, 0, $dueDate, null);
    }

    /**
     * @param  Builder<RecurringPayment>  $query
     */
    private function applyScope(Builder $query, TransactionFilter $scope): void
    {
        $query
            ->when($scope->accountId !== null, fn (Builder $q) => $q->where('bank_account_id', $scope->accountId))
            ->when($scope->owner === TransactionFilter::SHARED, fn (Builder $q) => $q->whereHas(
                'bankAccount',
                fn (Builder $account) => $account->whereNull('member_id'),
            ))
            ->when($scope->owner !== null && ctype_digit($scope->owner), fn (Builder $q) => $q->whereHas(
                'bankAccount',
                fn (Builder $account) => $account->where('member_id', (int) $scope->owner),
            ));
    }
}
