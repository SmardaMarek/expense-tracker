<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Categories\DefaultCategories;
use App\Enums\CategoryType;
use App\Livewire\Categories;
use App\Models\Category;
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

        $response = $this->actingAs($user)->get(route('categories'));

        $response->assertOk();
        $response->assertSeeInOrder(['Výdaje', 'Potraviny', 'Příjmy', 'Mzda']);
    }

    public function test_it_offers_default_categories_when_there_are_none(): void
    {
        Livewire::test(Categories::class)
            ->assertSee('Přidat výchozí kategorie')
            ->call('addDefaults')
            ->assertDontSee('Přidat výchozí kategorie');

        $this->assertSame(count(DefaultCategories::EXPENSES), Category::query()->ofType(CategoryType::Expense)->count());
        $this->assertSame(count(DefaultCategories::INCOME), Category::query()->ofType(CategoryType::Income)->count());
        $this->assertDatabaseHas('categories', ['type' => 'expense', 'name' => 'Potraviny']);
        $this->assertDatabaseHas('categories', ['type' => 'income', 'name' => 'Mzda']);
    }

    public function test_defaults_are_not_added_when_categories_already_exist(): void
    {
        Category::factory()->create(['name' => 'Vlastní']);

        Livewire::test(Categories::class)->call('addDefaults');

        $this->assertDatabaseCount('categories', 1);
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
}
