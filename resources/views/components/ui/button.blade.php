@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    wire:loading.attr="disabled"
    {{ $attributes->class('inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60') }}
>
    {{ $slot }}
</button>
