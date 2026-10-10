@props(['label', 'amount', 'previous', 'higherIsBetter' => true, 'hint' => null, 'signed' => false])

@php
    $delta = $amount - $previous;
    $improved = $higherIsBetter ? $delta > 0 : $delta < 0;
@endphp

<x-ui.card {{ $attributes->class('flex flex-col gap-1') }}>
    <p class="flex items-center gap-1 text-sm font-medium text-slate-600">
        {{ $label }}
        @if ($hint)
            <span title="{{ $hint }}" aria-label="{{ $hint }}" role="img" class="cursor-help text-slate-400 hover:text-slate-600">
                <x-ui.icon name="info" class="h-4 w-4" />
            </span>
        @endif
    </p>
    <p @class([
        'font-mono text-xl font-semibold tracking-tight',
        'text-slate-900' => ! $signed || $amount === 0,
        'text-emerald-700' => $signed && $amount > 0,
        'text-red-700' => $signed && $amount < 0,
    ])>{{ $signed ? \App\Money\Amount::formatSigned($amount) : \App\Money\Amount::format($amount) }}</p>
    <p @class([
        'text-xs',
        'text-slate-500' => $delta === 0,
        'text-emerald-700' => $delta !== 0 && $improved,
        'text-red-700' => $delta !== 0 && ! $improved,
    ])>
        @if ($delta === 0)
            {{ __('Same as last month') }}
        @else
            {{ $delta > 0 ? '▲' : '▼' }} {{ \App\Money\Amount::formatSigned($delta) }} {{ __('vs last month') }}
        @endif
    </p>
</x-ui.card>
