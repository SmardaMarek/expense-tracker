<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatementBank;
use Database\Factories\StatementImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bank_account_id',
    'bank',
    'period_from',
    'period_to',
    'opening_balance',
    'closing_balance',
    'file_hash',
    'imported_count',
    'skipped_count',
])]
class StatementImport extends Model
{
    /** @use HasFactory<StatementImportFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'bank' => StatementBank::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'opening_balance' => 'integer',
            'closing_balance' => 'integer',
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }
}
