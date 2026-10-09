<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['position', 'name'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    public const FIRST = 1;

    public const SECOND = 2;

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
