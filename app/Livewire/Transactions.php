<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Livewire\Concerns\FiltersByAccount;
use App\Livewire\Concerns\NavigatesMonths;
use App\Livewire\Forms\TransactionForm;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Transaction;
use App\Transactions\TransactionFilter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Transactions')]
class Transactions extends Component
{
    use FiltersByAccount;
    use NavigatesMonths;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $category = '';

    public bool $form_open = false;

    public TransactionForm $form;

    public function resetFilters(): void
    {
        $this->reset('account', 'owner', 'type', 'category');
    }

    public function updatedType(): void
    {
        if (! array_key_exists($this->category, $this->categoryFilterOptions())) {
            $this->category = '';
        }
    }

    public function create(): void
    {
        $this->closeForm();

        $month = $this->selectedMonth();
        $today = CarbonImmutable::today();

        $this->form->booked_on = ($today->isSameMonth($month) ? $today : $month)->toDateString();
        $this->form->bank_account_id = (string) (BankAccount::query()->active()->orderBy('name')->value('id') ?? '');
        $this->form_open = true;
    }

    public function edit(int $id): void
    {
        $this->closeForm();
        $this->form->fillFrom(Transaction::query()->findOrFail($id));
        $this->form_open = true;
    }

    public function updatedFormKind(): void
    {
        $this->form->category_id = '';
    }

    public function save(): void
    {
        $this->form->save();

        $this->closeForm();
        session()->flash('status', __('Transaction saved.'));
    }

    public function closeForm(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->resetValidation();
        $this->form_open = false;
    }

    public function delete(int $id): void
    {
        Transaction::query()->findOrFail($id)->delete();

        if ($this->form->transaction_id === $id) {
            $this->closeForm();
        }
    }

    public function render(): View
    {
        $filter = $this->filter();

        return view('livewire.transactions', [
            'transactions' => $filter->apply(Transaction::query()->with(['bankAccount.member', 'category']))
                ->orderByDesc('booked_on')
                ->orderByDesc('id')
                ->get(),
            'monthLabel' => $this->monthLabel(),
            'hasAccounts' => BankAccount::query()->active()->exists(),
            'filtersActive' => $this->account !== '' || $this->owner !== '' || $this->type !== '' || $this->category !== '',
            'accountFilterOptions' => $this->accountFilterOptions(),
            'ownerFilterOptions' => $this->ownerFilterOptions(),
            'typeFilterOptions' => ['' => __('All kinds')] + $this->typeOptions(),
            'categoryFilterOptions' => $this->categoryFilterOptions(),
            'formAccountOptions' => $this->formAccountOptions(),
            'kindOptions' => $this->kindOptions(),
            'formCategoryOptions' => $this->formCategoryOptions(),
            'isTransfer' => TransactionKind::tryFrom($this->form->kind)?->isTransfer() ?? false,
        ]);
    }

    private function filter(): TransactionFilter
    {
        return TransactionFilter::fromInput($this->month, $this->account, $this->owner, $this->type, $this->category);
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        return collect(TransactionType::cases())
            ->mapWithKeys(fn (TransactionType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function kindOptions(): array
    {
        return collect(TransactionKind::cases())
            ->mapWithKeys(fn (TransactionKind $kind): array => [$kind->value => $kind->label()])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function categoryFilterOptions(): array
    {
        $categoryType = TransactionType::tryFrom($this->type)?->categoryType();

        return ['' => __('All categories'), TransactionFilter::UNCATEGORIZED => __('Uncategorized')]
            + Category::query()
                ->when($categoryType !== null, fn ($query) => $query->ofType($categoryType))
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Category $category): array => [
                    $category->id => $categoryType === null
                        ? "{$category->name} ({$category->type->label()})"
                        : $category->name,
                ])
                ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function formAccountOptions(): array
    {
        $currentId = $this->form->transaction_id === null
            ? null
            : Transaction::query()->whereKey($this->form->transaction_id)->value('bank_account_id');

        return BankAccount::query()
            ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $currentId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function formCategoryOptions(): array
    {
        $categoryType = (TransactionKind::tryFrom($this->form->kind) ?? TransactionKind::Expense)->categoryType();

        $currentId = $this->form->transaction_id === null
            ? null
            : Transaction::query()->whereKey($this->form->transaction_id)->value('category_id');

        return ['' => __('— uncategorized —')] + Category::query()
            ->ofType($categoryType)
            ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $currentId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
