<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentFrequency;
use App\Enums\TransactionKind;
use App\Recurring\DueSchedule;
use Database\Factories\RecurringPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'kind',
    'amount',
    'frequency',
    'start_month',
    'end_month',
    'due_day',
    'bank_account_id',
    'category_id',
    'counterparty_account',
    'match_text',
    'archived_at',
])]
class RecurringPayment extends Model
{
    /** @use HasFactory<RecurringPaymentFactory> */
    use HasFactory;

    public const KINDS = [TransactionKind::Expense, TransactionKind::TransferOut];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<RecurringPayment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<RecurringPayment>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function schedule(): DueSchedule
    {
        return new DueSchedule($this->start_month->toImmutable(), $this->end_month?->toImmutable(), $this->frequency, $this->due_day);
    }

    public function monthlyEquivalent(): int
    {
        return (int) round($this->amount / $this->frequency->months());
    }

    protected function casts(): array
    {
        return [
            'kind' => TransactionKind::class,
            'frequency' => PaymentFrequency::class,
            'amount' => 'integer',
            'due_day' => 'integer',
            'start_month' => 'date',
            'end_month' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
