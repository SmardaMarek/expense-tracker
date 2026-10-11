<?php

declare(strict_types=1);

namespace App\Statements;

use App\Enums\StatementBank;
use Carbon\CarbonImmutable;

final readonly class ParsedStatement
{
    /**
     * @param  list<ParsedTransaction>  $transactions
     */
    public function __construct(
        public StatementBank $bank,
        public string $accountNumber,
        public CarbonImmutable $periodFrom,
        public CarbonImmutable $periodTo,
        public int $openingBalance,
        public int $closingBalance,
        public array $transactions,
    ) {}
}
