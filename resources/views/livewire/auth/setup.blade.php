<x-ui.card>
    <h1 class="text-xl font-semibold">{{ __('Create your account') }}</h1>
    <p class="mt-2 text-sm text-slate-600">
        {{ __('Welcome. Create the account that protects this app. You will use it to sign in next time.') }}
    </p>

    <form wire:submit="register" class="mt-6 space-y-4">
        <x-ui.field name="name" :label="__('Name')" autocomplete="name" />
        <x-ui.field name="email" type="email" :label="__('Email')" autocomplete="username" />
        <x-ui.field name="password" type="password" :label="__('Password')" autocomplete="new-password" />
        <p class="text-xs text-slate-500">{{ __('Use at least :count characters.', ['count' => 10]) }}</p>
        <x-ui.field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />

        <x-ui.button class="w-full">{{ __('Create account') }}</x-ui.button>
    </form>
</x-ui.card>
