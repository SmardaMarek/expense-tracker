<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Categories\DefaultCategories;
use App\Enums\CategoryPurpose;
use App\Enums\CategoryType;
use App\Livewire\Categories;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $response = $this->get(route('categories'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_lists_categories_grouped_by_type(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Potraviny']);
        Category::factory()->income()->create(['name' => 'Mzda']);
        Category::factory()->transfer()->create(['name' => 'Na spoření']);

        $response = $this->actingAs($user)->get(route('categories'));

        $response->assertOk();
        $response->assertSeeInOrder(['Výdaje', 'Potraviny', 'Příjmy', 'Mzda', 'Převody', 'Na spoření']);
    }

    public function test_categories_are_sorted_in_czech_alphabetical_order(): void
    {
        foreach (['Zdraví', 'Úroky', 'Čistírna', 'Cestování', 'Auto', 'Šperky', 'Sport'] as $name) {
            Category::factory()->create(['name' => $name]);
        }

        Livewire::test(Categories::class)
            ->assertViewHas('groups', fn ($groups) => $groups['expense']->pluck('name')->all()
                === ['Auto', 'Cestování', 'Čistírna', 'Sport', 'Šperky', 'Úroky', 'Zdraví']);
    }

    public function test_it_offers_default_categories_when_there_are_none(): void
    {
        Livewire::test(Categories::class)
            ->assertSee('Přidat výchozí kategorie')
            ->call('addDefaults')
            ->assertDontSee('Přidat výchozí kategorie');

        $this->assertSame(count(DefaultCategories::EXPENSES), Category::query()->ofType(CategoryType::Expense)->count());
        $this->assertSame(count(DefaultCategories::INCOME), Category::query()->ofType(CategoryType::Income)->count());
        $this->assertSame(count(DefaultCategories::TRANSFERS), Category::query()->ofType(CategoryType::Transfer)->count());
        $this->assertDatabaseHas('categories', ['type' => 'expense', 'name' => 'Potraviny']);
        $this->assertDatabaseHas('categories', ['type' => 'income', 'name' => 'Mzda']);
        $this->assertDatabaseHas('categories', ['type' => 'transfer', 'name' => 'Příspěvek na společný účet']);
    }

    public function test_an_empty_group_offers_its_own_defaults(): void
    {
        Category::factory()->create(['name' => 'Vlastní výdaj']);
        Category::factory()->income()->create(['name' => 'Vlastní příjem']);

        Livewire::test(Categories::class)
            ->assertSeeHtml("addDefaults('transfer')")
            ->assertDontSeeHtml("addDefaults('expense')")
            ->call('addDefaults', 'transfer')
            ->assertDontSeeHtml("addDefaults('transfer')");

        $this->assertSame(count(DefaultCategories::TRANSFERS), Category::query()->ofType(CategoryType::Transfer)->count());
        $this->assertSame(1, Category::query()->ofType(CategoryType::Expense)->count());
        $this->assertSame(1, Category::query()->ofType(CategoryType::Income)->count());
    }

    public function test_group_defaults_are_not_added_when_the_group_has_categories(): void
    {
        Category::factory()->transfer()->create(['name' => 'Můj převod']);

        Livewire::test(Categories::class)->call('addDefaults', 'transfer');

        $this->assertSame(1, Category::query()->ofType(CategoryType::Transfer)->count());
    }

    public function test_one_add_button_opens_the_form_with_a_type_choice(): void
    {
        Livewire::test(Categories::class)
            ->assertSee('Přidat kategorii')
            ->call('create')
            ->assertSet('form_open', true)
            ->assertSet('category_type', 'expense')
            ->assertDontSee('Přidat kategorii')
            ->set('category_type', 'transfer')
            ->set('category_name', 'Na dovolenou')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['type' => 'transfer', 'name' => 'Na dovolenou']);
    }

    public function test_defaults_only_fill_groups_that_are_empty(): void
    {
        Category::factory()->create(['name' => 'Vlastní']);

        Livewire::test(Categories::class)->call('addDefaults');

        $this->assertSame(1, Category::query()->ofType(CategoryType::Expense)->count());
        $this->assertSame(count(DefaultCategories::INCOME), Category::query()->ofType(CategoryType::Income)->count());
        $this->assertSame(count(DefaultCategories::TRANSFERS), Category::query()->ofType(CategoryType::Transfer)->count());
    }

    public function test_it_creates_an_expense_category(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'expense')
            ->set('category_name', 'Kroužky')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form_open', false);

        $this->assertDatabaseHas('categories', ['type' => 'expense', 'name' => 'Kroužky']);
    }

    public function test_it_creates_an_income_category(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'income')
            ->set('category_name', 'Bonus')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['type' => 'income', 'name' => 'Bonus']);
    }

    public function test_a_transfer_category_can_have_a_purpose(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'transfer')
            ->set('category_name', 'Spořicí účet')
            ->set('category_purpose', 'savings_deposit')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['type' => 'transfer', 'name' => 'Spořicí účet', 'purpose' => 'savings_deposit']);
    }

    public function test_a_purpose_is_not_stored_for_expense_categories(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'expense')
            ->set('category_name', 'Kino')
            ->set('category_purpose', 'investment')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Kino', 'purpose' => null]);
    }

    public function test_the_purpose_field_is_shown_only_for_transfers(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'expense')
            ->assertDontSee('Účel v přehledu')
            ->set('category_type', 'transfer')
            ->assertSee('Účel v přehledu');
    }

    public function test_editing_changes_and_clears_the_purpose(): void
    {
        $category = Category::factory()->transfer()->create(['name' => 'Broker', 'purpose' => CategoryPurpose::Investment]);

        Livewire::test(Categories::class)
            ->call('edit', $category->id)
            ->assertSet('category_purpose', 'investment')
            ->set('category_purpose', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($category->fresh()->purpose);
    }

    public function test_an_unknown_purpose_is_rejected(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'transfer')
            ->set('category_name', 'Něco')
            ->set('category_purpose', 'lottery')
            ->call('save')
            ->assertHasErrors('category_purpose');
    }

    public function test_default_transfer_categories_come_with_their_purposes(): void
    {
        Livewire::test(Categories::class)->call('addDefaults', 'transfer');

        $this->assertDatabaseHas('categories', ['name' => 'Na spoření', 'purpose' => 'savings_deposit']);
        $this->assertDatabaseHas('categories', ['name' => 'Ze spoření', 'purpose' => 'savings_withdrawal']);
        $this->assertDatabaseHas('categories', ['name' => 'Investice', 'purpose' => 'investment']);
        $this->assertDatabaseHas('categories', ['name' => 'Příspěvek na společný účet', 'purpose' => null]);
    }

    public function test_it_requires_a_name(): void
    {
        Livewire::test(Categories::class)
            ->call('create', 'expense')
            ->set('category_name', '   ')
            ->call('save')
            ->assertHasErrors('category_name');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_it_rejects_a_duplicate_name_regardless_of_letter_case(): void
    {
        Category::factory()->create(['name' => 'Čistírna']);

        Livewire::test(Categories::class)
            ->call('create', 'expense')
            ->set('category_name', 'čistírna')
            ->call('save')
            ->assertHasErrors('category_name');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_the_same_name_is_allowed_for_the_other_type(): void
    {
        Category::factory()->create(['name' => 'Dárky']);

        Livewire::test(Categories::class)
            ->call('create', 'income')
            ->set('category_name', 'Dárky')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('categories', 2);
    }

    public function test_it_renames_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Jidlo']);

        Livewire::test(Categories::class)
            ->call('edit', $category->id)
            ->assertSet('category_name', 'Jidlo')
            ->set('category_name', 'Jídlo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Jídlo', $category->fresh()->name);
    }

    public function test_renaming_cannot_change_the_type(): void
    {
        $category = Category::factory()->create(['name' => 'Nájem']);

        Livewire::test(Categories::class)
            ->call('edit', $category->id)
            ->set('category_type', 'income')
            ->set('category_name', 'Nájemné')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(CategoryType::Expense, $category->fresh()->type);
    }

    public function test_renaming_to_another_existing_name_is_rejected(): void
    {
        Category::factory()->create(['name' => 'Doprava']);
        $category = Category::factory()->create(['name' => 'Auto']);

        Livewire::test(Categories::class)
            ->call('edit', $category->id)
            ->set('category_name', 'Doprava')
            ->call('save')
            ->assertHasErrors('category_name');

        $this->assertSame('Auto', $category->fresh()->name);
    }

    public function test_it_archives_and_restores_a_category(): void
    {
        $category = Category::factory()->create();

        $component = Livewire::test(Categories::class)->call('archive', $category->id);
        $this->assertNotNull($category->fresh()->archived_at);

        $component->call('restore', $category->id);
        $this->assertNull($category->fresh()->archived_at);
    }

    public function test_archived_categories_are_listed_separately(): void
    {
        Category::factory()->create(['name' => 'Aktivní']);
        Category::factory()->archived()->create(['name' => 'Stará']);

        Livewire::test(Categories::class)
            ->assertViewHas('groups', fn ($groups) => $groups['expense']->pluck('name')->all() === ['Aktivní'])
            ->assertViewHas('archivedCategories', fn ($archived) => $archived->pluck('name')->all() === ['Stará']);
    }

    public function test_it_deletes_a_category(): void
    {
        $category = Category::factory()->create();

        Livewire::test(Categories::class)->call('delete', $category->id);

        $this->assertModelMissing($category);
    }

    public function test_a_category_used_by_transactions_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Transaction::factory()->inCategory($category)->create();

        Livewire::test(Categories::class)
            ->call('delete', $category->id)
            ->assertSee('Kategorie je použitá v transakcích nebo pravidelných platbách, proto ji nelze smazat.');

        $this->assertModelExists($category);
    }
}
