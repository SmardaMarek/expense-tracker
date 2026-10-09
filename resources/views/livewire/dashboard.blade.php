<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Dashboard') }}</h1>

    @if (! $hasAccounts)
        <x-ui.card>
            <h2 class="text-lg font-semibold">{{ __('Start by adding your bank accounts') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every transaction belongs to an account, so add them first.') }}</p>
            <x-ui.button :href="route('accounts')" class="mt-4">{{ __('Add bank accounts') }}</x-ui.button>
        </x-ui.card>
    @else
        <x-ui.card>
            <p class="text-slate-600">{{ __('Nothing here yet. Statement import and the monthly dashboard are coming next.') }}</p>
        </x-ui.card>
    @endif
</div>
