<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Recurring payments') }}</h1>
        @if ($hasAccounts && ! $form_open)
            <x-ui.button type="button" wire:click="create">{{ __('Add recurring payment') }}</x-ui.button>
        @endif
    </div>

    @if (session('status'))
        <x-ui.alert>{{ session('status') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="error">{{ session('error') }}</x-ui.alert>
    @endif

    @unless ($hasAccounts)
        <x-ui.alert variant="warning">
            {{ __('Add a bank account before entering transactions:') }}
            <a href="{{ route('accounts') }}" class="font-semibold underline">{{ __('Bank accounts') }}</a>
        </x-ui.alert>
    @endunless

    @if ($form_open)
        <x-ui.card class="max-w-3xl">
            <h2 class="text-lg font-semibold">{{ $form->payment_id ? __('Edit recurring payment') : __('New recurring payment') }}</h2>

            <form wire:submit="save" class="mt-6 grid gap-4 sm:grid-cols-2">
                <x-ui.field name="form.name" :label="__('Name')" autocomplete="off" placeholder="{{ __('e.g. Rent') }}" />
                <x-ui.select name="form.kind" :label="__('Kind')" :options="$kindOptions" live />
                <x-ui.field name="form.amount" :label="__('Amount (CZK)')" autocomplete="off" inputmode="decimal" placeholder="16 500" />
                <x-ui.select name="form.frequency" :label="__('Frequency')" :options="$frequencyOptions" />
                <div>
                    <x-ui.field name="form.start_month" type="month" :label="__('From month')" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('For quarterly and yearly payments, the first month it is paid.') }}</p>
                </div>
                <x-ui.field name="form.end_month" type="month" :label="__('Until month (optional)')" />
                <div>
                    <x-ui.field name="form.due_day" type="number" min="1" max="31" :label="__('Due day (optional)')" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Shown as missing 3 days after this day. Leave empty if it can be paid any time during the month.') }}</p>
                </div>
                <x-ui.select name="form.bank_account_id" :label="__('Paid from account')" :options="$accountOptions" />
                <x-ui.select name="form.category_id" :label="__('Category')" :options="$categoryOptions" />
                <x-ui.field name="form.counterparty_account" :label="__('Recipient account (optional)')" autocomplete="off" placeholder="19-2000145399/0800" />
                <div class="sm:col-span-2">
                    <x-ui.field name="form.match_text" :label="__('Text to recognize it (optional)')" autocomplete="off" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Payee name, variable symbol or message. Used by the statement import later.') }}</p>
                </div>

                <div class="flex gap-3 sm:col-span-2">
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.month-nav :label="$monthLabel" />
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
            <span>{{ __('Fixed expenses per month') }}: <b class="font-mono text-slate-900">{{ \App\Money\Amount::format($monthlyExpenses) }}</b></span>
            <span>{{ __('Regular transfers per month') }}: <b class="font-mono text-slate-900">{{ \App\Money\Amount::format($monthlyTransfers) }}</b></span>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($statuses as $status)
                @if ($statusCounts[$status->value] ?? 0)
                    <x-recurring.status-badge :status="$status">{{ $status->label() }}: {{ $statusCounts[$status->value] }}</x-recurring.status-badge>
                @endif
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($states === [])
            <p class="p-6 text-slate-600">{{ __('No recurring payments yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Frequency') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('This month') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($states as $state)
                            @php($payment = $state->payment)
                            <tr wire:key="recurring-{{ $payment->id }}" @class(['text-slate-400' => $state->status === \App\Enums\RecurringStatus::NotDue])>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $payment->name }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $payment->bankAccount->name }}
                                        @if ($payment->category) · {{ $payment->category->name }} @endif
                                        @if ($payment->kind === \App\Enums\TransactionKind::TransferOut) · {{ __('Transfer') }} @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="font-mono font-medium text-slate-900">{{ \App\Money\Amount::format($payment->amount) }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $payment->frequency->label() }}
                                    @if ($payment->due_day)
                                        <div class="text-xs text-slate-500">{{ __('due on day :day', ['day' => $payment->due_day]) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-recurring.status-badge :status="$state->status" />
                                    <div class="mt-0.5 text-xs text-slate-500"><x-recurring.state-detail :state="$state" /></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        @if (in_array($state->status, [\App\Enums\RecurringStatus::Waiting, \App\Enums\RecurringStatus::Missing], true))
                                            <x-ui.icon-button icon="check" :label="__('Record payment')" wire:click="recordPayment({{ $payment->id }})" />
                                        @endif
                                        <x-ui.icon-button icon="edit" :label="__('Edit')" wire:click="edit({{ $payment->id }})" />
                                        <x-ui.icon-button icon="archive" :label="__('Archive')" wire:click="archive({{ $payment->id }})" />
                                        <x-ui.icon-button icon="delete" :label="__('Delete')" variant="danger" wire:click="delete({{ $payment->id }})" wire:confirm="{{ __('Delete the recurring payment :name?', ['name' => $payment->name]) }}" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($archivedPayments->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-slate-700">{{ __('Archived recurring payments') }}</h2>
            <x-ui.card :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($archivedPayments as $payment)
                        <li wire:key="archived-recurring-{{ $payment->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-2">
                            <span class="text-slate-600">{{ $payment->name }} · {{ \App\Money\Amount::format($payment->amount) }} · {{ $payment->frequency->label() }}</span>
                            <div class="flex gap-1.5">
                                <x-ui.icon-button icon="restore" :label="__('Restore')" wire:click="restore({{ $payment->id }})" />
                                <x-ui.icon-button icon="delete" :label="__('Delete')" variant="danger" wire:click="delete({{ $payment->id }})" wire:confirm="{{ __('Delete the recurring payment :name?', ['name' => $payment->name]) }}" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </section>
    @endif
</div>
