<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => Member::FIRST,
            'name' => fake()->unique()->firstName(),
        ];
    }

    public function second(): static
    {
        return $this->state(fn (): array => ['position' => Member::SECOND]);
    }
}
