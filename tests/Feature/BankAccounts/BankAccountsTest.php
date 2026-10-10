<?php

declare(strict_types=1);

namespace Tests\Feature\BankAccounts;

use App\Livewire\BankAccounts;
use App\Models\BankAccount;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BankAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $response = $this->get(route('accounts'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_lists_active_accounts_with_their_owners(): void
    {
        $user = User::factory()->create();
        $member = Member::factory()->create(['name' => 'Marek']);
        BankAccount::factory()->ownedBy($member)->create(['name' => 'Osobní účet']);
        BankAccount::factory()->create(['name' => 'Společný účet']);

        $response = $this->actingAs($user)->get(route('accounts'));

        $response->assertOk();
        $response->assertSeeInOrder(['Osobní účet', 'Marek']);
        $response->assertSeeInOrder(['Společný účet', 'Společný']);
    }

    public function test_row_actions_are_icon_buttons_with_accessible_labels(): void
    {
        $user = User::factory()->create();
        BankAccount::factory()->create();
        BankAccount::factory()->archived()->create();

        $response = $this->actingAs($user)->get(route('accounts'));

        $response->assertSee('aria-label="Upravit"', false);
        $response->assertSee('aria-label="Archivovat"', false);
        $response->assertSee('aria-label="Obnovit"', false);
        $response->assertSee('aria-label="Smazat"', false);
        $response->assertSee('title="Smazat"', false);
    }

    public function test_it_creates_an_account_owned_by_a_person(): void
    {
        $member = Member::factory()->create();

        Livewire::test(BankAccounts::class)
            ->call('create')
            ->set('account_name', 'Osobní účet')
            ->set('owner', (string) $member->id)
            ->set('account_number', '1234567899/0300')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form_open', false);

        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'Osobní účet',
            'member_id' => $member->id,
            'account_number' => '1234567899/0300',
        ]);
    }

    public function test_it_creates_a_shared_account(): void
    {
        Livewire::test(BankAccounts::class)
            ->call('create')
            ->set('account_name', 'Společný účet')
            ->set('owner', BankAccounts::SHARED)
            ->set('account_number', '1234567899/0300')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bank_accounts', ['name' => 'Společný účet', 'member_id' => null]);
    }

    public function test_it_stores_the_account_number_in_normalized_form(): void
    {
        Livewire::test(BankAccounts::class)
            ->set('account_name', 'Účet')
            ->set('account_number', ' 000019-2000145399 / 0800 ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bank_accounts', ['account_number' => '19-2000145399/0800']);
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_it_rejects_invalid_input(array $input, string $invalidField): void
    {
        $component = Livewire::test(BankAccounts::class)
            ->set('account_name', 'Účet')
            ->set('owner', BankAccounts::SHARED)
            ->set('account_number', '1234567899/0300');
        foreach ($input as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('save');

        $component->assertHasErrors($invalidField);
        $this->assertDatabaseCount('bank_accounts', 0);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['account_name' => ''], 'account_name'],
            'wrong checksum' => [['account_number' => '1234567898/0300'], 'account_number'],
            'missing bank code' => [['account_number' => '1234567899'], 'account_number'],
            'unknown owner' => [['owner' => '999'], 'owner'],
        ];
    }

    public function test_it_rejects_a_number_already_registered_in_another_format(): void
    {
        BankAccount::factory()->create(['account_number' => '19-2000145399/0800']);

        Livewire::test(BankAccounts::class)
            ->set('account_name', 'Duplicitní')
            ->set('account_number', '000019-2000145399/0800')
            ->call('save')
            ->assertHasErrors('account_number');

        $this->assertDatabaseCount('bank_accounts', 1);
    }

    public function test_it_edits_an_account_and_keeps_its_own_number(): void
    {
        $member = Member::factory()->create();
        $account = BankAccount::factory()->create(['name' => 'Starý název', 'account_number' => '1234567899/0300']);

        Livewire::test(BankAccounts::class)
            ->call('edit', $account->id)
            ->assertSet('account_name', 'Starý název')
            ->assertSet('owner', BankAccounts::SHARED)
            ->set('account_name', 'Nový název')
            ->set('owner', (string) $member->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $account->id,
            'name' => 'Nový název',
            'member_id' => $member->id,
            'account_number' => '1234567899/0300',
        ]);
    }

    public function test_it_archives_and_restores_an_account(): void
    {
        $account = BankAccount::factory()->create();

        $component = Livewire::test(BankAccounts::class)->call('archive', $account->id);
        $this->assertNotNull($account->fresh()->archived_at);

        $component->call('restore', $account->id);
        $this->assertNull($account->fresh()->archived_at);
    }

    public function test_archived_accounts_are_listed_separately(): void
    {
        BankAccount::factory()->create(['name' => 'Aktivní účet']);
        BankAccount::factory()->archived()->create(['name' => 'Starý účet']);

        Livewire::test(BankAccounts::class)
            ->assertViewHas('activeAccounts', fn ($accounts) => $accounts->pluck('name')->all() === ['Aktivní účet'])
            ->assertViewHas('archivedAccounts', fn ($accounts) => $accounts->pluck('name')->all() === ['Starý účet']);
    }

    public function test_it_deletes_an_account(): void
    {
        $account = BankAccount::factory()->create();

        Livewire::test(BankAccounts::class)->call('delete', $account->id);

        $this->assertModelMissing($account);
    }

    public function test_an_account_with_transactions_cannot_be_deleted(): void
    {
        $account = BankAccount::factory()->create();
        Transaction::factory()->forAccount($account)->create();

        Livewire::test(BankAccounts::class)
            ->call('delete', $account->id)
            ->assertSee('Účet je použitý v transakcích nebo pravidelných platbách, proto ho nelze smazat.');

        $this->assertModelExists($account);
    }
}
