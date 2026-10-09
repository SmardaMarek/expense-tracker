<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resets_the_password(): void
    {
        $user = User::factory()->create(['password' => 'forgotten-password']);

        $this->artisan('app:reset-password')
            ->expectsQuestion("New password for {$user->email}", 'brand-new-password')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_it_fails_when_no_account_exists(): void
    {
        $this->artisan('app:reset-password')
            ->expectsOutputToContain('No account exists yet')
            ->assertFailed();
    }
}
