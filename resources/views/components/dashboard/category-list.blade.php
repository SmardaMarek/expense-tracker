@props(['rows', 'charts', 'empty', 'tone' => 'expense'])

@if ($rows === [])
    <p class="px-4 py-3 text-sm text-slate-500">{{ $empty }}</p>
@else
    <ul class="divide-y divide-slate-100 text-sm">
        @foreach ($rows as $row)
            <li wire:key="{{ $tone }}-row-{{ $row->categoryId ?? 'none' }}" class="px-4 py-2">
                <div class="flex items-baseline justify-between gap-3">
                    <a href="{{ $charts->categoryUrl($row->categoryId) }}" @class([
                        'hover:text-indigo-700 hover:underline',
                        'font-medium text-amber-700' => $row->categoryId === null,
                        'text-slate-800' => $row->categoryId !== null,
                    ])>{{ $row->name }}</a>
                    <span class="whitespace-nowrap font-mono text-slate-700">{{ \App\Money\Amount::format($row->amount) }}</span>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div @class([
                            'h-full rounded-full',
                            'bg-rose-400' => $tone === 'expense',
                            'bg-emerald-400' => $tone === 'income',
                        ]) style="width: {{ round($row->share * 100, 1) }}%"></div>
                    </div>
                    <span class="w-10 text-right text-xs text-slate-500">{{ round($row->share * 100) }} %</span>
                </div>
            </li>
        @endforeach
    </ul>
@endif
