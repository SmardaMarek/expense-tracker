<?php

declare(strict_types=1);

namespace Tests\Unit\Recurring;

use App\Enums\PaymentFrequency;
use App\Recurring\DueSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DueScheduleTest extends TestCase
{
    #[DataProvider('dueMonths')]
    public function test_it_knows_in_which_months_a_payment_is_due(PaymentFrequency $frequency, string $start, ?string $end, string $month, bool $expected): void
    {
        $schedule = new DueSchedule(CarbonImmutable::parse($start), $end ? CarbonImmutable::parse($end) : null, $frequency, 15);

        $this->assertSame($expected, $schedule->isDueIn(CarbonImmutable::parse($month)));
    }

    /**
     * @return array<string, array{PaymentFrequency, string, ?string, string, bool}>
     */
    public static function dueMonths(): array
    {
        return [
            'monthly, after start' => [PaymentFrequency::Monthly, '2026-01-01', null, '2026-10-01', true],
            'monthly, before start' => [PaymentFrequency::Monthly, '2026-05-01', null, '2026-04-01', false],
            'monthly, in the end month' => [PaymentFrequency::Monthly, '2026-01-01', '2026-10-01', '2026-10-01', true],
            'monthly, after the end month' => [PaymentFrequency::Monthly, '2026-01-01', '2026-10-01', '2026-11-01', false],
            'quarterly, due month' => [PaymentFrequency::Quarterly, '2026-01-01', null, '2026-10-01', true],
            'quarterly, between' => [PaymentFrequency::Quarterly, '2026-01-01', null, '2026-11-01', false],
            'half-yearly, due month' => [PaymentFrequency::HalfYearly, '2026-03-01', null, '2026-09-01', true],
            'yearly, next year' => [PaymentFrequency::Yearly, '2025-03-01', null, '2026-03-01', true],
            'yearly, other month' => [PaymentFrequency::Yearly, '2025-03-01', null, '2026-04-01', false],
        ];
    }

    public function test_the_due_date_is_clamped_to_the_length_of_the_month(): void
    {
        $schedule = new DueSchedule(CarbonImmutable::parse('2026-01-01'), null, PaymentFrequency::Monthly, 31);

        $this->assertSame('2026-02-28', $schedule->dueDate(CarbonImmutable::parse('2026-02-01'))?->toDateString());
        $this->assertSame('2026-10-31', $schedule->dueDate(CarbonImmutable::parse('2026-10-01'))?->toDateString());
    }

    public function test_without_a_due_day_there_is_no_due_date(): void
    {
        $schedule = new DueSchedule(CarbonImmutable::parse('2026-01-01'), null, PaymentFrequency::Monthly, null);

        $this->assertNull($schedule->dueDate(CarbonImmutable::parse('2026-10-01')));
    }

    #[DataProvider('payments')]
    public function test_a_payment_is_assigned_to_the_due_month_it_settles(PaymentFrequency $frequency, ?int $dueDay, string $paidOn, ?string $expectedMonth): void
    {
        $schedule = new DueSchedule(CarbonImmutable::parse('2026-01-01'), null, $frequency, $dueDay);

        $this->assertSame($expectedMonth, $schedule->periodFor(CarbonImmutable::parse($paidOn))?->format('Y-m'));
    }

    /**
     * @return array<string, array{PaymentFrequency, ?int, string, ?string}>
     */
    public static function payments(): array
    {
        return [
            'paid on time' => [PaymentFrequency::Monthly, 15, '2026-10-15', '2026-10'],
            'rent due on the 1st paid at the end of the previous month' => [PaymentFrequency::Monthly, 1, '2026-09-29', '2026-10'],
            'paid a few days late' => [PaymentFrequency::Monthly, 28, '2026-11-02', '2026-10'],
            'paid almost three weeks late' => [PaymentFrequency::Monthly, 1, '2026-10-20', '2026-10'],
            'paid two weeks early in the same month' => [PaymentFrequency::Monthly, 28, '2026-10-15', '2026-10'],
            'no due day, paid within the month' => [PaymentFrequency::Monthly, null, '2026-10-31', '2026-10'],
            'quarterly paid a month late' => [PaymentFrequency::Quarterly, 15, '2026-11-20', '2026-10'],
            'before the first month' => [PaymentFrequency::Yearly, 15, '2025-06-01', null],
        ];
    }
}
