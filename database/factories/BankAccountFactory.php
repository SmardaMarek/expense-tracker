<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    private const NUMBER_WEIGHTS = [6, 3, 7, 9, 10, 5, 8, 4, 2, 1];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Účet '.fake()->unique()->word(),
            'member_id' => null,
            'account_number' => $this->validAccountNumber(),
        ];
    }

    public function ownedBy(Member $member): static
    {
        return $this->state(fn (): array => ['member_id' => $member->id]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }

    private function validAccountNumber(): string
    {
        do {
            $digits = array_map(intval(...), str_split((string) fake()->unique()->numberBetween(100000000, 999999999)));
            $partial = 0;
            foreach ($digits as $index => $digit) {
                $partial += $digit * self::NUMBER_WEIGHTS[$index];
            }
            $checkDigit = (11 - $partial % 11) % 11;
        } while ($checkDigit === 10);

        return implode('', $digits).$checkDigit.'/0300';
    }
}
