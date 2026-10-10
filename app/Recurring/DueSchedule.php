<?php

declare(strict_types=1);

namespace App\Recurring;

use App\Enums\PaymentFrequency;
use Carbon\CarbonImmutable;

final readonly class DueSchedule
{
    private const EARLY_PAYMENT_DAYS = 7;

    private CarbonImmutable $startMonth;

    private ?CarbonImmutable $endMonth;

    public function __construct(
        CarbonImmutable $startMonth,
        ?CarbonImmutable $endMonth,
        private PaymentFrequency $frequency,
        private ?int $dueDay,
    ) {
        $this->startMonth = $startMonth->startOfMonth();
        $this->endMonth = $endMonth?->startOfMonth();
    }

    public function isDueIn(CarbonImmutable $month): bool
    {
        $month = $month->startOfMonth();

        if ($month->lt($this->startMonth) || ($this->endMonth !== null && $month->gt($this->endMonth))) {
            return false;
        }

        return $this->monthsFromStart($month) % $this->frequency->months() === 0;
    }

    public function dueDate(CarbonImmutable $month): ?CarbonImmutable
    {
        if ($this->dueDay === null) {
            return null;
        }

        $month = $month->startOfMonth();

        return $month->setDay(min($this->dueDay, $month->daysInMonth));
    }

    public function periodFor(CarbonImmutable $date): ?CarbonImmutable
    {
        $date = $date->startOfDay();
        $month = $date->startOfMonth();
        $next = $month->addMonthNoOverflow();
        $previous = $month->subMonthNoOverflow();

        if ($this->isDueIn($next) && $this->opensOn($next)->lte($date)) {
            return $next;
        }

        $previousDueDate = $this->dueDate($previous);

        if ($previousDueDate !== null && $this->isDueIn($previous) && $previousDueDate->addDays(self::EARLY_PAYMENT_DAYS)->gte($date)) {
            return $previous;
        }

        for ($offset = 0; $offset >= -$this->frequency->months(); $offset--) {
            $candidate = $month->addMonthsNoOverflow($offset);

            if ($this->isDueIn($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function opensOn(CarbonImmutable $month): CarbonImmutable
    {
        return $this->dueDate($month)?->subDays(self::EARLY_PAYMENT_DAYS) ?? $month->startOfMonth();
    }

    private function monthsFromStart(CarbonImmutable $month): int
    {
        return $this->monthsBetween($this->startMonth, $month);
    }

    private function monthsBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return ($to->year - $from->year) * 12 + ($to->month - $from->month);
    }
}
