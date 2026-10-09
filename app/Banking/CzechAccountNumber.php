<?php

declare(strict_types=1);

namespace App\Banking;

final readonly class CzechAccountNumber
{
    private const PATTERN = '/^(?:(\d{1,6})-)?(\d{2,10})\/(\d{4})$/';

    private const PREFIX_WEIGHTS = [10, 5, 8, 4, 2, 1];

    private const NUMBER_WEIGHTS = [6, 3, 7, 9, 10, 5, 8, 4, 2, 1];

    private const CHECKSUM_MODULUS = 11;

    private const MIN_NON_ZERO_DIGITS = 2;

    private function __construct(
        public string $prefix,
        public string $number,
        public string $bankCode,
    ) {}

    public static function parse(string $input): ?self
    {
        $compact = preg_replace('/\s+/', '', $input) ?? '';

        if (preg_match(self::PATTERN, $compact, $parts) !== 1) {
            return null;
        }

        $prefix = ltrim($parts[1], '0');
        $number = ltrim($parts[2], '0');

        if (! self::hasValidChecksum($parts[1], self::PREFIX_WEIGHTS)
            || ! self::hasValidChecksum($parts[2], self::NUMBER_WEIGHTS)
            || self::countNonZeroDigits($number) < self::MIN_NON_ZERO_DIGITS) {
            return null;
        }

        return new self($prefix, $number, $parts[3]);
    }

    public static function isValid(string $input): bool
    {
        return self::parse($input) !== null;
    }

    public function toString(): string
    {
        $base = "{$this->number}/{$this->bankCode}";

        return $this->prefix === '' ? $base : "{$this->prefix}-{$base}";
    }

    /**
     * @param  list<int>  $weights
     */
    private static function hasValidChecksum(string $digits, array $weights): bool
    {
        $padded = str_pad($digits, count($weights), '0', STR_PAD_LEFT);
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $padded[$index] * $weight;
        }

        return $sum % self::CHECKSUM_MODULUS === 0;
    }

    private static function countNonZeroDigits(string $digits): int
    {
        return strlen(str_replace('0', '', $digits));
    }
}
