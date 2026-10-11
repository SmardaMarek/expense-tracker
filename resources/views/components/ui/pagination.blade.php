@props(['paginator'])

@if ($paginator->lastPage() > 1)
    <nav aria-label="{{ __('Pages') }}" {{ $attributes->class('flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-sm') }}>
        <span class="text-slate-600">
            {{ __('Page :current of :last (:total in total)', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage(), 'total' => $paginator->total()]) }}
        </span>
        <div class="flex gap-2">
            <x-ui.button type="button" size="sm" variant="secondary" wire:click="previousPage" :disabled="$paginator->onFirstPage()">{{ __('Previous') }}</x-ui.button>
            <x-ui.button type="button" size="sm" variant="secondary" wire:click="nextPage" :disabled="! $paginator->hasMorePages()">{{ __('Next') }}</x-ui.button>
        </div>
    </nav>
@endif
