<?php

declare(strict_types=1);

namespace App\Reports;

final readonly class TrendPoint
{
    public function __construct(
        public string $month,
        public string $label,
        public Totals $totals,
    ) {}
}
