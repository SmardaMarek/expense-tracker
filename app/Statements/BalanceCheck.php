<?php

declare(strict_types=1);

namespace App\Statements;

final class BalanceCheck
{
    public static function verify(ParsedStatement $statement): void
    {
        $balance = $statement->openingBalance;

        foreach ($statement->transactions as $index => $transaction) {
            $balance += $transaction->amount;

            if ($transaction->balanceAfter !== null && $transaction->balanceAfter !== $balance) {
                throw StatementException::balanceMismatch($index + 1, $transaction, $balance);
            }
        }

        if ($balance !== $statement->closingBalance) {
            throw StatementException::closingMismatch($balance, $statement->closingBalance);
        }
    }
}
