<?php

declare(strict_types=1);

namespace App\Reports;

final readonly class TransferAmount
{
    public function __construct(
        public ?int $categoryId,
        public string $name,
        public int $outgoing,
        public int $incoming,
    ) {}
}
