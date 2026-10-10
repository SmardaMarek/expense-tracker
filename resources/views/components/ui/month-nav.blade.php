@props(['label'])

<div {{ $attributes->class('flex items-center gap-3') }}>
    <x-ui.button type="button" size="sm" variant="secondary" wire:click="previousMonth" aria-label="{{ __('Previous month') }}">&larr;</x-ui.button>
    <h2 class="min-w-40 text-center text-lg font-semibold capitalize">{{ $label }}</h2>
    <x-ui.button type="button" size="sm" variant="secondary" wire:click="nextMonth" aria-label="{{ __('Next month') }}">&rarr;</x-ui.button>
</div>
