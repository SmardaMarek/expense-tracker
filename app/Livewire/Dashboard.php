<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\BankAccount;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard', [
            'hasAccounts' => BankAccount::query()->active()->exists(),
        ]);
    }
}
