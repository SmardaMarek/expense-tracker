<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_is_shown_while_no_user_exists(): void
    {
        $response = $this->get(route('setup'));

        $response->assertOk();
        $response->assertSeeLivewire(Setup::class);
    }

    public function test_login_redirects_to_setup_while_no_user_exists(): void
    {
        $response = $this->get(route('login'));

        $response->assertRedirect(route('setup'));
    }

    public function test_guest_visiting_the_dashboard_ends_up_on_setup_while_no_user_exists(): void
    {
        $response = $this->followingRedirects()->get(route('dashboard'));

        $response->assertSeeLivewire(Setup::class);
    }

    public function test_it_creates_the_account_and_signs_in(): void
    {
        $component = Livewire::test(Setup::class)
            ->set('name', 'Marek')
            ->set('email', 'marek@example.com')
            ->set('password', 'correct-horse-battery')
            ->set('password_confirmation', 'correct-horse-battery')
            ->call('register');

        $component->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'marek@example.com', 'name' => 'Marek']);
        $this->assertTrue(Hash::check('correct-horse-battery', User::query()->firstOrFail()->password));
    }

    public function test_setup_page_is_unavailable_once_a_user_exists(): void
    {
        User::factory()->create();

        $response = $this->get(route('setup'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_refuses_to_create_a_second_account(): void
    {
        $component = Livewire::test(Setup::class);
        User::factory()->create();

        $component
            ->set('name', 'Intruder')
            ->set('email', 'intruder@example.com')
            ->set('password', 'correct-horse-battery')
            ->set('password_confirmation', 'correct-horse-battery')
            ->call('register')
            ->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_it_rejects_invalid_input(array $input, string $invalidField): void
    {
        $valid = [
            'name' => 'Marek',
            'email' => 'marek@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ];

        $component = Livewire::test(Setup::class);
        foreach (array_merge($valid, $input) as $property => $value) {
            $component->set($property, $value);
        }
        $component->call('register');

        $component->assertHasErrors($invalidField);
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'mismatched confirmation' => [['password_confirmation' => 'something-else-entirely'], 'password'],
        ];
    }
}
