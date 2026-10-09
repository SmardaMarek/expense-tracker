<?php

declare(strict_types=1);

namespace Tests\Unit\Banking;

use App\Banking\CzechAccountNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CzechAccountNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_accepts_and_normalizes_a_valid_number(string $input, string $expected): void
    {
        $accountNumber = CzechAccountNumber::parse($input);

        $this->assertNotNull($accountNumber);
        $this->assertSame($expected, $accountNumber->toString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validNumbers(): array
    {
        return [
            'without prefix' => ['1234567899/0300', '1234567899/0300'],
            'with prefix' => ['19-2000145399/0800', '19-2000145399/0800'],
            'prefix with leading zeros' => ['000019-2000145399/0800', '19-2000145399/0800'],
            'number with leading zeros' => ['0000000019/0100', '19/0100'],
            'short number' => ['19/0100', '19/0100'],
            'surrounding and inner spaces' => [' 19 - 2000145399 / 0800 ', '19-2000145399/0800'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_an_invalid_number(string $input): void
    {
        $this->assertNull(CzechAccountNumber::parse($input));
        $this->assertFalse(CzechAccountNumber::isValid($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'wrong number checksum' => ['1234567898/0300'],
            'wrong prefix checksum' => ['12-1234567899/0300'],
            'missing bank code' => ['1234567899'],
            'three digit bank code' => ['1234567899/300'],
            'number longer than ten digits' => ['12345678901/0300'],
            'prefix longer than six digits' => ['1234567-1234567899/0300'],
            'letters' => ['abc/0300'],
            'only zeros' => ['00/0100'],
            'empty' => [''],
        ];
    }
}
