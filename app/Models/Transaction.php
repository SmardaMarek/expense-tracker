<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Money\Amount;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_account_id',
    'booked_on',
    'amount',
    'type',
    'category_id',
    'recurring_payment_id',
    'counterparty_name',
    'counterparty_account',
    'variable_symbol',
    'message',
    'note',
    'source',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

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
     * @return BelongsTo<RecurringPayment, $this>
     */
    public function recurringPayment(): BelongsTo
    {
        return $this->belongsTo(RecurringPayment::class);
    }

    public function kind(): TransactionKind
    {
        return TransactionKind::of($this->type, $this->amount);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::get(fn (): string => Amount::format($this->amount));
    }

    protected function casts(): array
    {
        return [
            'booked_on' => 'date',
            'amount' => 'integer',
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
        ];
    }
}
