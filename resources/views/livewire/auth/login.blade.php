<x-ui.card>
    <h1 class="text-xl font-semibold">{{ __('Sign in') }}</h1>

    <form wire:submit="login" class="mt-6 space-y-4">
        <x-ui.field name="email" type="email" :label="__('Email')" autocomplete="username" />
        <x-ui.field name="password" type="password" :label="__('Password')" autocomplete="current-password" />

        <x-ui.checkbox name="remember" :label="__('Remember me')" />

        <x-ui.button class="w-full">{{ __('Sign in') }}</x-ui.button>
    </form>
</x-ui.card>
