<x-ui.card>
    <h1 class="text-xl font-semibold">{{ __('Sign in') }}</h1>

    <form wire:submit="login" class="mt-6 space-y-4">
        <x-ui.field name="email" type="email" :label="__('Email')" autocomplete="username" />
        <x-ui.field name="password" type="password" :label="__('Password')" autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            {{ __('Remember me') }}
        </label>

        <x-ui.button class="w-full">{{ __('Sign in') }}</x-ui.button>
    </form>
</x-ui.card>
