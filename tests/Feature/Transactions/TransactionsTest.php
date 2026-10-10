<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Livewire\Transactions;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Member;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $response = $this->get(route('transactions'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_shows_the_current_month_by_default(): void
    {
        Transaction::factory()->on('2026-10-01')->create(['counterparty_name' => 'Říjnový obchod']);
        Transaction::factory()->on('2026-09-30')->create(['counterparty_name' => 'Zářijový obchod']);

        Livewire::test(Transactions::class)
            ->assertSee('Říjnový obchod')
            ->assertDontSee('Zářijový obchod')
            ->assertSee('říjen 2026');
    }

    public function test_the_month_includes_its_first_and_last_day(): void
    {
        Transaction::factory()->on('2026-10-01')->create(['counterparty_name' => 'První den']);
        Transaction::factory()->on('2026-10-31')->create(['counterparty_name' => 'Poslední den']);
        Transaction::factory()->on('2026-11-01')->create(['counterparty_name' => 'Listopadový obchod']);

        Livewire::test(Transactions::class)
            ->assertSee('První den')
            ->assertSee('Poslední den')
            ->assertDontSee('Listopadový obchod');
    }

    public function test_it_moves_between_months(): void
    {
        Transaction::factory()->on('2026-09-10')->create(['counterparty_name' => 'Zářijový obchod']);

        Livewire::test(Transactions::class)
            ->call('previousMonth')
            ->assertSet('month', '2026-09')
            ->assertSee('Zářijový obchod')
            ->call('nextMonth')
            ->call('nextMonth')
            ->assertSet('month', '2026-11');
    }

    public function test_an_invalid_month_falls_back_to_the_current_month(): void
    {
        Transaction::factory()->on('2026-10-05')->create(['counterparty_name' => 'Říjnový obchod']);

        Livewire::withQueryParams(['month' => '2026-13'])
            ->test(Transactions::class)
            ->assertSee('Říjnový obchod');
    }

    public function test_it_filters_by_account(): void
    {
        $first = BankAccount::factory()->create();
        $second = BankAccount::factory()->create();
        Transaction::factory()->forAccount($first)->create(['counterparty_name' => 'Na prvním']);
        Transaction::factory()->forAccount($second)->create(['counterparty_name' => 'Na druhém']);

        Livewire::test(Transactions::class)
            ->set('account', (string) $first->id)
            ->assertSee('Na prvním')
            ->assertDontSee('Na druhém');
    }

    public function test_it_filters_by_owner_and_shared_accounts(): void
    {
        $member = Member::factory()->create();
        $personal = BankAccount::factory()->ownedBy($member)->create();
        $shared = BankAccount::factory()->create();
        Transaction::factory()->forAccount($personal)->create(['counterparty_name' => 'Osobní platba']);
        Transaction::factory()->forAccount($shared)->create(['counterparty_name' => 'Společná platba']);

        Livewire::test(Transactions::class)
            ->set('owner', (string) $member->id)
            ->assertSee('Osobní platba')
            ->assertDontSee('Společná platba')
            ->set('owner', 'shared')
            ->assertSee('Společná platba')
            ->assertDontSee('Osobní platba');
    }

    public function test_it_filters_by_kind(): void
    {
        Transaction::factory()->create(['counterparty_name' => 'Nákup']);
        Transaction::factory()->income()->create(['counterparty_name' => 'Výplata']);

        Livewire::test(Transactions::class)
            ->set('type', 'income')
            ->assertSee('Výplata')
            ->assertDontSee('Nákup');
    }

    public function test_it_filters_by_category_and_uncategorized(): void
    {
        $groceries = Category::factory()->create(['name' => 'Potraviny']);
        Transaction::factory()->inCategory($groceries)->create(['counterparty_name' => 'Supermarket']);
        Transaction::factory()->create(['counterparty_name' => 'Nezařazená platba']);
        Transaction::factory()->transferOut()->create(['counterparty_name' => 'Převod na společný']);

        Livewire::test(Transactions::class)
            ->set('category', (string) $groceries->id)
            ->assertSee('Supermarket')
            ->assertDontSee('Nezařazená platba')
            ->set('category', 'none')
            ->assertSee('Nezařazená platba')
            ->assertSee('Převod na společný')
            ->assertDontSee('Supermarket');
    }

    public function test_the_category_filter_follows_the_chosen_kind(): void
    {
        $groceries = Category::factory()->create(['name' => 'Potraviny']);
        $salary = Category::factory()->income()->create(['name' => 'Mzda']);
        $savings = Category::factory()->transfer()->create(['name' => 'Na spoření']);

        Livewire::test(Transactions::class)
            ->assertViewHas('categoryFilterOptions', fn (array $options) => isset($options[$groceries->id], $options[$salary->id], $options[$savings->id]))
            ->set('type', 'income')
            ->assertViewHas('categoryFilterOptions', fn (array $options) => isset($options[$salary->id], $options['none'])
                && ! isset($options[$groceries->id])
                && ! isset($options[$savings->id]))
            ->set('type', 'transfer')
            ->assertViewHas('categoryFilterOptions', fn (array $options) => array_keys($options) === ['', 'none', $savings->id]);
    }

    public function test_changing_the_kind_filter_clears_a_category_that_no_longer_fits(): void
    {
        $groceries = Category::factory()->create();

        Livewire::test(Transactions::class)
            ->set('category', (string) $groceries->id)
            ->set('type', 'income')
            ->assertSet('category', '');
    }

    public function test_changing_the_kind_filter_keeps_a_category_that_still_fits(): void
    {
        $groceries = Category::factory()->create();

        Livewire::test(Transactions::class)
            ->set('category', (string) $groceries->id)
            ->set('type', 'expense')
            ->assertSet('category', (string) $groceries->id)
            ->set('category', 'none')
            ->set('type', 'transfer')
            ->assertSet('category', 'none');
    }

    public function test_the_form_offers_only_categories_of_the_chosen_kind(): void
    {
        BankAccount::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Potraviny']);
        $salary = Category::factory()->income()->create(['name' => 'Mzda']);

        Livewire::test(Transactions::class)
            ->call('create')
            ->assertViewHas('formCategoryOptions', fn (array $options) => isset($options[$groceries->id]) && ! isset($options[$salary->id]))
            ->set('form.kind', 'income')
            ->assertViewHas('formCategoryOptions', fn (array $options) => isset($options[$salary->id]) && ! isset($options[$groceries->id]));
    }

    public function test_clearing_filters_resets_them_but_keeps_the_month(): void
    {
        Livewire::test(Transactions::class)
            ->set('month', '2026-08')
            ->set('type', 'income')
            ->set('owner', 'shared')
            ->call('resetFilters')
            ->assertSet('type', '')
            ->assertSet('owner', '')
            ->assertSet('month', '2026-08');
    }

    public function test_it_creates_an_expense_stored_as_negative_haler(): void
    {
        $account = BankAccount::factory()->create();
        $category = Category::factory()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->assertSet('form.booked_on', '2026-10-15')
            ->assertSet('form.bank_account_id', (string) $account->id)
            ->set('form.amount', '1 234,50')
            ->set('form.category_id', (string) $category->id)
            ->set('form.counterparty_name', 'Supermarket')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form_open', false);

        $this->assertDatabaseHas('transactions', [
            'bank_account_id' => $account->id,
            'amount' => -123450,
            'type' => 'expense',
            'category_id' => $category->id,
            'counterparty_name' => 'Supermarket',
            'source' => TransactionSource::Manual->value,
        ]);
    }

    public function test_it_creates_an_income_stored_as_positive_haler(): void
    {
        $account = BankAccount::factory()->create();
        $salary = Category::factory()->income()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.kind', 'income')
            ->set('form.amount', '45000')
            ->set('form.category_id', (string) $salary->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'bank_account_id' => $account->id,
            'amount' => 4500000,
            'type' => 'income',
            'category_id' => $salary->id,
        ]);
    }

    #[DataProvider('transferKinds')]
    public function test_transfers_keep_their_direction_and_take_a_transfer_category(string $kind, int $expectedAmount): void
    {
        BankAccount::factory()->create();
        $savings = Category::factory()->transfer()->create(['name' => 'Na spoření']);

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.kind', $kind)
            ->set('form.amount', '5000')
            ->set('form.category_id', (string) $savings->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', ['type' => 'transfer', 'amount' => $expectedAmount, 'category_id' => $savings->id]);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function transferKinds(): array
    {
        return [
            'outgoing' => ['transfer_out', -500000],
            'incoming' => ['transfer_in', 500000],
        ];
    }

    public function test_a_transfer_category_is_optional(): void
    {
        BankAccount::factory()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.kind', 'transfer_out')
            ->set('form.amount', '5000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', ['type' => 'transfer', 'category_id' => null]);
    }

    public function test_an_expense_category_cannot_be_used_for_a_transfer(): void
    {
        BankAccount::factory()->create();
        $groceries = Category::factory()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.kind', 'transfer_out')
            ->set('form.amount', '5000')
            ->set('form.category_id', (string) $groceries->id)
            ->call('save')
            ->assertHasErrors('form.category_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_changing_the_kind_clears_the_chosen_category(): void
    {
        BankAccount::factory()->create();
        $groceries = Category::factory()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.category_id', (string) $groceries->id)
            ->set('form.kind', 'transfer_out')
            ->assertSet('form.category_id', '');
    }

    public function test_the_form_explains_transfers_when_a_transfer_is_chosen(): void
    {
        BankAccount::factory()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->assertDontSee('peníze přesunuté mezi vašimi vlastními účty')
            ->set('form.kind', 'transfer_in')
            ->assertSee('peníze přesunuté mezi vašimi vlastními účty');
    }

    public function test_the_category_must_match_the_kind(): void
    {
        BankAccount::factory()->create();
        $salary = Category::factory()->income()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.kind', 'expense')
            ->set('form.amount', '100')
            ->set('form.category_id', (string) $salary->id)
            ->call('save')
            ->assertHasErrors('form.category_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_an_archived_category_cannot_be_used_for_a_new_transaction(): void
    {
        BankAccount::factory()->create();
        $archived = Category::factory()->archived()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.amount', '100')
            ->set('form.category_id', (string) $archived->id)
            ->call('save')
            ->assertHasErrors('form.category_id');
    }

    public function test_an_archived_account_cannot_be_used_for_a_new_transaction(): void
    {
        $archived = BankAccount::factory()->archived()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.bank_account_id', (string) $archived->id)
            ->set('form.amount', '100')
            ->call('save')
            ->assertHasErrors('form.bank_account_id');
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_it_rejects_invalid_input(array $input, string $invalidField): void
    {
        BankAccount::factory()->create();

        $component = Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.amount', '100');
        foreach ($input as $property => $value) {
            $component->set("form.{$property}", $value);
        }
        $component->call('save');

        $component->assertHasErrors("form.{$invalidField}");
        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing amount' => [['amount' => ''], 'amount'],
            'zero amount' => [['amount' => '0'], 'amount'],
            'negative amount' => [['amount' => '-50'], 'amount'],
            'three decimals' => [['amount' => '1,234'], 'amount'],
            'missing date' => [['booked_on' => ''], 'booked_on'],
            'invalid date' => [['booked_on' => '2026-02-30'], 'booked_on'],
            'unknown kind' => [['kind' => 'gift'], 'kind'],
            'variable symbol with letters' => [['variable_symbol' => '12AB'], 'variable_symbol'],
            'variable symbol too long' => [['variable_symbol' => '12345678901'], 'variable_symbol'],
            'unknown account' => [['bank_account_id' => '999'], 'bank_account_id'],
        ];
    }

    public function test_a_valid_czech_counterparty_account_is_normalized(): void
    {
        BankAccount::factory()->create();

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.amount', '100')
            ->set('form.counterparty_account', '000019-2000145399/0800')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', ['counterparty_account' => '19-2000145399/0800']);
    }

    public function test_it_edits_a_transaction(): void
    {
        $account = BankAccount::factory()->create();
        $transaction = Transaction::factory()->forAccount($account)->create(['amount' => -123450, 'message' => 'Původní']);

        Livewire::test(Transactions::class)
            ->call('edit', $transaction->id)
            ->assertSet('form.kind', 'expense')
            ->assertSet('form.amount', '1234,50')
            ->set('form.amount', '99,90')
            ->set('form.message', 'Opraveno')
            ->call('save')
            ->assertHasNoErrors();

        $transaction->refresh();
        $this->assertSame(-9990, $transaction->amount);
        $this->assertSame('Opraveno', $transaction->message);
    }

    public function test_a_transaction_on_an_archived_account_can_still_be_edited(): void
    {
        $archived = BankAccount::factory()->archived()->create();
        $transaction = Transaction::factory()->forAccount($archived)->create();

        Livewire::test(Transactions::class)
            ->call('edit', $transaction->id)
            ->set('form.message', 'Doplněno')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Doplněno', $transaction->fresh()->message);
    }

    public function test_editing_cannot_move_a_transaction_to_another_archived_account(): void
    {
        $archived = BankAccount::factory()->archived()->create();
        $otherArchived = BankAccount::factory()->archived()->create();
        $transaction = Transaction::factory()->forAccount($archived)->create();

        Livewire::test(Transactions::class)
            ->call('edit', $transaction->id)
            ->set('form.bank_account_id', (string) $otherArchived->id)
            ->call('save')
            ->assertHasErrors('form.bank_account_id');

        $this->assertSame($archived->id, $transaction->fresh()->bank_account_id);
    }

    public function test_it_deletes_a_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        Livewire::test(Transactions::class)->call('delete', $transaction->id);

        $this->assertModelMissing($transaction);
    }

    public function test_amounts_are_shown_in_czech_format(): void
    {
        Transaction::factory()->create(['amount' => -123450]);
        Transaction::factory()->income()->create(['amount' => 4500000]);

        Livewire::test(Transactions::class)
            ->assertSee("\u{2212}1\u{00A0}234,50\u{00A0}Kč", false)
            ->assertSee("45\u{00A0}000,00\u{00A0}Kč", false);
    }

    public function test_transfers_are_labelled_together_with_their_category(): void
    {
        $contribution = Category::factory()->transfer()->create(['name' => 'Příspěvek na společný účet']);
        Transaction::factory()->transferOut()->inCategory($contribution)->create(['counterparty_name' => 'Petr']);

        Livewire::test(Transactions::class)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->first()->type === TransactionType::Transfer)
            ->assertSeeInOrder(['Petr', 'Převod', 'Příspěvek na společný účet']);
    }

    public function test_choosing_a_recurring_payment_fills_in_the_form(): void
    {
        $account = BankAccount::factory()->create();
        $category = Category::factory()->create();
        $rent = RecurringPayment::factory()->create([
            'name' => 'Nájem',
            'amount' => 1_650_000,
            'bank_account_id' => $account->id,
            'category_id' => $category->id,
            'counterparty_account' => '19-2000145399/0800',
        ]);

        Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.recurring_payment_id', (string) $rent->id)
            ->assertSet('form.kind', 'expense')
            ->assertSet('form.amount', '16500,00')
            ->assertSet('form.bank_account_id', (string) $account->id)
            ->assertSet('form.category_id', (string) $category->id)
            ->assertSet('form.counterparty_name', 'Nájem')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', ['recurring_payment_id' => $rent->id, 'amount' => -1_650_000]);
    }

    public function test_a_recurring_payment_must_match_the_kind(): void
    {
        BankAccount::factory()->create();
        $savings = RecurringPayment::factory()->transfer()->create();

        $component = Livewire::test(Transactions::class)
            ->call('create')
            ->set('form.amount', '100');
        $component->set('form.recurring_payment_id', (string) $savings->id)
            ->set('form.kind', 'expense')
            ->assertSet('form.recurring_payment_id', '');
        $component->set('form.recurring_payment_id', (string) $savings->id)
            ->set('form.kind', 'transfer_out')
            ->set('form.kind', 'expense');

        $this->assertSame('', $component->get('form.recurring_payment_id'));
    }

    public function test_an_unlinked_kind_mismatch_is_rejected_on_save(): void
    {
        BankAccount::factory()->create();
        $savings = RecurringPayment::factory()->transfer()->create();

        $component = Livewire::test(Transactions::class)->call('create');
        $component->set('form.amount', '100');
        $component->updateProperty('form.recurring_payment_id', (string) $savings->id);
        $component->set('form.kind', 'transfer_out');
        $component->set('form.recurring_payment_id', (string) $savings->id);
        $component->set('form.kind', 'expense');
        $component->call('save');

        $this->assertDatabaseMissing('transactions', ['recurring_payment_id' => $savings->id, 'type' => 'expense']);
    }

    public function test_linked_transactions_show_a_recurring_marker(): void
    {
        $rent = RecurringPayment::factory()->create(['name' => 'Nájem']);
        Transaction::factory()->forRecurring($rent)->create();

        Livewire::test(Transactions::class)
            ->assertSeeHtml('Pravidelná platba: Nájem');
    }
}
