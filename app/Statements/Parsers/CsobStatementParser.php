<?php

declare(strict_types=1);

namespace App\Statements\Parsers;

use App\Banking\CzechAccountNumber;
use App\Enums\StatementBank;
use App\Statements\ParsedStatement;
use App\Statements\ParsedTransaction;
use App\Statements\StatementAmount;
use App\Statements\StatementException;
use App\Statements\StatementParser;
use Carbon\CarbonImmutable;

final class CsobStatementParser implements StatementParser
{
    private const BANK_NAME = 'Československá obchodní banka';

    private const ACCOUNT = '(?:\d{1,6}-)?\d{2,10}\/\d{4}';

    private const DATE = '(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})';

    private const MAX_DETAIL_LINE_LENGTH = 90;

    private const PAGE_MARKERS = ['Strana:', 'Období:'];

    private const NUMBERED_TYPE = '/^(.*?\s\d+)\s+(\D.*)$/u';

    public function supports(string $text): bool
    {
        return str_contains($text, self::BANK_NAME) && str_contains($text, 'Přehled pohybů na účtu');
    }

    public function parse(string $text): ParsedStatement
    {
        $text = str_replace("\r", '', $text);

        if (preg_match('/Období:\s*'.self::DATE.'\s*-\s*'.self::DATE.'/u', $text, $period) !== 1) {
            throw StatementException::missing('the statement period');
        }

        $from = CarbonImmutable::create((int) $period[3], (int) $period[2], (int) $period[1]);
        $to = CarbonImmutable::create((int) $period[6], (int) $period[5], (int) $period[4]);

        if (preg_match('/Účet:\s*('.self::ACCOUNT.')/u', $text, $account) !== 1) {
            throw StatementException::missing('the account number');
        }

        return new ParsedStatement(
            bank: StatementBank::Csob,
            accountNumber: CzechAccountNumber::parse($account[1])?->toString() ?? $account[1],
            periodFrom: $from,
            periodTo: $to,
            openingBalance: $this->balance('Počáteční zůstatek', $text, 'the opening balance'),
            closingBalance: $this->balance('Konečný zůstatek', $text, 'the closing balance'),
            transactions: $this->transactions(explode("\n", $text), $from, $to),
        );
    }

    private function balance(string $label, string $text, string $description): int
    {
        if (preg_match('/'.$label.':\s*('.StatementAmount::PATTERN.')/u', $text, $match) !== 1) {
            throw StatementException::missing($description);
        }

        return StatementAmount::toHaler($match[1]);
    }

    /**
     * @param  list<string>  $lines
     * @return list<ParsedTransaction>
     */
    private function transactions(array $lines, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $startPattern = '/^(\d{2})\.(\d{2})\.(.*?)(?:\t|\s{2,})\s*(\d+)\s+('.StatementAmount::PATTERN.')\s+('.StatementAmount::PATTERN.')\s*$/u';
        $transactions = [];
        $draft = null;

        foreach ($lines as $line) {
            if (preg_match($startPattern, $line, $match) === 1) {
                if ($draft !== null) {
                    $transactions[] = $draft->build();
                }

                [$typeLabel, $counterpartyName] = $this->typeAndCounterparty($match[3]);
                $draft = new CsobTransactionDraft(
                    postedOn: $this->postedOn((int) $match[1], (int) $match[2], $from, $to),
                    typeLabel: $typeLabel,
                    counterpartyName: $counterpartyName,
                    reference: $match[4],
                    amount: StatementAmount::toHaler($match[5]),
                    balanceAfter: StatementAmount::toHaler($match[6]),
                );

                continue;
            }

            if ($draft === null) {
                continue;
            }

            if ($this->endsTransactions($line)) {
                $transactions[] = $draft->build();
                $draft = null;

                continue;
            }

            $draft->addDetail($line);
        }

        if ($draft !== null) {
            $transactions[] = $draft->build();
        }

        return $transactions;
    }

    /**
     * @return array{string, string|null}
     */
    private function typeAndCounterparty(string $columnsText): array
    {
        $columns = array_values(array_filter(
            array_map('trim', preg_split('/\t| {2,}/u', $columnsText) ?: []),
            fn (string $part): bool => $part !== '',
        ));
        $typeLabel = $columns[0] ?? '';
        $counterpartyName = $columns[1] ?? null;

        if ($counterpartyName === null && preg_match(self::NUMBERED_TYPE, $typeLabel, $match) === 1) {
            return [$match[1], $match[2]];
        }

        return [$typeLabel, $counterpartyName];
    }

    private function endsTransactions(string $line): bool
    {
        $trimmed = trim($line);

        foreach (self::PAGE_MARKERS as $marker) {
            if (str_starts_with($trimmed, $marker)) {
                return true;
            }
        }

        return mb_strlen($trimmed) > self::MAX_DETAIL_LINE_LENGTH;
    }

    private function postedOn(int $day, int $month, CarbonImmutable $from, CarbonImmutable $to): CarbonImmutable
    {
        $year = $from->year === $to->year || $month >= $from->month ? $from->year : $to->year;

        return CarbonImmutable::create($year, $month, $day);
    }
}
