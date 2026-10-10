<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentFrequency;
use App\Enums\TransactionKind;
use App\Models\BankAccount;
use App\Models\RecurringPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPayment>
 */
class RecurringPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'kind' => TransactionKind::Expense,
            'amount' => 1_500_000,
            'frequency' => PaymentFrequency::Monthly,
            'start_month' => now()->startOfYear()->toDateString(),
            'due_day' => 15,
            'bank_account_id' => BankAccount::factory(),
        ];
    }

    public function transfer(): static
    {
        return $this->state(fn (): array => ['kind' => TransactionKind::TransferOut, 'due_day' => null]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
