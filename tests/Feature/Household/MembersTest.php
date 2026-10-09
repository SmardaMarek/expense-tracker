<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Livewire\Auth\ChangePassword;
use App\Livewire\Household\Members;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_shows_household_and_password_forms(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings'));

        $response->assertOk();
        $response->assertSeeLivewire(Members::class);
        $response->assertSeeLivewire(ChangePassword::class);
    }

    public function test_it_saves_both_names(): void
    {
        Livewire::test(Members::class)
            ->set('first_name', 'Marek')
            ->set('second_name', 'Jana')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('members', ['position' => Member::FIRST, 'name' => 'Marek']);
        $this->assertDatabaseHas('members', ['position' => Member::SECOND, 'name' => 'Jana']);
    }

    public function test_the_second_person_is_optional_at_first(): void
    {
        Livewire::test(Members::class)
            ->set('first_name', 'Marek')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('members', 1);
    }

    public function test_the_first_person_is_required(): void
    {
        Livewire::test(Members::class)
            ->set('first_name', '   ')
            ->set('second_name', 'Jana')
            ->call('save')
            ->assertHasErrors('first_name');

        $this->assertDatabaseCount('members', 0);
    }

    public function test_the_two_names_must_differ(): void
    {
        Livewire::test(Members::class)
            ->set('first_name', 'Marek')
            ->set('second_name', 'Marek')
            ->call('save')
            ->assertHasErrors('second_name');
    }

    public function test_it_renames_existing_people_without_adding_rows(): void
    {
        Member::factory()->create(['name' => 'Marek']);
        Member::factory()->second()->create(['name' => 'Jana']);

        Livewire::test(Members::class)
            ->assertSet('first_name', 'Marek')
            ->assertSet('second_name', 'Jana')
            ->set('second_name', 'Janička')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseHas('members', ['position' => Member::SECOND, 'name' => 'Janička']);
    }

    public function test_a_saved_second_person_cannot_be_removed(): void
    {
        Member::factory()->create(['name' => 'Marek']);
        Member::factory()->second()->create(['name' => 'Jana']);

        Livewire::test(Members::class)
            ->set('second_name', '')
            ->call('save')
            ->assertHasErrors('second_name');

        $this->assertDatabaseHas('members', ['position' => Member::SECOND, 'name' => 'Jana']);
    }
}
