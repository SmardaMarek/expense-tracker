<?php

declare(strict_types=1);

namespace App\Recurring;

use App\Enums\TransactionSource;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

final class RecordPayment
{
    public function handle(RecurringPayment $payment, CarbonImmutable $month): Transaction
    {
        return Transaction::query()->create([
            'bank_account_id' => $payment->bank_account_id,
            'booked_on' => $this->bookingDate($payment, $month)->toDateString(),
            'amount' => -$payment->amount,
            'type' => $payment->kind->type(),
            'category_id' => $payment->category_id,
            'recurring_payment_id' => $payment->id,
            'counterparty_name' => $payment->name,
            'counterparty_account' => $payment->counterparty_account,
            'source' => TransactionSource::Manual,
        ]);
    }

    private function bookingDate(RecurringPayment $payment, CarbonImmutable $month): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if ($today->isSameMonth($month)) {
            return $today;
        }

        return $payment->schedule()->dueDate($month) ?? $month->startOfMonth();
    }
}
