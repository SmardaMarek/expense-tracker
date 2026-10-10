<?php

declare(strict_types=1);

namespace App\Money;

final class Amount
{
    private const INPUT_PATTERN = '/^(\d{1,9})(?:[.,](\d{1,2}))?$/';

    private const SEPARATORS_PATTERN = '/[\s\x{00A0}\x{202F}]+/u';

    private const HALERU_PER_KORUNA = 100;

    private const THOUSANDS_SEPARATOR = "\u{00A0}";

    private const MINUS_SIGN = "\u{2212}";

    private const CURRENCY = 'Kč';

    public static function parse(string $input): ?int
    {
        $compact = preg_replace(self::SEPARATORS_PATTERN, '', $input) ?? '';

        if (preg_match(self::INPUT_PATTERN, $compact, $parts) !== 1) {
            return null;
        }

        $fraction = str_pad($parts[2] ?? '', 2, '0');
        $haler = (int) $parts[1] * self::HALERU_PER_KORUNA + (int) $fraction;

        return $haler > 0 ? $haler : null;
    }

    public static function format(int $haler): string
    {
        $sign = $haler < 0 ? self::MINUS_SIGN : '';

        return $sign.self::digits(abs($haler), self::THOUSANDS_SEPARATOR).self::THOUSANDS_SEPARATOR.self::CURRENCY;
    }

    public static function formatSigned(int $haler): string
    {
        return ($haler > 0 ? '+' : '').self::format($haler);
    }

    public static function toInput(int $haler): string
    {
        return self::digits(abs($haler), '');
    }

    private static function digits(int $haler, string $thousandsSeparator): string
    {
        $koruny = number_format(intdiv($haler, self::HALERU_PER_KORUNA), 0, '', $thousandsSeparator);

        return $koruny.','.sprintf('%02d', $haler % self::HALERU_PER_KORUNA);
    }
}
