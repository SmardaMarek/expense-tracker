<?php

declare(strict_types=1);

namespace App\Statements;

use Carbon\CarbonImmutable;

final readonly class ParsedTransaction
{
    public function __construct(
        public CarbonImmutable $postedOn,
        public CarbonImmutable $transactedOn,
        public int $amount,
        public ?int $balanceAfter,
        public ?string $reference,
        public string $typeLabel,
        public ?string $counterpartyName,
        public ?string $counterpartyAccount,
        public ?string $variableSymbol,
        public ?string $message,
    ) {}
}
