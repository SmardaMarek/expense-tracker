<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Enums\TransactionSource;
use App\Models\BankAccount;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Statements\ParsedStatement;
use App\Statements\Parsers\CsobStatementParser;
use App\Statements\StatementException;
use App\Statements\StatementImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_every_row_of_the_statement(): void
    {
        $account = BankAccount::factory()->create(['account_number' => '123456789/0300']);

        $import = app(StatementImporter::class)->import($this->statement(), 'hash-1');

        $this->assertSame(6, $import->imported_count);
        $this->assertSame(0, $import->skipped_count);
        $this->assertSame(6, Transaction::query()->where('bank_account_id', $account->id)->count());
        $this->assertDatabaseHas('statement_imports', ['bank' => 'csob', 'file_hash' => 'hash-1', 'opening_balance' => 1_000_000, 'closing_balance' => 3_489_454]);
    }

    public function test_imported_rows_keep_their_details(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);

        app(StatementImporter::class)->import($this->statement(), 'hash-1');

        $this->assertDatabaseHas('transactions', [
            'bank_reference' => '10004',
            'amount' => 4_500_000,
            'type' => 'income',
            'counterparty_name' => 'Zaměstnavatel s.r.o.',
            'counterparty_account' => '1234567890/0100',
            'source' => TransactionSource::Import->value,
        ]);
        $this->assertDatabaseHas('transactions', ['bank_reference' => '10001', 'type' => 'expense', 'variable_symbol' => '123456', 'message' => 'Najem zari']);
    }

    public function test_card_payments_are_dated_by_purchase_and_keep_the_posting_date(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);

        app(StatementImporter::class)->import($this->statement(), 'hash-1');

        $card = Transaction::query()->where('bank_reference', '10006')->firstOrFail();
        $this->assertSame('2026-09-28', $card->booked_on->toDateString());
        $this->assertSame('2026-09-30', $card->posted_on->toDateString());
    }

    public function test_a_payment_to_another_own_account_becomes_a_transfer(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        BankAccount::factory()->create(['account_number' => '19-2000145399/0800']);

        app(StatementImporter::class)->import($this->statement(), 'hash-1');

        $this->assertDatabaseHas('transactions', ['bank_reference' => '10005', 'type' => 'transfer', 'amount' => -500_000]);
        $this->assertDatabaseHas('transactions', ['bank_reference' => '10001', 'type' => 'expense']);
    }

    public function test_importing_the_same_statement_again_skips_every_row(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $importer = app(StatementImporter::class);
        $importer->import($this->statement(), 'hash-1');

        $second = $importer->import($this->statement(), 'hash-1');

        $this->assertSame(0, $second->imported_count);
        $this->assertSame(6, $second->skipped_count);
        $this->assertDatabaseCount('transactions', 6);
        $this->assertSame(2, StatementImport::query()->count());
    }

    public function test_the_preview_marks_rows_that_are_already_imported(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $importer = app(StatementImporter::class);
        $importer->import($this->statement(), 'hash-1');

        $preview = $importer->preview($this->statement());

        $this->assertSame(0, $preview->newCount());
        $this->assertSame(6, $preview->duplicateCount());
    }

    public function test_an_unknown_account_is_rejected(): void
    {
        BankAccount::factory()->create(['account_number' => '1234567899/0300']);

        $this->expectException(StatementException::class);
        $this->expectExceptionMessage('123456789/0300');

        app(StatementImporter::class)->preview($this->statement());
    }

    public function test_an_archived_account_is_rejected(): void
    {
        BankAccount::factory()->archived()->create(['account_number' => '123456789/0300', 'name' => 'Starý účet']);

        $this->expectException(StatementException::class);
        $this->expectExceptionMessage('Starý účet');

        app(StatementImporter::class)->preview($this->statement());
    }

    public function test_manually_entered_transactions_are_not_treated_as_duplicates(): void
    {
        $account = BankAccount::factory()->create(['account_number' => '123456789/0300']);
        Transaction::factory()->forAccount($account)->on('2026-09-02')->create(['amount' => -150_000]);

        $preview = app(StatementImporter::class)->preview($this->statement());

        $this->assertSame(6, $preview->newCount());
    }

    public function test_rows_chosen_as_hidden_are_imported_hidden(): void
    {
        BankAccount::factory()->create(['account_number' => '123456789/0300']);
        $importer = app(StatementImporter::class);
        $key = $importer->preview($this->statement())->rows[5]->importKey;

        $importer->import($this->statement(), 'hash-1', [$key]);

        $this->assertDatabaseHas('transactions', ['bank_reference' => '10006', 'is_hidden' => true]);
        $this->assertDatabaseHas('transactions', ['bank_reference' => '10001', 'is_hidden' => false]);
    }

    private function statement(): ParsedStatement
    {
        return (new CsobStatementParser)->parse((string) file_get_contents(base_path('tests/Fixtures/Statements/csob-invented.txt')));
    }
}
