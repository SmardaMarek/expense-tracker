<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentFrequency;
use App\Livewire\Dashboard;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Member;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prompts_to_add_accounts_when_there_are_none(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee(route('accounts'));
        $response->assertSee('Začněte přidáním bankovních účtů');
    }

    public function test_the_prompt_disappears_once_an_account_exists(): void
    {
        $user = User::factory()->create();
        BankAccount::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertDontSee('Začněte přidáním bankovních účtů');
    }

    public function test_archived_accounts_alone_still_show_the_prompt(): void
    {
        $user = User::factory()->create();
        BankAccount::factory()->archived()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('Začněte přidáním bankovních účtů');
    }

    public function test_it_shows_the_monthly_tiles_with_czech_amounts(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $user = User::factory()->create();
        $account = BankAccount::factory()->create();
        Transaction::factory()->income()->forAccount($account)->create(['amount' => 4_500_000]);
        Transaction::factory()->forAccount($account)->create(['amount' => -1_234_550]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['Příjmy', "45\u{00A0}000,00\u{00A0}Kč", 'Výdaje', "12\u{00A0}345,50\u{00A0}Kč", 'Bilance', "+32\u{00A0}654,50\u{00A0}Kč"], false);
        $response->assertSee('Pohyb na účtech');
        $response->assertSee('říjen 2026');
    }

    public function test_tiles_compare_with_the_previous_month(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $account = BankAccount::factory()->create();
        Transaction::factory()->income()->forAccount($account)->on('2026-09-10')->create(['amount' => 1_000_000]);
        Transaction::factory()->income()->forAccount($account)->on('2026-10-10')->create(['amount' => 1_500_000]);

        Livewire::test(Dashboard::class)
            ->assertSee("▲ +5\u{00A0}000,00\u{00A0}Kč oproti minulému měsíci", false);
    }

    public function test_it_renders_the_three_charts_with_their_data(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $account = BankAccount::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Potraviny']);
        Transaction::factory()->income()->forAccount($account)->create(['amount' => 4_500_000]);
        Transaction::factory()->forAccount($account)->inCategory($groceries)->create(['amount' => -300_000]);

        Livewire::test(Dashboard::class)
            ->assertSeeHtml("chart('donut'")
            ->assertSeeHtml("chart('trend'")
            ->assertSeeHtml("chart('flow'")
            ->assertViewHas('donut', fn (array $donut) => $donut['total'] === 300_000
                && $donut['items'][0]['name'] === 'Potraviny'
                && str_contains($donut['items'][0]['url'], 'category='.$groceries->id)
                && str_contains($donut['items'][0]['url'], 'month=2026-10'));
    }

    public function test_an_empty_month_shows_messages_instead_of_charts(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        BankAccount::factory()->create();

        Livewire::test(Dashboard::class)
            ->assertSee('V tomto měsíci nejsou žádné výdaje.')
            ->assertDontSeeHtml("chart('donut'")
            ->assertDontSeeHtml("chart('flow'");
    }

    public function test_category_rows_link_to_the_filtered_transactions(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $account = BankAccount::factory()->create();
        Transaction::factory()->forAccount($account)->create(['amount' => -50_000, 'category_id' => null]);

        Livewire::test(Dashboard::class)
            ->assertSeeHtml(e(route('transactions', ['month' => '2026-10', 'category' => 'none'])));
    }

    public function test_the_owner_table_appears_only_without_filters(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $member = Member::factory()->create(['name' => 'Petr']);
        $account = BankAccount::factory()->ownedBy($member)->create();
        Transaction::factory()->forAccount($account)->create();

        Livewire::test(Dashboard::class)
            ->assertSee('Podle vlastníka')
            ->set('owner', (string) $member->id)
            ->assertDontSee('Podle vlastníka');
    }

    public function test_it_moves_between_months(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        BankAccount::factory()->create();

        Livewire::test(Dashboard::class)
            ->call('previousMonth')
            ->assertSet('month', '2026-09')
            ->assertSee('září 2026');
    }

    public function test_it_shows_fixed_expenses_and_what_is_left_after_them(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $account = BankAccount::factory()->create();
        Transaction::factory()->income()->forAccount($account)->create(['amount' => 5_000_000]);
        $rent = RecurringPayment::factory()->create(['amount' => 1_650_000, 'bank_account_id' => $account->id]);
        Transaction::factory()->forRecurring($rent)->on('2026-10-15')->create();
        RecurringPayment::factory()->create(['amount' => 30_000, 'due_day' => 28, 'bank_account_id' => $account->id]);

        Livewire::test(Dashboard::class)
            ->assertViewHas('summary', fn ($summary) => $summary->fixedExpenses === 1_680_000 && $summary->leftAfterFixed() === 3_320_000)
            ->assertSee('Fixní výdaje')
            ->assertSee('Zbývá po fixních');
    }

    public function test_the_recurring_card_lists_payments_due_this_month_with_status(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $account = BankAccount::factory()->create();
        RecurringPayment::factory()->create(['name' => 'Nájem', 'due_day' => 1, 'bank_account_id' => $account->id]);
        RecurringPayment::factory()->create(['name' => 'Pojištění', 'frequency' => PaymentFrequency::Yearly, 'start_month' => '2026-03-01', 'bank_account_id' => $account->id]);

        Livewire::test(Dashboard::class)
            ->assertSee('Pravidelné platby v měsíci')
            ->assertSeeInOrder(['Nájem', 'Chybí'])
            ->assertDontSee('Pojištění');
    }
}
