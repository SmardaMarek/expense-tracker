<?php

declare(strict_types=1);

namespace App\Statements;

interface StatementParser
{
    public function supports(string $text): bool;

    public function parse(string $text): ParsedStatement;
}
