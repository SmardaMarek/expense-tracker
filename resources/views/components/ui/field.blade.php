@props(['label', 'name', 'type' => 'text', 'autocomplete' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        wire:model="{{ $name }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        {{ $attributes->class([
            'mt-1 block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500',
            'border-slate-300' => ! $errors->has($name),
            'border-red-500' => $errors->has($name),
        ]) }}
    >
    @error($name)
        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
