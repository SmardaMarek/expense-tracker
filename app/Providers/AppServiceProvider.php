<?php

declare(strict_types=1);

namespace App\Providers;

use App\Statements\Parsers\CsobStatementParser;
use App\Statements\PdfTextExtractor;
use App\Statements\SmalotPdfTextExtractor;
use App\Statements\StatementReader;
use Collator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfTextExtractor::class, SmalotPdfTextExtractor::class);
        $this->app->bind(StatementReader::class, fn ($app): StatementReader => new StatementReader(
            $app->make(PdfTextExtractor::class),
            [new CsobStatementParser],
        ));
    }

    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(10));

        Collection::macro('sortByLocale', function (string $key): Collection {
            /** @var Collection<array-key, mixed> $this */
            if (! class_exists(Collator::class)) {
                return $this->sortBy($key, SORT_NATURAL | SORT_FLAG_CASE)->values();
            }

            $collator = new Collator(app()->getLocale());

            return $this->sort(fn (mixed $a, mixed $b): int => (int) $collator->compare(
                (string) data_get($a, $key),
                (string) data_get($b, $key),
            ))->values();
        });
    }
}
