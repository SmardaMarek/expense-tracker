<?php

declare(strict_types=1);

namespace App\Statements;

use App\Enums\TransactionType;

final readonly class PreviewRow
{
    public function __construct(
        public ParsedTransaction $transaction,
        public TransactionType $type,
        public string $importKey,
        public bool $duplicate,
    ) {}
}
