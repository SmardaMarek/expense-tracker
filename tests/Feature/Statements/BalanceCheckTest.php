<?php

declare(strict_types=1);

namespace Tests\Feature\Statements;

use App\Enums\StatementBank;
use App\Statements\BalanceCheck;
use App\Statements\ParsedStatement;
use App\Statements\ParsedTransaction;
use App\Statements\StatementException;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BalanceCheckTest extends TestCase
{
    public function test_a_wrong_running_balance_names_the_row(): void
    {
        $statement = $this->statement(100_000, 50_000, [[-20_000, 80_000], [-30_000, 49_999]]);

        $this->expectException(StatementException::class);
        $this->expectExceptionMessage('řádku 2');

        BalanceCheck::verify($statement);
    }

    public function test_a_wrong_closing_balance_is_rejected(): void
    {
        $statement = $this->statement(100_000, 60_000, [[-20_000, 80_000], [-30_000, 50_000]]);

        $this->expectException(StatementException::class);
        $this->expectExceptionMessage('konečný zůstatek');

        BalanceCheck::verify($statement);
    }

    public function test_a_consistent_statement_passes(): void
    {
        BalanceCheck::verify($this->statement(100_000, 50_000, [[-20_000, 80_000], [-30_000, 50_000]]));

        $this->addToAssertionCount(1);
    }

    /**
     * @param  list<array{int, int}>  $rows
     */
    private function statement(int $opening, int $closing, array $rows): ParsedStatement
    {
        $date = CarbonImmutable::parse('2026-09-10');

        return new ParsedStatement(StatementBank::Csob, '123456789/0300', $date->startOfMonth(), $date->endOfMonth(), $opening, $closing, array_map(
            fn (array $row): ParsedTransaction => new ParsedTransaction($date, $date, $row[0], $row[1], '1', 'Odchozí úhrada', null, null, null, null),
            $rows,
        ));
    }
}
