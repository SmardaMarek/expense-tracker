<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
