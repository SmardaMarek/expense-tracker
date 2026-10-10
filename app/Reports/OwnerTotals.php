<?php

declare(strict_types=1);

namespace App\Reports;

final readonly class OwnerTotals
{
    public function __construct(
        public string $owner,
        public string $name,
        public Totals $totals,
        public int $transfersOut,
        public int $transfersIn,
    ) {}
}
