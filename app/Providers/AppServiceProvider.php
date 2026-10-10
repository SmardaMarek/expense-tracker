<?php

declare(strict_types=1);

namespace App\Providers;

use Collator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

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
