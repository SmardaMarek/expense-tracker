<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatementBank;
use App\Models\BankAccount;
use App\Models\StatementImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementImport>
 */
class StatementImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'bank' => StatementBank::Csob,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'opening_balance' => 1_000_000,
            'closing_balance' => 900_000,
            'file_hash' => fake()->sha256(),
            'imported_count' => 10,
            'skipped_count' => 0,
        ];
    }

    public function forPeriod(string $month): static
    {
        return $this->state(fn (): array => [
            'period_from' => $month.'-01',
            'period_to' => date('Y-m-t', (int) strtotime($month.'-01')),
        ]);
    }
}
