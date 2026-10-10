<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Dashboard') }}</h1>
        <x-ui.month-nav :label="$monthLabel" />
    </div>

    @if (! $hasAccounts)
        <x-ui.card>
            <h2 class="text-lg font-semibold">{{ __('Start by adding your bank accounts') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every transaction belongs to an account, so add them first.') }}</p>
            <x-ui.button :href="route('accounts')" class="mt-4">{{ __('Add bank accounts') }}</x-ui.button>
        </x-ui.card>
    @else
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-56">
                <x-ui.select name="owner" :label="__('Owner')" :options="$ownerFilterOptions" live />
            </div>
            <div class="w-full sm:w-56">
                <x-ui.select name="account" :label="__('Bank account')" :options="$accountFilterOptions" live />
            </div>
            @if ($filtersActive)
                <x-ui.button type="button" size="sm" variant="secondary" wire:click="resetFilters" class="mb-1">{{ __('Clear filters') }}</x-ui.button>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <x-ui.stat :label="__('Incomes')" :amount="$summary->current->income" :previous="$summary->previous->income" />
            <x-ui.stat :label="__('Expenses')" :amount="$summary->current->expenses" :previous="$summary->previous->expenses" :higher-is-better="false" />
            <x-ui.stat :label="__('Balance')" :amount="$summary->current->balance()" :previous="$summary->previous->balance()" signed />
            <x-ui.stat :label="__('Saved')" :amount="$summary->current->saved" :previous="$summary->previous->saved" signed />
            <x-ui.stat :label="__('Invested')" :amount="$summary->current->invested" :previous="$summary->previous->invested" />
            <x-ui.stat :label="__('Account movement')" :amount="$summary->current->movement" :previous="$summary->previous->movement" signed :hint="__('How much the money on the selected accounts went up or down, transfers included.')" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card class="space-y-4">
                <h2 class="text-lg font-semibold">{{ __('Expenses by category') }}</h2>
                @if ($donut['items'] === [])
                    <p class="text-sm text-slate-500">{{ __('No expenses in this month.') }}</p>
                @else
                    <div wire:key="donut-{{ md5(json_encode($donut)) }}" x-data="chart('donut', @js($donut))" class="h-80 w-full min-w-0"></div>
                @endif
            </x-ui.card>

            <x-ui.card class="space-y-4">
                <h2 class="text-lg font-semibold">{{ __('Last 12 months') }}</h2>
                <div wire:key="trend-{{ md5(json_encode($trend)) }}" x-data="chart('trend', @js($trend))" class="h-80 w-full min-w-0"></div>
            </x-ui.card>
        </div>

        <x-ui.card class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Where the money went') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Incomes on the left flow into expenses, savings and investments on the right.') }}</p>
            </div>
            @if ($flow['links'] === [])
                <p class="text-sm text-slate-500">{{ __('No transactions in this month.') }}</p>
            @else
                <div wire:key="flow-{{ md5(json_encode($flow)) }}" x-data="chart('flow', @js($flow))" class="h-96 w-full min-w-0"></div>
            @endif
        </x-ui.card>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card :padded="false">
                <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">{{ __('Expenses by category') }}</h2>
                <x-dashboard.category-list :rows="$summary->expenseCategories" :charts="$charts" :empty="__('No expenses in this month.')" tone="expense" />
            </x-ui.card>

            <x-ui.card :padded="false">
                <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">{{ __('Incomes by category') }}</h2>
                <x-dashboard.category-list :rows="$summary->incomeCategories" :charts="$charts" :empty="__('No incomes in this month.')" tone="income" />
            </x-ui.card>

            <x-ui.card :padded="false">
                <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">{{ __('Transfers by category') }}</h2>
                @if ($summary->transferCategories === [])
                    <p class="px-4 py-3 text-sm text-slate-500">{{ __('No transfers in this month.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2 font-medium">{{ __('Category') }}</th>
                                <th scope="col" class="px-4 py-2 text-right font-medium">{{ __('Out') }}</th>
                                <th scope="col" class="px-4 py-2 text-right font-medium">{{ __('In') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($summary->transferCategories as $row)
                                <tr wire:key="transfer-row-{{ $row->categoryId ?? 'none' }}">
                                    <td class="px-4 py-2"><a href="{{ $charts->categoryUrl($row->categoryId) }}" class="text-slate-800 hover:text-indigo-700 hover:underline">{{ $row->name }}</a></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-mono text-slate-700">{{ \App\Money\Amount::format($row->outgoing) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-mono text-slate-700">{{ \App\Money\Amount::format($row->incoming) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui.card>
        </div>

        @if ($summary->owners !== [])
            <x-ui.card :padded="false">
                <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">{{ __('By owner') }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-slate-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Owner') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Incomes') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Expenses') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Balance') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Transfers out') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Transfers in') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($summary->owners as $owner)
                                <tr wire:key="owner-{{ $owner->owner }}">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $owner->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-emerald-700">{{ \App\Money\Amount::format($owner->totals->income) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-red-700">{{ \App\Money\Amount::format($owner->totals->expenses) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-slate-900">{{ \App\Money\Amount::formatSigned($owner->totals->balance()) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-slate-600">{{ \App\Money\Amount::format($owner->transfersOut) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-slate-600">{{ \App\Money\Amount::format($owner->transfersIn) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif
    @endif
</div>
