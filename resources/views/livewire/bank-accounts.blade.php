<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Bank accounts') }}</h1>
        @unless ($form_open)
            <x-ui.button type="button" wire:click="create">{{ __('Add account') }}</x-ui.button>
        @endunless
    </div>

    @if (session('status'))
        <x-ui.alert>{{ session('status') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="error">{{ session('error') }}</x-ui.alert>
    @endif

    @unless ($hasMembers)
        <x-ui.alert variant="warning">
            {{ __('To assign accounts to people, first enter their names in') }}
            <a href="{{ route('settings') }}" class="font-semibold underline">{{ __('Settings') }}</a>.
        </x-ui.alert>
    @endunless

    @if ($form_open)
        <x-ui.card class="max-w-xl">
            <h2 class="text-lg font-semibold">{{ $editing_id ? __('Edit account') : __('New account') }}</h2>

            <form wire:submit="save" class="mt-6 space-y-4">
                <x-ui.field name="account_name" :label="__('Account name')" autocomplete="off" />
                <x-ui.select name="owner" :label="__('Owner')" :options="$ownerOptions" />
                <div>
                    <x-ui.field name="account_number" :label="__('Account number')" autocomplete="off" inputmode="numeric" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Format: prefix-number/bank code, e.g. 19-1234567899/0300 or 1234567899/0300.') }}</p>
                </div>

                <div class="flex gap-3">
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card :padded="false">
        @if ($activeAccounts->isEmpty())
            <p class="p-6 text-slate-600">{{ __('No bank accounts yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Account name') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Owner') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Account number') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($activeAccounts as $account)
                            <tr wire:key="account-{{ $account->id }}">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $account->name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $account->owner_name }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">{{ $account->account_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <x-ui.icon-button icon="edit" :label="__('Edit')" wire:click="edit({{ $account->id }})" />
                                        <x-ui.icon-button icon="archive" :label="__('Archive')" wire:click="archive({{ $account->id }})" />
                                        <x-ui.icon-button icon="delete" :label="__('Delete')" variant="danger" wire:click="delete({{ $account->id }})" wire:confirm="{{ __('Delete the account :name?', ['name' => $account->name]) }}" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($archivedAccounts->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-slate-700">{{ __('Archived accounts') }}</h2>
            <x-ui.card :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($archivedAccounts as $account)
                        <li wire:key="archived-{{ $account->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <span class="text-slate-600">
                                {{ $account->name }} · {{ $account->owner_name }} · <span class="font-mono">{{ $account->account_number }}</span>
                            </span>
                            <div class="flex gap-1.5">
                                <x-ui.icon-button icon="restore" :label="__('Restore')" wire:click="restore({{ $account->id }})" />
                                <x-ui.icon-button icon="delete" :label="__('Delete')" variant="danger" wire:click="delete({{ $account->id }})" wire:confirm="{{ __('Delete the account :name?', ['name' => $account->name]) }}" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </section>
    @endif
</div>
