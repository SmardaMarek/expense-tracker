<?php

declare(strict_types=1);

namespace App\Statements;

use App\Money\Amount;
use RuntimeException;

final class StatementException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self(__('The file could not be read as a PDF statement. Make sure it is the original PDF downloaded from the bank, not a scan.'));
    }

    public static function unsupported(): self
    {
        return new self(__('This statement format is not supported yet. Supported banks: ČSOB.'));
    }

    public static function missing(string $what): self
    {
        return new self(__('The statement is missing :what, so it cannot be imported.', ['what' => __($what)]));
    }

    public static function unknownAccount(string $accountNumber): self
    {
        return new self(__('The account :number from the statement is not among your bank accounts. Add it on the Bank accounts page first.', ['number' => $accountNumber]));
    }

    public static function archivedAccount(string $name): self
    {
        return new self(__('The account :name is archived. Restore it before importing its statements.', ['name' => $name]));
    }

    public static function balanceMismatch(int $row, ParsedTransaction $transaction, int $expected): self
    {
        return new self(__('The balance check failed on row :row (:date, :amount): expected balance :expected, statement says :actual. Nothing was imported.', [
            'row' => $row,
            'date' => $transaction->postedOn->format('j. n. Y'),
            'amount' => Amount::format($transaction->amount),
            'expected' => Amount::format($expected),
            'actual' => Amount::format($transaction->balanceAfter ?? 0),
        ]));
    }

    public static function closingMismatch(int $calculated, int $closing): self
    {
        return new self(__('The transactions add up to :calculated, but the statement closing balance is :closing. Nothing was imported.', [
            'calculated' => Amount::format($calculated),
            'closing' => Amount::format($closing),
        ]));
    }
}
