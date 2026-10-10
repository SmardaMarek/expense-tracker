<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryPurpose;
use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'purpose', 'archived_at'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeOfType(Builder $query, CategoryType $type): void
    {
        $query->where('type', $type);
    }

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'purpose' => CategoryPurpose::class,
            'archived_at' => 'datetime',
        ];
    }
}
