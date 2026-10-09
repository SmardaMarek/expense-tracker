<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Categories\DefaultCategories;
use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categories')]
class Categories extends Component
{
    public bool $form_open = false;

    public ?int $editing_id = null;

    public string $category_name = '';

    public string $category_type = 'expense';

    public function create(string $type = 'expense'): void
    {
        $this->closeForm();
        $this->category_type = (CategoryType::tryFrom($type) ?? CategoryType::Expense)->value;
        $this->form_open = true;
    }

    public function edit(int $id): void
    {
        $category = Category::query()->findOrFail($id);

        $this->resetValidation();
        $this->editing_id = $category->id;
        $this->category_name = $category->name;
        $this->category_type = $category->type->value;
        $this->form_open = true;
    }

    public function save(): void
    {
        $this->category_name = trim($this->category_name);

        $this->validate([
            'category_name' => ['required', 'string', 'max:50'],
            'category_type' => ['required', Rule::enum(CategoryType::class)],
        ]);

        $category = $this->editing_id === null ? null : Category::query()->findOrFail($this->editing_id);
        $type = $category === null ? CategoryType::from($this->category_type) : $category->type;

        if ($this->nameIsTaken($type)) {
            $this->addError('category_name', __('A category with this name already exists.'));

            return;
        }

        if ($category === null) {
            Category::query()->create(['type' => $type, 'name' => $this->category_name]);
        } else {
            $category->update(['name' => $this->category_name]);
        }

        $this->closeForm();
        session()->flash('status', __('Category saved.'));
    }

    public function closeForm(): void
    {
        $this->reset('form_open', 'editing_id', 'category_name', 'category_type');
        $this->resetValidation();
    }

    public function addDefaults(DefaultCategories $defaults, string $type = ''): void
    {
        $groupType = CategoryType::tryFrom($type);

        $created = $groupType === null
            ? $defaults->createForEmptyGroups()
            : $defaults->createIfGroupEmpty($groupType);

        if ($created > 0) {
            session()->flash('status', __('Default categories added.'));
        }
    }

    public function archive(int $id): void
    {
        Category::query()->findOrFail($id)->update(['archived_at' => now()]);
    }

    public function restore(int $id): void
    {
        Category::query()->findOrFail($id)->update(['archived_at' => null]);
    }

    public function delete(int $id): void
    {
        $category = Category::query()->findOrFail($id);

        if ($category->transactions()->exists()) {
            session()->flash('error', __('This category is used by transactions, so it cannot be deleted. Archive it instead.'));

            return;
        }

        $category->delete();

        if ($this->editing_id === $id) {
            $this->closeForm();
        }
    }

    public function render(): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('livewire.categories', [
            'groups' => collect(CategoryType::cases())->mapWithKeys(fn (CategoryType $type): array => [
                $type->value => $categories->filter(fn (Category $category): bool => $category->type === $type
                    && $category->archived_at === null)->values(),
            ]),
            'archivedCategories' => $categories->whereNotNull('archived_at')->values(),
            'hasCategories' => $categories->isNotEmpty(),
            'emptyTypes' => collect(CategoryType::cases())
                ->reject(fn (CategoryType $type): bool => $categories->contains('type', $type))
                ->map(fn (CategoryType $type): string => $type->value)
                ->values()
                ->all(),
            'typeLabels' => collect(CategoryType::cases())
                ->mapWithKeys(fn (CategoryType $type): array => [$type->value => $type->label()])
                ->all(),
        ]);
    }

    private function nameIsTaken(CategoryType $type): bool
    {
        $wanted = Str::lower($this->category_name);

        return Category::query()
            ->ofType($type)
            ->when($this->editing_id !== null, fn ($query) => $query->whereKeyNot($this->editing_id))
            ->pluck('name')
            ->contains(fn (string $name): bool => Str::lower($name) === $wanted);
    }
}
