<?php

declare(strict_types=1);

namespace App\Recurring;

use App\Enums\RecurringStatus;
use App\Models\RecurringPayment;
use Carbon\CarbonImmutable;

final readonly class RecurringState
{
    public function __construct(
        public RecurringPayment $payment,
        public RecurringStatus $status,
        public int $paidAmount,
        public ?CarbonImmutable $dueDate,
        public ?CarbonImmutable $paidOn,
    ) {}

    public function expectedCost(): int
    {
        return $this->status->isSettled() ? $this->paidAmount : $this->payment->amount;
    }
}
