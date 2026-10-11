<?php

declare(strict_types=1);

namespace App\Statements\Parsers;

use App\Statements\ParsedTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class CsobTransactionDraft
{
    private const ACCOUNT_LINE = '/^((?:\d{1,6}-)?\d{2,10}\/\d{4})(?:\t(.*))?$/u';

    private const CARD_NUMBERS_LINE = '/^\d+(?:\s+\d+)*$/u';

    private const PLACE_LINE = '/^Místo:\s*(.+)$/u';

    private const ORIGINAL_AMOUNT_LINE = '/^Částka:\s*([\d.,\s]+?)\s+([A-Z]{3})\s+(\d{2})\.(\d{2})\.(\d{4})$/u';

    private const HOME_CURRENCY = 'CZK';

    private const MAX_NAME_LENGTH = 150;

    private const MAX_MESSAGE_LENGTH = 255;

    private const MAX_VARIABLE_SYMBOL_LENGTH = 10;

    private ?CarbonImmutable $transactedOn = null;

    private ?string $counterpartyAccount = null;

    private ?string $variableSymbol = null;

    private ?string $place = null;

    private bool $hasDetails = false;

    /** @var list<string> */
    private array $messageParts = [];

    public function __construct(
        private readonly CarbonImmutable $postedOn,
        private readonly string $typeLabel,
        private readonly ?string $counterpartyName,
        private readonly string $reference,
        private readonly int $amount,
        private readonly int $balanceAfter,
    ) {}

    public function addDetail(string $line): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        $isFirstDetail = ! $this->hasDetails;
        $this->hasDetails = true;

        if ($isFirstDetail && preg_match(self::ACCOUNT_LINE, $line, $match) === 1) {
            $this->counterpartyAccount = $match[1];
            $symbols = array_values(array_filter(array_map('trim', explode("\t", $match[2] ?? '')), fn (string $part): bool => $part !== ''));
            $variableSymbol = $symbols[0] ?? null;
            $this->variableSymbol = $variableSymbol !== null && ctype_digit($variableSymbol) && strlen($variableSymbol) <= self::MAX_VARIABLE_SYMBOL_LENGTH
                ? $variableSymbol
                : null;

            return;
        }

        if ($isFirstDetail && preg_match(self::CARD_NUMBERS_LINE, $line) === 1) {
            return;
        }

        if (preg_match(self::PLACE_LINE, $line, $match) === 1) {
            $this->place = trim($match[1]);

            return;
        }

        if (preg_match(self::ORIGINAL_AMOUNT_LINE, $line, $match) === 1) {
            $this->transactedOn = CarbonImmutable::create((int) $match[5], (int) $match[4], (int) $match[3]);

            if ($match[2] !== self::HOME_CURRENCY) {
                $this->messageParts[] = trim($match[1]).' '.$match[2];
            }

            return;
        }

        $this->messageParts[] = preg_replace('/\s+/u', ' ', $line) ?? $line;
    }

    public function build(): ParsedTransaction
    {
        $name = $this->place ?? $this->counterpartyName;
        $message = implode(' · ', $this->messageParts);

        return new ParsedTransaction(
            postedOn: $this->postedOn,
            transactedOn: $this->transactedOn ?? $this->postedOn,
            amount: $this->amount,
            balanceAfter: $this->balanceAfter,
            reference: $this->reference,
            typeLabel: $this->typeLabel,
            counterpartyName: $name === null || $name === '' ? null : Str::limit($name, self::MAX_NAME_LENGTH, ''),
            counterpartyAccount: $this->counterpartyAccount,
            variableSymbol: $this->variableSymbol,
            message: $message === '' ? null : Str::limit($message, self::MAX_MESSAGE_LENGTH, ''),
        );
    }
}
