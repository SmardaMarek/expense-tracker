@props(['column', 'label', 'sort', 'direction', 'align' => 'left'])

@php($active = $sort === $column)

<th
    scope="col"
    @if ($active) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
    {{ $attributes->class(['px-4 py-3 font-medium', 'text-right' => $align === 'right']) }}
>
    <button
        type="button"
        wire:click="sortBy('{{ $column }}')"
        @class([
            'inline-flex items-center gap-1 rounded hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500',
            'text-slate-900' => $active,
        ])
    >
        {{ $label }}
        <span aria-hidden="true" @class(['text-xs', 'text-indigo-600' => $active, 'text-slate-300' => ! $active])>
            {{ $active && $direction === 'desc' ? '▼' : '▲' }}
        </span>
    </button>
</th>
