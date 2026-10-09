<x-ui.card>
    <h2 class="text-lg font-semibold">{{ __('Household') }}</h2>
    <p class="mt-1 text-sm text-slate-600">{{ __('The people who own the bank accounts. Accounts can also be shared by both.') }}</p>

    @if (session('members_status'))
        <p class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('members_status') }}</p>
    @endif

    <form wire:submit="save" class="mt-6 space-y-4">
        <x-ui.field name="first_name" :label="__('First person')" autocomplete="off" />
        <x-ui.field name="second_name" :label="__('Second person (optional)')" autocomplete="off" />

        <x-ui.button class="w-full">{{ __('Save household') }}</x-ui.button>
    </form>
</x-ui.card>
