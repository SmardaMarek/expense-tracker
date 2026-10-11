<?php

declare(strict_types=1);

namespace Tests\Unit\Statements;

use App\Enums\StatementBank;
use App\Statements\BalanceCheck;
use App\Statements\ParsedStatement;
use App\Statements\ParsedTransaction;
use App\Statements\Parsers\CsobStatementParser;
use PHPUnit\Framework\TestCase;

class CsobStatementParserTest extends TestCase
{
    public function test_it_recognizes_a_csob_statement(): void
    {
        $this->assertTrue((new CsobStatementParser)->supports($this->fixture()));
        $this->assertFalse((new CsobStatementParser)->supports('Air Bank a.s. Výpis z účtu'));
    }

    public function test_it_reads_the_header(): void
    {
        $statement = $this->parse();

        $this->assertSame(StatementBank::Csob, $statement->bank);
        $this->assertSame('123456789/0300', $statement->accountNumber);
        $this->assertSame('2026-09-01', $statement->periodFrom->toDateString());
        $this->assertSame('2026-09-30', $statement->periodTo->toDateString());
        $this->assertSame(1_000_000, $statement->openingBalance);
        $this->assertSame(3_489_454, $statement->closingBalance);
    }

    public function test_it_reads_every_transaction_across_pages(): void
    {
        $statement = $this->parse();

        $this->assertCount(6, $statement->transactions);
        $this->assertSame(['10001', '10002', '10003', '10004', '10005', '10006'], array_map(fn (ParsedTransaction $t) => $t->reference, $statement->transactions));
    }

    public function test_the_parsed_statement_passes_the_balance_check(): void
    {
        BalanceCheck::verify($this->parse());

        $this->addToAssertionCount(1);
    }

    public function test_an_outgoing_transfer_has_account_variable_symbol_and_message(): void
    {
        $transfer = $this->parse()->transactions[0];

        $this->assertSame('2026-09-02', $transfer->postedOn->toDateString());
        $this->assertSame(-150_000, $transfer->amount);
        $this->assertSame(850_000, $transfer->balanceAfter);
        $this->assertSame('Odchozí úhrada okamžitá', $transfer->typeLabel);
        $this->assertSame('2000145399/0800', $transfer->counterpartyAccount);
        $this->assertSame('123456', $transfer->variableSymbol);
        $this->assertSame('Najem zari', $transfer->message);
    }

    public function test_a_card_payment_uses_the_merchant_and_the_purchase_date(): void
    {
        $card = $this->parse()->transactions[1];

        $this->assertSame('ALBERT 1234 PRAHA', $card->counterpartyName);
        $this->assertSame('2026-09-03', $card->postedOn->toDateString());
        $this->assertSame('2026-09-01', $card->transactedOn->toDateString());
        $this->assertSame(-123_456, $card->amount);
        $this->assertNull($card->counterpartyAccount);
        $this->assertSame('Praha', $card->message);
    }

    public function test_a_foreign_card_payment_keeps_the_original_amount_in_the_message(): void
    {
        $card = $this->parse()->transactions[2];

        $this->assertSame('1.10 EUR · Stockholm', $card->message);
        $this->assertSame('2026-09-04', $card->transactedOn->toDateString());
    }

    public function test_an_incoming_payment_takes_the_counterparty_name_from_its_column(): void
    {
        $income = $this->parse()->transactions[3];

        $this->assertSame(4_500_000, $income->amount);
        $this->assertSame('Příchozí úhrada', $income->typeLabel);
        $this->assertSame('Zaměstnavatel s.r.o.', $income->counterpartyName);
        $this->assertSame('1234567890/0100', $income->counterpartyAccount);
        $this->assertSame('9/2026 - Mzda Jan Testovaci - Zari 9', $income->message);
    }

    public function test_a_standing_order_separates_its_recipient_from_the_type(): void
    {
        $order = $this->parse()->transactions[4];

        $this->assertSame('Trvalý příkaz elektronicky 362', $order->typeLabel);
        $this->assertSame('Nadace Test, o.p.s.', $order->counterpartyName);
        $this->assertSame('19-2000145399/0800', $order->counterpartyAccount);
        $this->assertSame('11111111', $order->variableSymbol);
    }

    public function test_page_footers_and_the_final_notice_are_not_added_to_messages(): void
    {
        $statement = $this->parse();

        $this->assertStringNotContainsString('Strana', (string) $statement->transactions[3]->message);
        $this->assertSame('Praha', $statement->transactions[5]->message);
    }

    public function test_a_period_across_new_year_gives_each_row_the_right_year(): void
    {
        $text = str_replace(
            ['1. 9. 2026 - 30. 9. 2026', '02.09.', '30.09.'],
            ['16. 12. 2026 - 15. 1. 2027', '20.12.', '10.01.'],
            $this->fixture(),
        );

        $transactions = (new CsobStatementParser)->parse($text)->transactions;

        $this->assertSame('2026-12-20', $transactions[0]->postedOn->toDateString());
        $this->assertSame('2027-01-10', $transactions[5]->postedOn->toDateString());
    }

    private function parse(): ParsedStatement
    {
        return (new CsobStatementParser)->parse($this->fixture());
    }

    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__.'/../../Fixtures/Statements/csob-invented.txt');
    }
}
