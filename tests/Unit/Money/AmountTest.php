<?php

declare(strict_types=1);

namespace Tests\Unit\Money;

use App\Money\Amount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountTest extends TestCase
{
    #[DataProvider('validInputs')]
    public function test_it_parses_a_valid_amount_into_haler(string $input, int $expected): void
    {
        $this->assertSame($expected, Amount::parse($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validInputs(): array
    {
        return [
            'whole crowns' => ['250', 25_000],
            'decimal comma' => ['1234,50', 123_450],
            'decimal point' => ['1234.5', 123_450],
            'one decimal digit' => ['0,5', 50],
            'space thousands separator' => ['1 234,50', 123_450],
            'non-breaking space separator' => ["1\u{00A0}234,50", 123_450],
            'surrounding spaces' => ['  99  ', 9_900],
            'smallest amount' => ['0,01', 1],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_it_rejects_an_invalid_amount(string $input): void
    {
        $this->assertNull(Amount::parse($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'zero' => ['0'],
            'zero with decimals' => ['0,00'],
            'negative' => ['-50'],
            'three decimal digits' => ['1,234'],
            'letters' => ['abc'],
            'empty' => [''],
            'too large' => ['1234567890'],
            'two separators' => ['1,2,3'],
        ];
    }

    public function test_it_formats_amounts_in_czech_style(): void
    {
        $this->assertSame("1\u{00A0}234,50\u{00A0}Kč", Amount::format(123_450));
        $this->assertSame("\u{2212}1\u{00A0}234,50\u{00A0}Kč", Amount::format(-123_450));
        $this->assertSame("0,05\u{00A0}Kč", Amount::format(5));
        $this->assertSame("1\u{00A0}000\u{00A0}000,00\u{00A0}Kč", Amount::format(100_000_000));
    }

    public function test_it_formats_amounts_for_editing_without_sign_or_separators(): void
    {
        $this->assertSame('1234,50', Amount::toInput(-123_450));
        $this->assertSame('0,05', Amount::toInput(5));
    }

    public function test_formatted_input_parses_back_to_the_same_amount(): void
    {
        $this->assertSame(123_450, Amount::parse(Amount::toInput(123_450)));
    }
}
