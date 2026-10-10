<?php

declare(strict_types=1);

namespace Tests\Feature\Recurring;

use App\Enums\PaymentFrequency;
use App\Enums\RecurringStatus;
use App\Models\BankAccount;
use App\Models\Member;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Recurring\RecurringOverview;
use App\Recurring\RecurringState;
use App\Transactions\TransactionFilter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-20 12:00:00');
    }

    public function test_a_linked_payment_marks_it_as_paid(): void
    {
        $rent = RecurringPayment::factory()->create(['amount' => 1_650_000]);
        Transaction::factory()->forRecurring($rent)->on('2026-10-14')->create();

        $state = $this->stateOf($rent);

        $this->assertSame(RecurringStatus::Paid, $state->status);
        $this->assertSame(1_650_000, $state->paidAmount);
        $this->assertSame('2026-10-14', $state->paidOn?->toDateString());
    }

    public function test_a_payment_with_a_different_amount_still_counts_as_paid_and_keeps_its_amount(): void
    {
        $energy = RecurringPayment::factory()->create(['amount' => 300_000]);
        Transaction::factory()->forRecurring($energy)->on('2026-10-15')->create(['amount' => -340_000]);

        $state = $this->stateOf($energy);

        $this->assertSame(RecurringStatus::Paid, $state->status);
        $this->assertSame(340_000, $state->paidAmount);
    }

    public function test_an_unpaid_payment_before_its_deadline_is_waiting(): void
    {
        $netflix = RecurringPayment::factory()->create(['due_day' => 25]);

        $state = $this->stateOf($netflix);

        $this->assertSame(RecurringStatus::Waiting, $state->status);
        $this->assertSame('2026-10-25', $state->dueDate?->toDateString());
    }

    public function test_a_payment_becomes_missing_three_days_after_its_due_day(): void
    {
        $bill = RecurringPayment::factory()->create(['due_day' => 17]);

        $this->assertSame(RecurringStatus::Waiting, $this->stateOf($bill)->status);

        $this->travelTo('2026-10-21 08:00:00');
        $this->assertSame(RecurringStatus::Missing, $this->stateOf($bill)->status);
    }

    public function test_an_unpaid_payment_after_its_deadline_is_missing(): void
    {
        $rent = RecurringPayment::factory()->create(['due_day' => 10]);

        $this->assertSame(RecurringStatus::Missing, $this->stateOf($rent)->status);
    }

    public function test_without_a_due_day_it_waits_until_the_month_ends(): void
    {
        $savings = RecurringPayment::factory()->transfer()->create();

        $this->assertSame(RecurringStatus::Waiting, $this->stateOf($savings)->status);
        $this->assertSame(RecurringStatus::Missing, $this->stateOf($savings, '2026-09')->status);
    }

    public function test_a_payment_not_due_this_month_is_marked_so(): void
    {
        $insurance = RecurringPayment::factory()->create(['frequency' => PaymentFrequency::Yearly, 'start_month' => '2026-03-01']);

        $this->assertSame(RecurringStatus::NotDue, $this->stateOf($insurance)->status);
        $this->assertSame(RecurringStatus::Missing, $this->stateOf($insurance, '2026-03')->status);
    }

    public function test_a_payment_after_its_end_month_is_not_due(): void
    {
        $subscription = RecurringPayment::factory()->create(['end_month' => '2026-08-01']);

        $this->assertSame(RecurringStatus::NotDue, $this->stateOf($subscription)->status);
    }

    public function test_an_early_payment_counts_for_the_next_due_month_only(): void
    {
        $rent = RecurringPayment::factory()->create(['due_day' => 1]);
        Transaction::factory()->forRecurring($rent)->on('2026-09-29')->create();

        $this->assertSame(RecurringStatus::Paid, $this->stateOf($rent)->status);
        $this->assertSame(RecurringStatus::Missing, $this->stateOf($rent, '2026-09')->status);
    }

    public function test_archived_payments_are_left_out(): void
    {
        RecurringPayment::factory()->archived()->create();

        $this->assertSame([], app(RecurringOverview::class)->forMonth(CarbonImmutable::parse('2026-10-01')));
    }

    public function test_it_can_be_limited_to_an_owner(): void
    {
        $petr = Member::factory()->create();
        RecurringPayment::factory()->create(['name' => 'Petrova', 'bank_account_id' => BankAccount::factory()->ownedBy($petr)]);
        RecurringPayment::factory()->create(['name' => 'Společná']);

        $states = app(RecurringOverview::class)->forMonth(
            CarbonImmutable::parse('2026-10-01'),
            TransactionFilter::fromInput('2026-10', '', (string) $petr->id, '', ''),
        );

        $this->assertSame(['Petrova'], array_map(fn (RecurringState $state) => $state->payment->name, $states));
    }

    public function test_fixed_expenses_use_paid_amounts_and_expected_amounts_for_unpaid_ones(): void
    {
        $rent = RecurringPayment::factory()->create(['amount' => 1_650_000]);
        Transaction::factory()->forRecurring($rent)->on('2026-10-14')->create(['amount' => -1_700_000]);
        RecurringPayment::factory()->create(['amount' => 30_000, 'due_day' => 28]);
        RecurringPayment::factory()->transfer()->create(['amount' => 500_000]);
        RecurringPayment::factory()->create(['amount' => 999_900, 'frequency' => PaymentFrequency::Yearly, 'start_month' => '2026-03-01']);

        $overview = app(RecurringOverview::class);

        $this->assertSame(1_730_000, $overview->fixedExpenses($overview->forMonth(CarbonImmutable::parse('2026-10-01'))));
    }

    private function stateOf(RecurringPayment $payment, string $month = '2026-10'): RecurringState
    {
        $states = app(RecurringOverview::class)->forMonth(CarbonImmutable::parse($month.'-01'));

        return collect($states)->first(fn (RecurringState $state) => $state->payment->is($payment));
    }
}
