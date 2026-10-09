<x-ui.card>
    <h2 class="text-lg font-semibold">{{ __('Change password') }}</h2>
    <p class="mt-1 text-sm text-slate-600">{{ __('Signed in as :email', ['email' => auth()->user()->email]) }}</p>

    @if (session('password_status'))
        <x-ui.alert class="mt-4">{{ session('password_status') }}</x-ui.alert>
    @endif

    <form wire:submit="updatePassword" class="mt-6 space-y-4">
        <x-ui.field name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" />
        <x-ui.field name="password" type="password" :label="__('New password')" autocomplete="new-password" />
        <p class="text-xs text-slate-500">{{ __('Use at least :count characters.', ['count' => 10]) }}</p>
        <x-ui.field name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" />

        <x-ui.button class="w-full">{{ __('Save password') }}</x-ui.button>
    </form>
</x-ui.card>
