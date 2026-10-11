<?php

declare(strict_types=1);

namespace App\Statements;

use App\Models\StatementImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ImportHistory
{
    public const SORTABLE = ['account', 'bank', 'period', 'imported', 'skipped', 'created'];

    public const DEFAULT_SORT = 'created';

    /**
     * @return Collection<int, StatementImport>
     */
    public function find(string $search, string $sort, bool $descending): Collection
    {
        $imports = StatementImport::query()->with('bankAccount')->withCount('transactions')->get();
        $needle = self::normalize($search);

        if ($needle !== '') {
            $imports = $imports->filter(fn (StatementImport $import): bool => str_contains($this->haystack($import), $needle));
        }

        $sorted = match ($sort) {
            'account' => $imports->sortByLocale('bankAccount.name'),
            'bank' => $imports->sortBy(fn (StatementImport $import): string => $import->bank->label()),
            'period' => $imports->sortBy(fn (StatementImport $import): string => $import->period_from->toDateString().'#'.$import->id),
            'imported' => $imports->sortBy(fn (StatementImport $import): string => sprintf('%010d#%010d', $import->imported_count, $import->id)),
            'skipped' => $imports->sortBy(fn (StatementImport $import): string => sprintf('%010d#%010d', $import->skipped_count, $import->id)),
            default => $imports->sortBy(fn (StatementImport $import): string => $import->created_at?->format('Y-m-d H:i:s').'#'.sprintf('%010d', $import->id)),
        };

        return ($descending ? $sorted->reverse() : $sorted)->values();
    }

    private function haystack(StatementImport $import): string
    {
        $locale = app()->getLocale();

        return self::normalize(implode(' ', [
            $import->bankAccount->name,
            $import->bankAccount->account_number,
            $import->bank->label(),
            $import->period_from->format('j. n. Y'),
            $import->period_to->format('j. n. Y'),
            $import->period_from->format('n/Y'),
            $import->period_from->locale($locale)->isoFormat('MMMM YYYY'),
            $import->period_to->locale($locale)->isoFormat('MMMM YYYY'),
        ]));
    }

    private static function normalize(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }
}
