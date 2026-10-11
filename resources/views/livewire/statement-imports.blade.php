<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Statement import') }}</h1>

    @if (session('status'))
        <x-ui.alert>
            {{ session('status') }}
            @if (session('imported_month'))
                <a href="{{ route('transactions', ['month' => session('imported_month')]) }}" class="font-semibold underline">{{ __('Show transactions') }}</a>
            @endif
        </x-ui.alert>
    @endif

    @if ($failure)
        <x-ui.alert variant="error">{{ $failure }}</x-ui.alert>
    @endif

    @if (! $preview)
        <x-ui.card class="max-w-2xl space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Upload a PDF statement') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Supported banks: ČSOB. The file is read on this computer and deleted right after the import.') }}</p>
            </div>
            <div>
                <x-ui.file-input name="statement" :label="__('Statement (PDF)')" accept="application/pdf,.pdf" />
                <p wire:loading wire:target="statement" class="mt-2 text-sm text-slate-500" role="status">{{ __('Reading the statement…') }}</p>
            </div>
        </x-ui.card>
    @else
        <x-ui.card class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('Check before importing') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $preview['bank'] }} · {{ $preview['account'] }} ({{ $preview['accountNumber'] }}) · {{ $preview['period'] }}</p>
                </div>
                <div class="flex gap-3">
                    <x-ui.button type="button" wire:click="import" :disabled="$preview['newCount'] === 0">
                        {{ trans_choice('Import :count transaction|Import :count transactions', $preview['newCount'], ['count' => $preview['newCount']]) }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-800">✓ {{ __('Balances match: :opening → :closing', ['opening' => $preview['opening'], 'closing' => $preview['closing']]) }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ __('New: :count', ['count' => $preview['newCount']]) }}</span>
                @if ($preview['duplicateCount'] > 0)
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">{{ __('Already imported: :count', ['count' => $preview['duplicateCount']]) }}</span>
                @endif
                @if ($hidden_keys !== [])
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-indigo-700">{{ __('Hidden: :count', ['count' => count($hidden_keys)]) }}</span>
                @endif
                @if ($preview['transferCount'] > 0)
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-indigo-700">{{ __('Transfers between your accounts: :count', ['count' => $preview['transferCount']]) }}</span>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Kind') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($preview['rows'] as $index => $row)
                            <tr wire:key="preview-{{ $index }}" @class(['text-slate-400' => $row['duplicate']])>
                                <td class="whitespace-nowrap px-4 py-2">
                                    {{ $row['date'] }}
                                    @if ($row['posted'])
                                        <div class="text-xs text-slate-500">{{ __('posted :date', ['date' => $row['posted']]) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if (in_array($row['key'], $hidden_keys, true))
                                        <div class="flex items-center gap-1.5 font-medium italic text-slate-500">
                                            <x-ui.icon name="eye-slash" class="h-4 w-4" />
                                            {{ __('Hidden payment') }}
                                        </div>
                                    @else
                                        <div class="font-medium">{{ $row['name'] }}</div>
                                        @if ($row['detail'])
                                            <div class="text-xs text-slate-500">{{ $row['detail'] }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $row['typeLabel'] }}</td>
                                <td @class([
                                    'whitespace-nowrap px-4 py-2 text-right font-mono',
                                    'text-red-700' => ! $row['duplicate'] && $row['type'] === 'expense',
                                    'text-emerald-700' => ! $row['duplicate'] && $row['type'] === 'income',
                                    'text-slate-500' => $row['duplicate'] || $row['type'] === 'transfer',
                                ])>{{ \App\Money\Amount::format($row['amount']) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $row['duplicate'] ? __('Already imported') : __('New') }}</td>
                                <td class="px-4 py-2 text-right">
                                    @unless ($row['duplicate'])
                                        @php($isHidden = in_array($row['key'], $hidden_keys, true))
                                        <x-ui.icon-button
                                            :icon="$isHidden ? 'eye' : 'eye-slash'"
                                            :label="$isHidden ? __('Show recipient') : __('Hide recipient')"
                                            wire:click="toggleHidden('{{ $row['key'] }}')"
                                            :class="$isHidden ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : ''"
                                        />
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    @if ($hasImports)
        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-700">{{ __('Import history') }}</h2>
                <div class="w-full sm:w-72">
                    <x-ui.field name="search" type="search" :label="__('Search')" :placeholder="__('Account, bank or month…')" autocomplete="off" live />
                </div>
            </div>
            <x-ui.card :padded="false">
                @if ($imports->isEmpty())
                    <p class="p-6 text-sm text-slate-600">{{ __('No import matches the search.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <x-ui.sortable-header column="account" :label="__('Bank account')" :sort="$sort" :direction="$direction" />
                                    <x-ui.sortable-header column="bank" :label="__('Bank')" :sort="$sort" :direction="$direction" />
                                    <x-ui.sortable-header column="period" :label="__('Period')" :sort="$sort" :direction="$direction" />
                                    <x-ui.sortable-header column="imported" :label="__('Imported')" :sort="$sort" :direction="$direction" align="right" />
                                    <x-ui.sortable-header column="skipped" :label="__('Skipped')" :sort="$sort" :direction="$direction" align="right" />
                                    <x-ui.sortable-header column="created" :label="__('Imported on')" :sort="$sort" :direction="$direction" />
                                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($imports as $import)
                                    <tr wire:key="import-{{ $import->id }}">
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-slate-900">{{ $import->bankAccount->name }}</div>
                                            <div class="font-mono text-xs text-slate-500">{{ $import->bankAccount->account_number }}</div>
                                        </td>
                                        <td class="px-4 py-2 text-slate-700">{{ $import->bank->label() }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-slate-700">{{ $import->period_from->format('j. n. Y') }} – {{ $import->period_to->format('j. n. Y') }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-slate-700">{{ $import->imported_count }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-slate-500">{{ $import->skipped_count }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $import->created_at->format('j. n. Y H:i') }}</td>
                                        <td class="px-4 py-2">
                                            <div class="flex justify-end">
                                                <x-ui.icon-button
                                                    icon="delete"
                                                    :label="__('Delete import')"
                                                    variant="danger"
                                                    wire:click="deleteImport({{ $import->id }})"
                                                    wire:confirm="{{ __('Delete this import and its :count transactions? Changes you made to them (categories, hiding, recurring payment links) will be lost. You can import the statement again afterwards.', ['count' => $import->transactions_count]) }}"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-ui.pagination :paginator="$imports" />
                @endif
            </x-ui.card>
        </section>
    @endif
</div>
