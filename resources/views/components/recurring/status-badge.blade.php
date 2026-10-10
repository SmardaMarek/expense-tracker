@props(['status'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-emerald-50 text-emerald-700' => $status === \App\Enums\RecurringStatus::Paid,
    'bg-sky-50 text-sky-700' => $status === \App\Enums\RecurringStatus::Waiting,
    'bg-red-50 text-red-700' => $status === \App\Enums\RecurringStatus::Missing,
    'bg-slate-100 text-slate-500' => $status === \App\Enums\RecurringStatus::NotDue,
]) }}>{{ $slot->isEmpty() ? $status->label() : $slot }}</span>
