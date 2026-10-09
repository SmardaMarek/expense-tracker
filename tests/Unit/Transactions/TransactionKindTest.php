<?php

declare(strict_types=1);

namespace Tests\Unit\Transactions;

use App\Enums\CategoryType;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransactionKindTest extends TestCase
{
    #[DataProvider('kinds')]
    public function test_each_kind_maps_to_type_sign_and_category_type(
        TransactionKind $kind,
        TransactionType $type,
        int $sign,
        CategoryType $categoryType,
    ): void {
        $this->assertSame($type, $kind->type());
        $this->assertSame($sign, $kind->sign());
        $this->assertSame($categoryType, $kind->categoryType());
    }

    /**
     * @return array<string, array{TransactionKind, TransactionType, int, CategoryType}>
     */
    public static function kinds(): array
    {
        return [
            'expense' => [TransactionKind::Expense, TransactionType::Expense, -1, CategoryType::Expense],
            'income' => [TransactionKind::Income, TransactionType::Income, 1, CategoryType::Income],
            'transfer out' => [TransactionKind::TransferOut, TransactionType::Transfer, -1, CategoryType::Transfer],
            'transfer in' => [TransactionKind::TransferIn, TransactionType::Transfer, 1, CategoryType::Transfer],
        ];
    }

    #[DataProvider('storedTransactions')]
    public function test_the_kind_is_recovered_from_a_stored_transaction(TransactionType $type, int $amount, TransactionKind $expected): void
    {
        $this->assertSame($expected, TransactionKind::of($type, $amount));
    }

    /**
     * @return array<string, array{TransactionType, int, TransactionKind}>
     */
    public static function storedTransactions(): array
    {
        return [
            'expense' => [TransactionType::Expense, -500, TransactionKind::Expense],
            'income' => [TransactionType::Income, 500, TransactionKind::Income],
            'outgoing transfer' => [TransactionType::Transfer, -500, TransactionKind::TransferOut],
            'incoming transfer' => [TransactionType::Transfer, 500, TransactionKind::TransferIn],
        ];
    }
}
