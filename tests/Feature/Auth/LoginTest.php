<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_shown_once_a_user_exists(): void
    {
        User::factory()->create();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSeeLivewire(Login::class);
    }

    public function test_it_signs_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'correct-horse-battery')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_it_locks_out_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $component = Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('login');
        }

        $component
            ->set('password', 'correct-horse-battery')
            ->call('login')
            ->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_protected_pages(): void
    {
        User::factory()->create();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('settings'))->assertRedirect(route('login'));
    }

    public function test_signed_in_user_can_open_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_it_signs_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
