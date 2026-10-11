<?php

declare(strict_types=1);

namespace App\Statements;

final class StatementAmount
{
    public const PATTERN = '-?\d{1,3}(?:[ \x{00A0}]\d{3})*,\d{2}';

    public static function toHaler(string $amount): int
    {
        $compact = preg_replace('/[\s\x{00A0}]+/u', '', $amount) ?? '';
        $negative = str_starts_with($compact, '-');
        [$koruny, $haler] = explode(',', ltrim($compact, '-'));
        $value = (int) $koruny * 100 + (int) $haler;

        return $negative ? -$value : $value;
    }
}
