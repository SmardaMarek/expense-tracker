<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\BankAccount;
use App\Models\Member;
use App\Transactions\TransactionFilter;
use Livewire\Attributes\Url;

trait FiltersByAccount
{
    #[Url(except: '')]
    public string $account = '';

    #[Url(except: '')]
    public string $owner = '';

    /**
     * @return array<int|string, string>
     */
    protected function accountFilterOptions(): array
    {
        return ['' => __('All accounts')] + BankAccount::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function ownerFilterOptions(): array
    {
        return ['' => __('Everyone')]
            + Member::query()->orderBy('position')->pluck('name', 'id')->all()
            + [TransactionFilter::SHARED => __('Shared')];
    }
}
