<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? __($title).' – ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="font-semibold text-slate-800">{{ config('app.name') }}</a>
            <nav class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" aria-label="{{ __('Main navigation') }}">
                <x-ui.nav-link route="dashboard">{{ __('Dashboard') }}</x-ui.nav-link>
                <x-ui.nav-link route="transactions">{{ __('Transactions') }}</x-ui.nav-link>
                <x-ui.nav-link route="recurring">{{ __('Recurring payments') }}</x-ui.nav-link>
                <x-ui.nav-link route="accounts">{{ __('Bank accounts') }}</x-ui.nav-link>
                <x-ui.nav-link route="categories">{{ __('Categories') }}</x-ui.nav-link>
                <x-ui.nav-link route="settings">{{ __('Settings') }}</x-ui.nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-600 hover:text-slate-900">{{ __('Sign out') }}</button>
                </form>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</body>
</html>
