@props(['label', 'name', 'live' => false])

<label for="{{ $name }}" {{ $attributes->class('flex items-center gap-2 text-sm text-slate-700') }}>
    <input
        id="{{ $name }}"
        type="checkbox"
        @if ($live) wire:model.live="{{ $name }}" @else wire:model="{{ $name }}" @endif
        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500"
    >
    {{ $label }}
</label>
