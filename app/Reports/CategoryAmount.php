<?php

declare(strict_types=1);

namespace App\Reports;

final readonly class CategoryAmount
{
    public function __construct(
        public ?int $categoryId,
        public string $name,
        public int $amount,
        public float $share,
    ) {}
}
