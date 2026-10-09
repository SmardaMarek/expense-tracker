<?php

declare(strict_types=1);

namespace App\Transactions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class TransactionFilter
{
    public const SHARED = 'shared';

    public const UNCATEGORIZED = 'none';

    private const MONTH_PATTERN = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public function __construct(
        public CarbonImmutable $month,
        public ?int $accountId = null,
        public ?string $owner = null,
        public ?TransactionType $type = null,
        public ?string $category = null,
    ) {}

    public static function fromInput(string $month, string $account, string $owner, string $type, string $category): self
    {
        return new self(
            self::parseMonth($month),
            ctype_digit($account) ? (int) $account : null,
            $owner === '' ? null : $owner,
            TransactionType::tryFrom($type),
            $category === '' ? null : $category,
        );
    }

    public static function parseMonth(string $month): CarbonImmutable
    {
        if (preg_match(self::MONTH_PATTERN, $month) === 1) {
            return CarbonImmutable::parse($month.'-01')->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->where('booked_on', '>=', $this->month->toDateString())
            ->where('booked_on', '<', $this->month->addMonth()->toDateString())
            ->when($this->accountId !== null, fn (Builder $q) => $q->where('bank_account_id', $this->accountId))
            ->when($this->owner === self::SHARED, fn (Builder $q) => $q->whereHas(
                'bankAccount',
                fn (Builder $account) => $account->whereNull('member_id'),
            ))
            ->when($this->owner !== null && ctype_digit($this->owner), fn (Builder $q) => $q->whereHas(
                'bankAccount',
                fn (Builder $account) => $account->where('member_id', (int) $this->owner),
            ))
            ->when($this->type !== null, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->category === self::UNCATEGORIZED, fn (Builder $q) => $q->whereNull('category_id'))
            ->when($this->category !== null && ctype_digit($this->category), fn (Builder $q) => $q
                ->where('category_id', (int) $this->category));
    }
}
