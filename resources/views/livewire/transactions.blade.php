<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Transactions') }}</h1>
        @if ($hasAccounts && ! $form_open)
            <x-ui.button type="button" wire:click="create">{{ __('Add transaction') }}</x-ui.button>
        @endif
    </div>

    @if (session('status'))
        <x-ui.alert>{{ session('status') }}</x-ui.alert>
    @endif

    @unless ($hasAccounts)
        <x-ui.alert variant="warning">
            {{ __('Add a bank account before entering transactions:') }}
            <a href="{{ route('accounts') }}" class="font-semibold underline">{{ __('Bank accounts') }}</a>
        </x-ui.alert>
    @endunless

    @if ($form_open)
        <x-ui.card class="max-w-3xl">
            <h2 class="text-lg font-semibold">{{ $form->transaction_id ? __('Edit transaction') : __('New transaction') }}</h2>

            <form wire:submit="save" class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <x-ui.select name="form.kind" :label="__('Kind')" :options="$kindOptions" live />
                    @if ($isTransfer)
                        <p class="mt-1 text-xs text-slate-500">{{ __('transfers.explanation') }}</p>
                    @endif
                </div>
                <x-ui.field name="form.amount" :label="__('Amount (CZK)')" autocomplete="off" inputmode="decimal" placeholder="1 234,50" />
                <x-ui.field name="form.booked_on" type="date" :label="__('Date')" />
                <x-ui.select name="form.bank_account_id" :label="__('Bank account')" :options="$formAccountOptions" />
                <x-ui.select name="form.category_id" :label="__('Category')" :options="$formCategoryOptions" />
                <x-ui.field name="form.counterparty_name" :label="__('Counterparty')" autocomplete="off" />
                <x-ui.field name="form.counterparty_account" :label="__('Counterparty account')" autocomplete="off" />
                <x-ui.field name="form.variable_symbol" :label="__('Variable symbol')" autocomplete="off" inputmode="numeric" />
                <div class="sm:col-span-2">
                    <x-ui.field name="form.message" :label="__('Message')" autocomplete="off" />
                </div>
                <x-ui.field name="form.note" :label="__('Note')" autocomplete="off" />

                <div class="flex gap-3 sm:col-span-2">
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button type="button" size="sm" variant="secondary" wire:click="previousMonth" aria-label="{{ __('Previous month') }}">&larr;</x-ui.button>
            <h2 class="min-w-40 text-center text-lg font-semibold capitalize">{{ $monthLabel }}</h2>
            <x-ui.button type="button" size="sm" variant="secondary" wire:click="nextMonth" aria-label="{{ __('Next month') }}">&rarr;</x-ui.button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select name="account" :label="__('Bank account')" :options="$accountFilterOptions" live />
            <x-ui.select name="owner" :label="__('Owner')" :options="$ownerFilterOptions" live />
            <x-ui.select name="type" :label="__('Kind')" :options="$typeFilterOptions" live />
            <x-ui.select name="category" :label="__('Category')" :options="$categoryFilterOptions" live />
        </div>

        @if ($filtersActive)
            <x-ui.button type="button" size="sm" variant="secondary" wire:click="resetFilters">{{ __('Clear filters') }}</x-ui.button>
        @endif
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($transactions->isEmpty())
            <p class="p-6 text-slate-600">{{ __('No transactions in this month.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Bank account') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($transactions as $transaction)
                            <tr wire:key="transaction-{{ $transaction->id }}">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $transaction->booked_on->format('j. n. Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $transaction->counterparty_name ?? $transaction->message ?? '—' }}</div>
                                    @if ($transaction->counterparty_name && $transaction->message)
                                        <div class="text-xs text-slate-500">{{ $transaction->message }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if ($transaction->type === \App\Enums\TransactionType::Transfer)
                                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Transfer') }}</span>
                                        @if ($transaction->category)
                                            <span class="block">{{ $transaction->category->name }}</span>
                                        @endif
                                    @else
                                        {{ $transaction->category?->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $transaction->bankAccount->name }}</td>
                                <td @class([
                                    'whitespace-nowrap px-4 py-3 text-right font-mono font-medium',
                                    'text-slate-500' => $transaction->type === \App\Enums\TransactionType::Transfer,
                                    'text-red-700' => $transaction->type === \App\Enums\TransactionType::Expense,
                                    'text-emerald-700' => $transaction->type === \App\Enums\TransactionType::Income,
                                ])>{{ $transaction->formatted_amount }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <x-ui.icon-button icon="edit" :label="__('Edit')" wire:click="edit({{ $transaction->id }})" />
                                        <x-ui.icon-button icon="delete" :label="__('Delete')" variant="danger" wire:click="delete({{ $transaction->id }})" wire:confirm="{{ __('Delete this transaction?') }}" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
