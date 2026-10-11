<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Livewire\StatementImports;
use App\Models\BankAccount;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Statements\PdfTextExtractor;
use App\Statements\StatementException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class StatementImportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $this->get(route('import'))->assertRedirect(route('login'));
    }

    public function test_uploading_a_statement_shows_a_preview_without_saving_anything(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300', 'name' => 'Osobní ČSOB']);
        $this->fakeStatementText($this->fixture());

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSet('failure', null)
            ->assertSee('Zkontrolujte před importem')
            ->assertSee('Osobní ČSOB')
            ->assertSee('Zůstatky sedí')
            ->assertSee('Importovat 6 transakcí')
            ->assertSee('ALBERT 1234 PRAHA');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_confirming_imports_the_transactions_and_discards_the_file(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());

        $component = Livewire::test(StatementImports::class)->set('statement', $this->pdf());
        $component->call('import')
            ->assertSet('preview', null)
            ->assertSet('statement', null)
            ->assertSee('Importováno transakcí: 6');

        $this->assertDatabaseCount('transactions', 6);
        $this->assertDatabaseCount('statement_imports', 1);
    }

    public function test_a_second_upload_of_the_same_statement_shows_everything_as_already_imported(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());
        Livewire::test(StatementImports::class)->set('statement', $this->pdf())->call('import');

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSee('Už importované: 6')
            ->assertSee('Není co importovat');
    }

    public function test_an_unknown_account_shows_an_error_and_discards_the_file(): void
    {
        $this->fakeStatementText($this->fixture());

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSet('preview', null)
            ->assertSet('statement', null)
            ->assertSee('není mezi vašimi bankovními účty');
    }

    public function test_an_unsupported_statement_is_reported(): void
    {
        BankAccount::factory()->create();
        $this->fakeStatementText('Nějaký jiný dokument bez výpisu');

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSee('Tento formát výpisu zatím není podporovaný');
    }

    public function test_an_unreadable_file_is_reported(): void
    {
        $this->app->instance(PdfTextExtractor::class, new class implements PdfTextExtractor
        {
            public function extract(string $path): string
            {
                throw StatementException::unreadable();
            }
        });

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSee('Soubor se nepodařilo přečíst jako PDF výpis');
    }

    public function test_a_statement_with_a_broken_balance_is_not_imported(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText(str_replace('10002 -1 234,56 7 265,44', '10002 -1 234,56 7 265,45', $this->fixture()));

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSee('Kontrola zůstatků nesouhlasí na řádku 2');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_only_pdf_files_are_accepted(): void
    {
        Livewire::test(StatementImports::class)
            ->set('statement', UploadedFile::fake()->create('vypis.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->assertHasErrors('statement');
    }

    public function test_rows_can_be_hidden_in_the_preview_before_importing(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());

        $component = Livewire::test(StatementImports::class)->set('statement', $this->pdf());
        $ikeaKey = collect($component->get('preview')['rows'])->firstWhere('name', 'IKEA PRAHA')['key'];

        $component->call('toggleHidden', $ikeaKey)
            ->assertSee('Skryté: 1')
            ->assertSee('Skrytá platba')
            ->call('import');

        $this->assertDatabaseHas('transactions', ['counterparty_name' => 'IKEA PRAHA', 'is_hidden' => true]);
        $this->assertSame(5, Transaction::query()->where('is_hidden', false)->count());
    }

    public function test_hiding_a_row_twice_shows_it_again(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());

        $component = Livewire::test(StatementImports::class)->set('statement', $this->pdf());
        $key = $component->get('preview')['rows'][0]['key'];

        $component->call('toggleHidden', $key)->call('toggleHidden', $key)->assertSet('hidden_keys', []);
    }

    public function test_deleting_an_import_removes_only_its_own_transactions(): void
    {
        $account = BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $manual = Transaction::factory()->forAccount($account)->create();
        $this->fakeStatementText($this->fixture());
        Livewire::test(StatementImports::class)->set('statement', $this->pdf())->call('import');
        $import = StatementImport::query()->firstOrFail();

        Livewire::test(StatementImports::class)
            ->assertSeeHtml('wire:click="deleteImport('.$import->id.')"')
            ->call('deleteImport', $import->id)
            ->assertSee('Import byl smazán i s jeho transakcemi (6).')
            ->assertDontSee('Historie importů');

        $this->assertModelMissing($import);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertModelExists($manual);
    }

    public function test_a_deleted_import_can_be_imported_again(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());
        Livewire::test(StatementImports::class)->set('statement', $this->pdf())->call('import');
        Livewire::test(StatementImports::class)->call('deleteImport', StatementImport::query()->value('id'));

        Livewire::test(StatementImports::class)
            ->set('statement', $this->pdf())
            ->assertSee('Importovat 6 transakcí');
    }

    public function test_deleting_a_later_import_keeps_rows_owned_by_the_earlier_one(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());
        Livewire::test(StatementImports::class)->set('statement', $this->pdf())->call('import');
        Livewire::test(StatementImports::class)->set('statement', $this->pdf())->call('import');
        $second = StatementImport::query()->latest('id')->firstOrFail();

        Livewire::test(StatementImports::class)->call('deleteImport', $second->id);

        $this->assertDatabaseCount('transactions', 6);
        $this->assertDatabaseCount('statement_imports', 1);
    }

    public function test_the_confirmation_names_how_many_transactions_will_be_deleted(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $this->fakeStatementText($this->fixture());
        Livewire::test(StatementImports::class)->set('statement', $this->pdf())->call('import');

        Livewire::test(StatementImports::class)
            ->assertSee('Opravdu smazat tento import a jeho transakce (6)?');
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('vypis.pdf', 50, 'application/pdf');
    }

    private function fixture(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/Statements/csob-invented.txt'));
    }

    private function fakeStatementText(string $text): void
    {
        $this->app->instance(PdfTextExtractor::class, new readonly class($text) implements PdfTextExtractor
        {
            public function __construct(private string $text) {}

            public function extract(string $path): string
            {
                return $this->text;
            }
        });
    }
}
