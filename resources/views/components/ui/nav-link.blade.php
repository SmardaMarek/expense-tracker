@props(['route'])

<a
    href="{{ route($route) }}"
    @if (request()->routeIs($route)) aria-current="page" @endif
    {{ $attributes->class([
        'font-medium',
        'text-indigo-700' => request()->routeIs($route),
        'text-slate-600 hover:text-slate-900' => ! request()->routeIs($route),
    ]) }}
>{{ $slot }}</a>
