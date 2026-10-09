@props(['variant' => 'success'])

<p
    role="{{ $variant === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->class([
        'rounded-md px-3 py-2 text-sm',
        'bg-emerald-50 text-emerald-800' => $variant === 'success',
        'bg-red-50 text-red-800' => $variant === 'error',
        'bg-amber-50 text-amber-900' => $variant === 'warning',
    ]) }}
>{{ $slot }}</p>
