@props(['icon', 'label', 'variant' => 'secondary'])

<button
    type="button"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    wire:loading.attr="disabled"
    {{ $attributes->class([
        'inline-flex h-8 w-8 items-center justify-center rounded-md border bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60',
        'border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-slate-900 focus:ring-indigo-500' => $variant === 'secondary',
        'border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 focus:ring-red-500' => $variant === 'danger',
    ]) }}
>
    <x-ui.icon :name="$icon" class="h-4 w-4" />
</button>
