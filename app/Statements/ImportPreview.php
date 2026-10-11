<?php

declare(strict_types=1);

namespace App\Statements;

use App\Models\BankAccount;

final readonly class ImportPreview
{
    /**
     * @param  list<PreviewRow>  $rows
     */
    public function __construct(
        public ParsedStatement $statement,
        public BankAccount $account,
        public array $rows,
    ) {}

    public function newCount(): int
    {
        return count(array_filter($this->rows, fn (PreviewRow $row): bool => ! $row->duplicate));
    }

    public function duplicateCount(): int
    {
        return count($this->rows) - $this->newCount();
    }
}
