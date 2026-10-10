<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\CategoryPurpose;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Member;
use App\Models\Transaction;
use App\Reports\MoneyFlow;
use App\Reports\MonthlySummary;
use App\Reports\MonthlySummaryData;
use App\Reports\Totals;
use App\Transactions\TransactionFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    private Member $petr;

    private BankAccount $petrAccount;

    private BankAccount $petrSavings;

    private BankAccount $evaAccount;

    private BankAccount $sharedAccount;

    private Category $groceries;

    private Category $rent;

    private Category $salary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_it_sums_income_and_expenses_and_ignores_transfers(): void
    {
        $this->seedHousehold();

        $totals = $this->build()->current;

        $this->assertSame(9_050_000, $totals->income);
        $this->assertSame(1_990_000, $totals->expenses);
        $this->assertSame(7_060_000, $totals->balance());
    }

    public function test_savings_count_each_movement_once_and_subtract_withdrawals(): void
    {
        $this->seedHousehold();

        $this->assertSame(600_000, $this->build()->current->saved);
    }

    public function test_investments_count_outgoing_transfers_only(): void
    {
        $this->seedHousehold();

        $this->assertSame(300_000, $this->build()->current->invested);
    }

    public function test_account_movement_includes_transfers(): void
    {
        $this->seedHousehold();

        $this->assertSame(6_560_000, $this->build()->current->movement);
    }

    public function test_it_compares_with_the_previous_month(): void
    {
        $this->seedHousehold();

        $previous = $this->build()->previous;

        $this->assertSame(4_800_000, $previous->income);
        $this->assertSame(250_000, $previous->expenses);
    }

    public function test_it_lists_expense_categories_by_amount_with_uncategorized_ones(): void
    {
        $this->seedHousehold();

        $rows = $this->build()->expenseCategories;

        $this->assertSame(['Nájem', 'Potraviny', 'Bez kategorie'], array_map(fn ($row) => $row->name, $rows));
        $this->assertSame([1_500_000, 470_000, 20_000], array_map(fn ($row) => $row->amount, $rows));
        $this->assertNull($rows[2]->categoryId);
        $this->assertEqualsWithDelta(15_000 / 19_900, $rows[0]->share, 0.0001);
    }

    public function test_it_lists_transfer_categories_with_both_directions(): void
    {
        $this->seedHousehold();

        $contribution = collect($this->build()->transferCategories)->firstWhere('name', 'Příspěvek na společný účet');

        $this->assertSame(1_000_000, $contribution->outgoing);
        $this->assertSame(1_000_000, $contribution->incoming);
    }

    public function test_it_breaks_totals_down_by_owner(): void
    {
        $this->seedHousehold();

        $owners = collect($this->build()->owners)->keyBy('name');

        $this->assertSame(['Petr', 'Eva', 'Společný'], $owners->keys()->all());
        $this->assertSame(5_000_000, $owners['Petr']->totals->income);
        $this->assertSame(170_000, $owners['Petr']->totals->expenses);
        $this->assertSame(1_600_000, $owners['Petr']->transfersOut);
        $this->assertSame(600_000, $owners['Petr']->transfersIn);
        $this->assertSame(500_000, $owners['Eva']->transfersOut);
        $this->assertSame(1_800_000, $owners['Společný']->totals->expenses);
        $this->assertSame(1_000_000, $owners['Společný']->transfersIn);
    }

    public function test_the_owner_breakdown_is_left_out_when_filtering(): void
    {
        $this->seedHousehold();

        $this->assertSame([], $this->build(owner: (string) $this->petr->id)->owners);
    }

    public function test_filtering_by_owner_limits_every_figure_to_their_accounts(): void
    {
        $this->seedHousehold();

        $totals = $this->build(owner: (string) $this->petr->id)->current;

        $this->assertSame(5_000_000, $totals->income);
        $this->assertSame(170_000, $totals->expenses);
        $this->assertSame(400_000, $totals->saved);
        $this->assertSame(0, $totals->invested);
        $this->assertSame(3_830_000, $totals->movement);
    }

    public function test_filtering_by_account_limits_the_figures_to_that_account(): void
    {
        $this->seedHousehold();

        $totals = $this->build(account: (string) $this->sharedAccount->id)->current;

        $this->assertSame(50_000, $totals->income);
        $this->assertSame(1_800_000, $totals->expenses);
    }

    public function test_the_trend_covers_twelve_months_ending_with_the_selected_one(): void
    {
        $this->seedHousehold();

        $trend = $this->build()->trend;

        $this->assertCount(MonthlySummary::TREND_MONTHS, $trend);
        $this->assertSame('2025-11', $trend[0]->month);
        $this->assertSame('2026-10', $trend[11]->month);
        $this->assertSame('říj 26', $trend[11]->label);
        $this->assertSame(9_050_000, $trend[11]->totals->income);
        $this->assertSame(4_800_000, $trend[10]->totals->income);
        $this->assertSame(0, $trend[0]->totals->income);
    }

    public function test_the_money_flow_balances_what_comes_in_with_what_goes_out(): void
    {
        $this->seedHousehold();

        $flow = $this->build()->flow;
        $into = array_sum(array_map(fn ($link) => $link['value'], array_filter($flow->links, fn ($link) => $link['target'] === MoneyFlow::HUB)));
        $outOf = array_sum(array_map(fn ($link) => $link['value'], array_filter($flow->links, fn ($link) => $link['source'] === MoneyFlow::HUB)));
        $targets = array_column(array_filter($flow->links, fn ($link) => $link['source'] === MoneyFlow::HUB), 'value', 'target');

        $this->assertSame(9_050_000, $into);
        $this->assertSame($into, $outOf);
        $this->assertSame(600_000, $targets[MoneyFlow::SAVED]);
        $this->assertSame(300_000, $targets[MoneyFlow::INVESTED]);
        $this->assertSame(6_160_000, $targets[MoneyFlow::LEFT_OVER]);
    }

    public function test_the_money_flow_shows_spending_beyond_income_as_earlier_money(): void
    {
        $flow = MoneyFlow::from(new Totals(income: 100_000, expenses: 300_000), [], [], MonthlySummary::TOP_FLOW_EXPENSES);

        $sources = array_column(array_filter($flow->links, fn ($link) => $link['target'] === MoneyFlow::HUB), 'value', 'source');

        $this->assertSame(200_000, $sources[MoneyFlow::FROM_RESERVES]);
        $this->assertArrayNotHasKey(MoneyFlow::LEFT_OVER, array_column($flow->nodes, 'key', 'key'));
    }

    public function test_the_money_flow_shows_net_withdrawals_from_savings_as_a_source(): void
    {
        $flow = MoneyFlow::from(new Totals(income: 100_000, expenses: 150_000, saved: -50_000), [], [], MonthlySummary::TOP_FLOW_EXPENSES);

        $sources = array_column(array_filter($flow->links, fn ($link) => $link['target'] === MoneyFlow::HUB), 'value', 'source');

        $this->assertSame(50_000, $sources[MoneyFlow::FROM_SAVINGS]);
        $this->assertArrayNotHasKey(MoneyFlow::FROM_RESERVES, $sources);
    }

    public function test_an_empty_month_has_zero_totals_and_no_flow(): void
    {
        $summary = $this->build();

        $this->assertEquals(new Totals, $summary->current);
        $this->assertTrue($summary->flow->isEmpty());
        $this->assertSame([], $summary->expenseCategories);
    }

    private function build(string $account = '', string $owner = ''): MonthlySummaryData
    {
        return app(MonthlySummary::class)->build(TransactionFilter::fromInput('2026-10', $account, $owner, '', ''));
    }

    private function seedHousehold(): void
    {
        $this->petr = Member::factory()->create(['name' => 'Petr']);
        $eva = Member::factory()->second()->create(['name' => 'Eva']);
        $this->petrAccount = BankAccount::factory()->ownedBy($this->petr)->create();
        $this->petrSavings = BankAccount::factory()->ownedBy($this->petr)->create();
        $this->evaAccount = BankAccount::factory()->ownedBy($eva)->create();
        $this->sharedAccount = BankAccount::factory()->create();

        $this->groceries = Category::factory()->create(['name' => 'Potraviny']);
        $this->rent = Category::factory()->create(['name' => 'Nájem']);
        $this->salary = Category::factory()->income()->create(['name' => 'Mzda']);
        $refunds = Category::factory()->income()->create(['name' => 'Vrácené peníze']);
        $contribution = Category::factory()->transfer()->create(['name' => 'Příspěvek na společný účet']);
        $toSavings = Category::factory()->transfer()->create(['name' => 'Na spoření', 'purpose' => CategoryPurpose::SavingsDeposit]);
        $fromSavings = Category::factory()->transfer()->create(['name' => 'Ze spoření', 'purpose' => CategoryPurpose::SavingsWithdrawal]);
        $investments = Category::factory()->transfer()->create(['name' => 'Investice', 'purpose' => CategoryPurpose::Investment]);

        $this->income($this->petrAccount, $this->salary, 5_000_000);
        $this->income($this->evaAccount, $this->salary, 4_000_000);
        $this->income($this->sharedAccount, $refunds, 50_000);

        $this->expense($this->sharedAccount, $this->rent, 1_500_000);
        $this->expense($this->sharedAccount, $this->groceries, 300_000);
        $this->expense($this->petrAccount, $this->groceries, 100_000);
        $this->expense($this->evaAccount, null, 20_000);
        $this->expense($this->petrAccount, $this->groceries, 70_000, '2026-10-31');
        $this->expense($this->petrAccount, $this->groceries, 99_900, '2026-11-01');

        $this->transfer($this->petrAccount, $contribution, -1_000_000);
        $this->transfer($this->sharedAccount, $contribution, 1_000_000);
        $this->transfer($this->petrAccount, $toSavings, -500_000);
        $this->transfer($this->petrSavings, $toSavings, 500_000);
        $this->transfer($this->evaAccount, $toSavings, -200_000);
        $this->transfer($this->petrSavings, $fromSavings, -100_000);
        $this->transfer($this->petrAccount, $fromSavings, 100_000);
        $this->transfer($this->evaAccount, $investments, -300_000);

        $this->income($this->petrAccount, $this->salary, 4_800_000, '2026-09-20');
        $this->expense($this->petrAccount, $this->groceries, 250_000, '2026-09-21');
    }

    private function income(BankAccount $account, Category $category, int $amount, string $date = '2026-10-10'): void
    {
        Transaction::factory()->income()->forAccount($account)->inCategory($category)->on($date)->create(['amount' => $amount]);
    }

    private function expense(BankAccount $account, ?Category $category, int $amount, string $date = '2026-10-10'): void
    {
        Transaction::factory()->forAccount($account)->on($date)->create(['amount' => -$amount, 'category_id' => $category?->id]);
    }

    private function transfer(BankAccount $account, Category $category, int $amount): void
    {
        Transaction::factory()->transferOut()->forAccount($account)->inCategory($category)->on('2026-10-12')->create(['amount' => $amount]);
    }
}
