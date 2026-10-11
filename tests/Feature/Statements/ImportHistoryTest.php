<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Livewire\StatementImports;
use App\Models\BankAccount;
use App\Models\StatementImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

class ImportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_history_lists_the_newest_import_first_by_default(): void
    {
        $older = StatementImport::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = StatementImport::factory()->create(['created_at' => now()->subDay()]);

        Livewire::test(StatementImports::class)
            ->assertSee('Historie importů')
            ->assertViewHas('imports', fn (LengthAwarePaginator $imports) => $imports->pluck('id')->all() === [$newer->id, $older->id]);
    }

    public function test_clicking_a_column_sorts_by_it_and_clicking_again_reverses(): void
    {
        StatementImport::factory()->create(['imported_count' => 30]);
        StatementImport::factory()->create(['imported_count' => 5]);
        StatementImport::factory()->create(['imported_count' => 12]);

        Livewire::test(StatementImports::class)
            ->call('sortBy', 'imported')
            ->assertSet('sort', 'imported')
            ->assertSet('direction', 'asc')
            ->assertViewHas('imports', fn ($imports) => $imports->pluck('imported_count')->all() === [5, 12, 30])
            ->call('sortBy', 'imported')
            ->assertSet('direction', 'desc')
            ->assertViewHas('imports', fn ($imports) => $imports->pluck('imported_count')->all() === [30, 12, 5]);
    }

    public function test_accounts_sort_in_czech_alphabetical_order(): void
    {
        foreach (['Zahradní účet', 'Účet Petr', 'Běžný účet', 'Cestovní'] as $name) {
            StatementImport::factory()->create(['bank_account_id' => BankAccount::factory()->create(['name' => $name])]);
        }

        Livewire::test(StatementImports::class)
            ->call('sortBy', 'account')
            ->assertViewHas('imports', fn ($imports) => $imports->pluck('bankAccount.name')->all() === ['Běžný účet', 'Cestovní', 'Účet Petr', 'Zahradní účet']);
    }

    public function test_sorting_by_period_orders_the_statement_months(): void
    {
        StatementImport::factory()->forPeriod('2026-09')->create();
        StatementImport::factory()->forPeriod('2026-07')->create();
        StatementImport::factory()->forPeriod('2026-08')->create();

        Livewire::test(StatementImports::class)
            ->call('sortBy', 'period')
            ->assertViewHas('imports', fn ($imports) => $imports->map(fn ($import) => $import->period_from->format('Y-m'))->all() === ['2026-07', '2026-08', '2026-09']);
    }

    public function test_an_unknown_sort_column_is_ignored(): void
    {
        Livewire::test(StatementImports::class)
            ->call('sortBy', 'file_hash')
            ->assertSet('sort', 'created');
    }

    public function test_search_ignores_letter_case_and_diacritics(): void
    {
        StatementImport::factory()->create(['bank_account_id' => BankAccount::factory()->create(['name' => 'Účet Petr'])]);
        StatementImport::factory()->create(['bank_account_id' => BankAccount::factory()->create(['name' => 'Společný'])]);

        Livewire::test(StatementImports::class)
            ->set('search', 'ucet')
            ->assertViewHas('imports', fn ($imports) => $imports->pluck('bankAccount.name')->all() === ['Účet Petr']);
    }

    public function test_search_finds_imports_by_month_name(): void
    {
        StatementImport::factory()->forPeriod('2026-09')->create();
        StatementImport::factory()->forPeriod('2026-08')->create();

        Livewire::test(StatementImports::class)
            ->set('search', 'září 2026')
            ->assertViewHas('imports', fn ($imports) => $imports->count() === 1 && $imports->first()->period_from->month === 9);
    }

    public function test_a_search_without_results_says_so(): void
    {
        StatementImport::factory()->create();

        Livewire::test(StatementImports::class)
            ->set('search', 'neexistuje')
            ->assertSee('Hledání neodpovídá žádný import.');
    }

    public function test_long_histories_are_split_into_pages(): void
    {
        StatementImport::factory()->count(17)->create();

        Livewire::test(StatementImports::class)
            ->assertViewHas('imports', fn ($imports) => $imports->count() === 15 && $imports->lastPage() === 2)
            ->assertSee('Strana 1 z 2 (celkem 17)')
            ->call('nextPage')
            ->assertViewHas('imports', fn ($imports) => $imports->count() === 2 && $imports->currentPage() === 2);
    }

    public function test_searching_returns_to_the_first_page(): void
    {
        StatementImport::factory()->count(17)->create();

        Livewire::test(StatementImports::class)
            ->call('nextPage')
            ->set('search', 'ČSOB')
            ->assertViewHas('imports', fn ($imports) => $imports->currentPage() === 1);
    }

    public function test_column_headers_expose_the_sort_state_to_screen_readers(): void
    {
        StatementImport::factory()->create();

        Livewire::test(StatementImports::class)
            ->assertSeeHtml('aria-sort="descending"')
            ->call('sortBy', 'bank')
            ->assertSeeHtml('aria-sort="ascending"');
    }
}
