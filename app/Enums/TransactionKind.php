<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionKind: string
{
    case Expense = 'expense';
    case Income = 'income';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    public static function of(TransactionType $type, int $amount): self
    {
        return match ($type) {
            TransactionType::Expense => self::Expense,
            TransactionType::Income => self::Income,
            TransactionType::Transfer => $amount < 0 ? self::TransferOut : self::TransferIn,
        };
    }

    public function type(): TransactionType
    {
        return match ($this) {
            self::Expense => TransactionType::Expense,
            self::Income => TransactionType::Income,
            self::TransferOut, self::TransferIn => TransactionType::Transfer,
        };
    }

    public function sign(): int
    {
        return match ($this) {
            self::Expense, self::TransferOut => -1,
            self::Income, self::TransferIn => 1,
        };
    }

    public function categoryType(): CategoryType
    {
        return match ($this) {
            self::Expense => CategoryType::Expense,
            self::Income => CategoryType::Income,
            self::TransferOut, self::TransferIn => CategoryType::Transfer,
        };
    }

    public function isTransfer(): bool
    {
        return $this->type() === TransactionType::Transfer;
    }

    public function label(): string
    {
        return match ($this) {
            self::Expense => __('Expense'),
            self::Income => __('Income'),
            self::TransferOut => __('Transfer – outgoing'),
            self::TransferIn => __('Transfer – incoming'),
        };
    }
}
