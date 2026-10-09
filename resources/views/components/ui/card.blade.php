@props(['padded' => true])

<section {{ $attributes->class(['rounded-lg border border-slate-200 bg-white shadow-sm', 'p-6' => $padded, 'overflow-hidden' => ! $padded]) }}>
    {{ $slot }}
</section>
