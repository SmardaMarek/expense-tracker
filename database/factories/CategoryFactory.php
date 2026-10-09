<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CategoryType::Expense,
            'name' => ucfirst(fake()->unique()->word()),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => ['type' => CategoryType::Income]);
    }

    public function transfer(): static
    {
        return $this->state(fn (): array => ['type' => CategoryType::Transfer]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
