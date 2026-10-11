<?php

declare(strict_types=1);

namespace App\Statements;

use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Throwable;

final class SmalotPdfTextExtractor implements PdfTextExtractor
{
    public function extract(string $path): string
    {
        try {
            $pages = (new Parser)->parseFile($path)->getPages();
        } catch (Throwable) {
            throw StatementException::unreadable();
        }

        $text = implode("\n", array_map(fn (Page $page): string => $page->getText(), $pages));

        if (trim($text) === '') {
            throw StatementException::unreadable();
        }

        return $text;
    }
}
