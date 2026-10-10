<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\FiltersByAccount;
use App\Livewire\Concerns\NavigatesMonths;
use App\Models\BankAccount;
use App\Reports\DashboardCharts;
use App\Reports\MonthlySummary;
use App\Transactions\TransactionFilter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use FiltersByAccount;
    use NavigatesMonths;

    public function resetFilters(): void
    {
        $this->reset('account', 'owner');
    }

    public function render(MonthlySummary $monthlySummary): View
    {
        $filter = TransactionFilter::fromInput($this->month, $this->account, $this->owner, '', '');
        $summary = $monthlySummary->build($filter);
        $charts = new DashboardCharts($filter);

        return view('livewire.dashboard', [
            'hasAccounts' => BankAccount::query()->active()->exists(),
            'monthLabel' => $this->monthLabel(),
            'summary' => $summary,
            'charts' => $charts,
            'donut' => $charts->expenseDonut($summary),
            'trend' => $charts->trend($summary),
            'flow' => $charts->flow($summary),
            'accountFilterOptions' => $this->accountFilterOptions(),
            'ownerFilterOptions' => $this->ownerFilterOptions(),
            'filtersActive' => $this->account !== '' || $this->owner !== '',
        ]);
    }
}
