<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>

    @if (session('status'))
        <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
    @endif

    @unless ($hasCategories)
        <x-ui.card>
            <h2 class="text-lg font-semibold">{{ __('No categories yet') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Start with a ready-made set of common categories and adjust it, or add your own.') }}</p>
            <x-ui.button type="button" class="mt-4" wire:click="addDefaults">{{ __('Add default categories') }}</x-ui.button>
        </x-ui.card>
    @endunless

    @if ($form_open)
        <x-ui.card class="max-w-xl">
            <h2 class="text-lg font-semibold">{{ $editing_id ? __('Edit category') : __('New category') }}</h2>

            <form wire:submit="save" class="mt-6 space-y-4">
                <x-ui.field name="category_name" :label="__('Category name')" autocomplete="off" />

                @if ($editing_id)
                    <p class="text-sm text-slate-600">{{ __('Type') }}: {{ $typeLabels[$category_type] }}</p>
                @else
                    <x-ui.select name="category_type" :label="__('Type')" :options="$typeLabels" />
                @endif

                <div class="flex gap-3">
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($groups as $type => $categories)
            <x-ui.card :padded="false" wire:key="group-{{ $type }}">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <h2 class="font-semibold">{{ $typeLabels[$type] }}</h2>
                    <x-ui.button type="button" size="sm" variant="secondary" wire:click="create('{{ $type }}')">{{ __('Add') }}</x-ui.button>
                </div>

                @if ($categories->isEmpty())
                    <p class="px-4 py-3 text-sm text-slate-500">{{ __('No categories.') }}</p>
                @else
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($categories as $category)
                            <li wire:key="category-{{ $category->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                                <span class="text-slate-900">{{ $category->name }}</span>
                                <div class="flex gap-2">
                                    <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $category->id }})">{{ __('Edit') }}</x-ui.button>
                                    <x-ui.button type="button" size="sm" variant="secondary" wire:click="archive({{ $category->id }})">{{ __('Archive') }}</x-ui.button>
                                    <x-ui.button type="button" size="sm" variant="danger" wire:click="delete({{ $category->id }})" wire:confirm="{{ __('Delete the category :name?', ['name' => $category->name]) }}">{{ __('Delete') }}</x-ui.button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    @if ($archivedCategories->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-slate-700">{{ __('Archived categories') }}</h2>
            <x-ui.card :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($archivedCategories as $category)
                        <li wire:key="archived-{{ $category->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-2">
                            <span class="text-slate-600">{{ $category->name }} · {{ $category->type->label() }}</span>
                            <div class="flex gap-2">
                                <x-ui.button type="button" size="sm" variant="secondary" wire:click="restore({{ $category->id }})">{{ __('Restore') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="danger" wire:click="delete({{ $category->id }})" wire:confirm="{{ __('Delete the category :name?', ['name' => $category->name]) }}">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </section>
    @endif
</div>
