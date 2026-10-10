@props(['padded' => true])

<section {{ $attributes->class(['min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm', 'p-6' => $padded, 'overflow-hidden' => ! $padded]) }}>
    {{ $slot }}
</section>
