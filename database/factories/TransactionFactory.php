<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'booked_on' => now()->toDateString(),
            'amount' => -fake()->numberBetween(100, 500_000),
            'type' => TransactionType::Expense,
            'category_id' => null,
            'counterparty_name' => fake()->company(),
            'source' => TransactionSource::Manual,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Income,
            'amount' => fake()->numberBetween(100, 5_000_000),
        ]);
    }

    public function transferOut(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Transfer,
            'amount' => -fake()->numberBetween(100, 5_000_000),
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['booked_on' => $date]);
    }

    public function forAccount(BankAccount $account): static
    {
        return $this->state(fn (): array => ['bank_account_id' => $account->id]);
    }

    public function forRecurring(RecurringPayment $payment): static
    {
        return $this->state(fn (): array => [
            'recurring_payment_id' => $payment->id,
            'bank_account_id' => $payment->bank_account_id,
            'type' => $payment->kind->type(),
            'amount' => -$payment->amount,
        ]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (): array => ['category_id' => $category->id]);
    }
}
