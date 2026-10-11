<?php

declare(strict_types=1);

namespace App\Statements;

use App\Banking\CzechAccountNumber;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\StatementImport;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class StatementImporter
{
    public function preview(ParsedStatement $statement): ImportPreview
    {
        $account = BankAccount::query()->where('account_number', $statement->accountNumber)->first();

        if ($account === null) {
            throw StatementException::unknownAccount($statement->accountNumber);
        }

        if ($account->archived_at !== null) {
            throw StatementException::archivedAccount($account->name);
        }

        $ownAccounts = BankAccount::query()->pluck('account_number')->flip();
        $keys = array_map(fn (ParsedTransaction $transaction): string => $this->importKey($statement, $transaction), $statement->transactions);
        $existing = Transaction::query()->whereIn('import_key', $keys)->pluck('import_key')->flip();

        $rows = array_map(fn (ParsedTransaction $transaction, string $key): PreviewRow => new PreviewRow(
            $transaction,
            $this->typeOf($transaction, $ownAccounts->all()),
            $key,
            $existing->has($key),
        ), $statement->transactions, $keys);

        return new ImportPreview($statement, $account, $rows);
    }

    /**
     * @param  list<string>  $hiddenKeys
     */
    public function import(ParsedStatement $statement, string $fileHash, array $hiddenKeys = []): StatementImport
    {
        $preview = $this->preview($statement);
        $hidden = array_flip($hiddenKeys);

        return DB::transaction(function () use ($preview, $statement, $fileHash, $hidden): StatementImport {
            $import = StatementImport::query()->create([
                'bank_account_id' => $preview->account->id,
                'bank' => $statement->bank,
                'period_from' => $statement->periodFrom->toDateString(),
                'period_to' => $statement->periodTo->toDateString(),
                'opening_balance' => $statement->openingBalance,
                'closing_balance' => $statement->closingBalance,
                'file_hash' => $fileHash,
                'imported_count' => $preview->newCount(),
                'skipped_count' => $preview->duplicateCount(),
            ]);

            foreach ($preview->rows as $row) {
                if ($row->duplicate) {
                    continue;
                }

                $transaction = $row->transaction;

                Transaction::query()->create([
                    'bank_account_id' => $preview->account->id,
                    'booked_on' => $transaction->transactedOn->toDateString(),
                    'posted_on' => $transaction->postedOn->toDateString(),
                    'amount' => $transaction->amount,
                    'type' => $row->type,
                    'counterparty_name' => $transaction->counterpartyName,
                    'counterparty_account' => $this->normalizeAccount($transaction->counterpartyAccount),
                    'variable_symbol' => $transaction->variableSymbol,
                    'message' => $transaction->message,
                    'is_hidden' => isset($hidden[$row->importKey]),
                    'bank_reference' => $transaction->reference,
                    'import_key' => $row->importKey,
                    'statement_import_id' => $import->id,
                    'source' => TransactionSource::Import,
                ]);
            }

            return $import;
        });
    }

    public function revert(StatementImport $import): int
    {
        return DB::transaction(function () use ($import): int {
            $deleted = $import->transactions()->delete();
            $import->delete();

            return $deleted;
        });
    }

    /**
     * @param  array<string, int>  $ownAccounts
     */
    private function typeOf(ParsedTransaction $transaction, array $ownAccounts): TransactionType
    {
        $counterparty = $this->normalizeAccount($transaction->counterpartyAccount);

        if ($counterparty !== null && isset($ownAccounts[$counterparty])) {
            return TransactionType::Transfer;
        }

        return $transaction->amount > 0 ? TransactionType::Income : TransactionType::Expense;
    }

    private function importKey(ParsedStatement $statement, ParsedTransaction $transaction): string
    {
        return hash('sha256', implode('|', [
            $statement->bank->value,
            $statement->accountNumber,
            $transaction->postedOn->toDateString(),
            $transaction->reference,
            $transaction->amount,
            $transaction->balanceAfter,
        ]));
    }

    private function normalizeAccount(?string $account): ?string
    {
        return $account === null ? null : (CzechAccountNumber::parse($account)?->toString() ?? $account);
    }
}
