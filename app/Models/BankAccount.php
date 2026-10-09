<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'member_id', 'account_number', 'archived_at'])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @param  Builder<BankAccount>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<BankAccount>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isShared(): bool
    {
        return $this->member_id === null;
    }

    /**
     * @return Attribute<string, never>
     */
    protected function ownerName(): Attribute
    {
        return Attribute::get(fn (): string => $this->member->name ?? __('Shared'));
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
