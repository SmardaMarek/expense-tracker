<?php

declare(strict_types=1);

namespace Tests\Feature\Recurring;

use App\Enums\PaymentFrequency;
use App\Enums\TransactionKind;
use App\Livewire\BankAccounts;
use App\Livewire\Categories;
use App\Livewire\RecurringPayments;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecurringPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-20 12:00:00');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $this->get(route('recurring'))->assertRedirect(route('login'));
    }

    public function test_it_creates_a_monthly_rent_with_a_recipient_account(): void
    {
        $account = BankAccount::factory()->create();
        $housing = Category::factory()->create(['name' => 'Bydlení']);

        Livewire::test(RecurringPayments::class)
            ->call('create')
            ->assertSet('form.start_month', '2026-10')
            ->assertSet('form.bank_account_id', (string) $account->id)
            ->set('form.name', 'Nájem')
            ->set('form.amount', '16 500')
            ->set('form.due_day', '1')
            ->set('form.category_id', (string) $housing->id)
            ->set('form.counterparty_account', '000019-2000145399/0800')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form_open', false);

        $this->assertDatabaseHas('recurring_payments', [
            'name' => 'Nájem',
            'kind' => 'expense',
            'amount' => 1_650_000,
            'frequency' => 'monthly',
            'start_month' => '2026-10-01 00:00:00',
            'due_day' => 1,
            'bank_account_id' => $account->id,
            'category_id' => $housing->id,
            'counterparty_account' => '19-2000145399/0800',
        ]);
    }

    public function test_it_creates_a_savings_transfer_without_a_due_day(): void
    {
        BankAccount::factory()->create();
        $savings = Category::factory()->transfer()->create();

        Livewire::test(RecurringPayments::class)
            ->call('create')
            ->set('form.name', 'Spoření')
            ->set('form.kind', 'transfer_out')
            ->set('form.amount', '5000')
            ->set('form.category_id', (string) $savings->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recurring_payments', ['name' => 'Spoření', 'kind' => 'transfer_out', 'due_day' => null]);
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_it_rejects_invalid_input(array $input, string $invalidField): void
    {
        BankAccount::factory()->create();
        $component = Livewire::test(RecurringPayments::class)
            ->call('create')
            ->set('form.name', 'Platba')
            ->set('form.amount', '100');
        foreach ($input as $property => $value) {
            $component->set("form.{$property}", $value);
        }

        $component->call('save');

        $component->assertHasErrors("form.{$invalidField}");
        $this->assertDatabaseCount('recurring_payments', 0);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'income kind' => [['kind' => 'income'], 'kind'],
            'zero amount' => [['amount' => '0'], 'amount'],
            'due day 32' => [['due_day' => '32'], 'due_day'],
            'unknown frequency' => [['frequency' => 'weekly'], 'frequency'],
            'end before start' => [['start_month' => '2026-10', 'end_month' => '2026-09'], 'end_month'],
            'invalid start month' => [['start_month' => '2026-13'], 'start_month'],
        ];
    }

    public function test_the_category_must_match_the_kind(): void
    {
        BankAccount::factory()->create();
        $groceries = Category::factory()->create();

        Livewire::test(RecurringPayments::class)
            ->call('create')
            ->set('form.name', 'Spoření')
            ->set('form.kind', 'transfer_out')
            ->set('form.amount', '5000')
            ->set('form.category_id', (string) $groceries->id)
            ->call('save')
            ->assertHasErrors('form.category_id');
    }

    public function test_changing_the_kind_clears_the_category(): void
    {
        BankAccount::factory()->create();
        $groceries = Category::factory()->create();

        Livewire::test(RecurringPayments::class)
            ->call('create')
            ->set('form.category_id', (string) $groceries->id)
            ->set('form.kind', 'transfer_out')
            ->assertSet('form.category_id', '');
    }

    public function test_it_edits_a_payment(): void
    {
        $payment = RecurringPayment::factory()->create(['name' => 'Elektřina', 'amount' => 300_000, 'frequency' => PaymentFrequency::Monthly]);

        Livewire::test(RecurringPayments::class)
            ->call('edit', $payment->id)
            ->assertSet('form.amount', '3000,00')
            ->assertSet('form.start_month', '2026-01')
            ->set('form.amount', '3200')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(320_000, $payment->fresh()->amount);
    }

    public function test_it_shows_each_payment_with_its_status_for_the_month(): void
    {
        RecurringPayment::factory()->create(['name' => 'Nájem', 'due_day' => 1]);
        RecurringPayment::factory()->create(['name' => 'Netflix', 'due_day' => 28]);

        Livewire::test(RecurringPayments::class)
            ->assertSeeInOrder(['Nájem', 'Chybí', 'Netflix', 'Čeká']);
    }

    public function test_recording_a_payment_creates_a_linked_transaction(): void
    {
        $category = Category::factory()->create();
        $rent = RecurringPayment::factory()->create([
            'name' => 'Nájem',
            'amount' => 1_650_000,
            'due_day' => 1,
            'category_id' => $category->id,
            'counterparty_account' => '19-2000145399/0800',
        ]);

        Livewire::test(RecurringPayments::class)
            ->call('recordPayment', $rent->id)
            ->assertSee('Platba Nájem byla zaznamenána.')
            ->assertSee('Zaplaceno');

        $this->assertDatabaseHas('transactions', [
            'recurring_payment_id' => $rent->id,
            'bank_account_id' => $rent->bank_account_id,
            'amount' => -1_650_000,
            'type' => 'expense',
            'category_id' => $category->id,
            'booked_on' => '2026-10-20 00:00:00',
            'counterparty_name' => 'Nájem',
            'counterparty_account' => '19-2000145399/0800',
        ]);
    }

    public function test_recording_a_payment_for_a_past_month_uses_its_due_date(): void
    {
        $rent = RecurringPayment::factory()->create(['due_day' => 5]);

        Livewire::test(RecurringPayments::class)
            ->call('previousMonth')
            ->call('recordPayment', $rent->id);

        $this->assertDatabaseHas('transactions', ['recurring_payment_id' => $rent->id, 'booked_on' => '2026-09-05 00:00:00']);
    }

    public function test_a_recorded_transfer_keeps_its_transfer_type(): void
    {
        $savings = RecurringPayment::factory()->transfer()->create(['amount' => 500_000]);

        Livewire::test(RecurringPayments::class)->call('recordPayment', $savings->id);

        $this->assertDatabaseHas('transactions', ['recurring_payment_id' => $savings->id, 'type' => 'transfer', 'amount' => -500_000]);
    }

    public function test_it_sums_fixed_costs_per_month(): void
    {
        RecurringPayment::factory()->create(['amount' => 1_650_000]);
        RecurringPayment::factory()->create(['amount' => 1_200_000, 'frequency' => PaymentFrequency::Yearly]);
        RecurringPayment::factory()->transfer()->create(['amount' => 500_000]);

        Livewire::test(RecurringPayments::class)
            ->assertViewHas('monthlyExpenses', 1_750_000)
            ->assertViewHas('monthlyTransfers', 500_000);
    }

    public function test_a_payment_with_linked_transactions_cannot_be_deleted(): void
    {
        $rent = RecurringPayment::factory()->create();
        Transaction::factory()->forRecurring($rent)->create();

        Livewire::test(RecurringPayments::class)
            ->call('delete', $rent->id)
            ->assertSee('nelze smazat');

        $this->assertModelExists($rent);
    }

    public function test_it_archives_restores_and_deletes(): void
    {
        $payment = RecurringPayment::factory()->create();

        $component = Livewire::test(RecurringPayments::class)->call('archive', $payment->id);
        $this->assertNotNull($payment->fresh()->archived_at);
        $component->assertSee('Archivované pravidelné platby');

        $component->call('restore', $payment->id);
        $this->assertNull($payment->fresh()->archived_at);

        $component->call('delete', $payment->id);
        $this->assertModelMissing($payment);
    }

    public function test_an_account_used_by_a_recurring_payment_cannot_be_deleted(): void
    {
        $payment = RecurringPayment::factory()->create();

        Livewire::test(BankAccounts::class)->call('delete', $payment->bank_account_id);

        $this->assertModelExists($payment->bankAccount);
    }

    public function test_a_category_used_by_a_recurring_payment_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        RecurringPayment::factory()->create(['category_id' => $category->id]);

        Livewire::test(Categories::class)->call('delete', $category->id);

        $this->assertModelExists($category);
    }

    public function test_only_expense_and_outgoing_transfer_kinds_are_offered(): void
    {
        Livewire::test(RecurringPayments::class)
            ->assertViewHas('kindOptions', fn (array $options) => array_keys($options) === [TransactionKind::Expense->value, TransactionKind::TransferOut->value]);
    }
}
