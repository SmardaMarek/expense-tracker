<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Transactions\TransactionFilter;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

trait NavigatesMonths
{
    #[Url(except: '')]
    public string $month = '';

    public function previousMonth(): void
    {
        $this->month = $this->selectedMonth()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->selectedMonth()->addMonth()->format('Y-m');
    }

    protected function selectedMonth(): CarbonImmutable
    {
        return TransactionFilter::parseMonth($this->month);
    }

    protected function monthLabel(): string
    {
        return $this->selectedMonth()->locale(app()->getLocale())->isoFormat('MMMM YYYY');
    }
}
