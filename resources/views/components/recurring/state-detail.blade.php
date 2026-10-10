@props(['state'])

@if ($state->status->isSettled())
    {{ __('paid :date', ['date' => $state->paidOn?->format('j. n.')]) }} · {{ \App\Money\Amount::format($state->paidAmount) }}
@elseif ($state->dueDate)
    {{ __('due :date', ['date' => $state->dueDate->format('j. n.')]) }}
@elseif ($state->status !== \App\Enums\RecurringStatus::NotDue)
    {{ __('any time this month') }}
@endif
