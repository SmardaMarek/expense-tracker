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

    public const TRANSFERS = [
        'Contribution to the shared account',
        'To savings',
        'From savings',
        'Other transfers',
    ];

    /**
     * @return list<string>
     */
    public static function namesFor(CategoryType $type): array
    {
        return match ($type) {
            CategoryType::Expense => self::EXPENSES,
            CategoryType::Income => self::INCOME,
            CategoryType::Transfer => self::TRANSFERS,
        };
    }

    public function createForEmptyGroups(): int
    {
        return DB::transaction(fn (): int => array_sum(array_map(
            fn (CategoryType $type): int => $this->createIfGroupEmpty($type),
            CategoryType::cases(),
        )));
    }

    public function createIfGroupEmpty(CategoryType $type): int
    {
        if (Category::query()->ofType($type)->exists()) {
            return 0;
        }

        $names = self::namesFor($type);

        foreach ($names as $name) {
            Category::query()->create(['type' => $type, 'name' => __($name)]);
        }

        return count($names);
    }
}
