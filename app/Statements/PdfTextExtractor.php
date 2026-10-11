<?php

declare(strict_types=1);

namespace App\Statements;

interface PdfTextExtractor
{
    public function extract(string $path): string;
}
