<?php

declare(strict_types=1);

namespace App\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final class DefaultCategories
{
    public const EXPENSES = [
        'Housing',
        'Energy',
        'Groceries',
        'Restaurants and cafés',
        'Transport',
        'Car',
        'Health and pharmacy',
        'Clothing and shoes',
        'Household goods',
        'Phone and internet',
        'Subscriptions',
        'Leisure and entertainment',
        'Travel',
        'Education',
        'Gifts',
        'Insurance',
        'Bank fees',
        'Pets',
        'Other expenses',
    ];

    public const INCOME = [
        'Salary',
        'Refunds',
        'Interest',
        'Sales',
        'Gifts received',
        'Other income',
    ];

    public function createIfNone(): int
    {
        if (Category::query()->exists()) {
            return 0;
        }

        return DB::transaction(fn (): int => $this->createAll(CategoryType::Expense, self::EXPENSES)
            + $this->createAll(CategoryType::Income, self::INCOME));
    }

    /**
     * @param  list<string>  $names
     */
    private function createAll(CategoryType $type, array $names): int
    {
        foreach ($names as $name) {
            Category::query()->create(['type' => $type, 'name' => __($name)]);
        }

        return count($names);
    }
}
