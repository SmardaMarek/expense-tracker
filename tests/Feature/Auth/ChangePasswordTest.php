<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_changes_the_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'old-password-123')
            ->set('password', 'new-password-456')
            ->set('password_confirmation', 'new-password-456')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
    }

    public function test_it_rejects_a_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'not-my-password')
            ->set('password', 'new-password-456')
            ->set('password_confirmation', 'new-password-456')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_it_rejects_a_new_password_that_is_too_short(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'old-password-123')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('updatePassword')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_it_rejects_a_mismatched_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'old-password-123')
            ->set('password', 'new-password-456')
            ->set('password_confirmation', 'different-password-789')
            ->call('updatePassword')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_it_rejects_reusing_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'old-password-123')
            ->set('password', 'old-password-123')
            ->set('password_confirmation', 'old-password-123')
            ->call('updatePassword')
            ->assertHasErrors('password');
    }

    public function test_the_new_password_works_for_the_next_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'old-password-123')
            ->set('password', 'new-password-456')
            ->set('password_confirmation', 'new-password-456')
            ->call('updatePassword');
        auth()->logout();

        $attempt = auth()->attempt(['email' => $user->email, 'password' => 'new-password-456']);

        $this->assertTrue($attempt);
    }
}
