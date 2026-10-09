@props(['type' => 'submit', 'variant' => 'primary', 'size' => 'md', 'href' => null])

@php
    $variants = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-700 focus:ring-indigo-500',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-indigo-500',
        'danger' => 'border border-red-200 bg-white text-red-700 shadow-sm hover:bg-red-50 focus:ring-red-500',
    ];
    $sizes = [
        'md' => 'px-4 py-2 text-sm',
        'sm' => 'px-2.5 py-1 text-xs',
    ];
    $classes = ['inline-flex items-center justify-center rounded-md font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60', $variants[$variant], $sizes[$size]];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" wire:loading.attr="disabled" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
