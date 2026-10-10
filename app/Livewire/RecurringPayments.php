<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PaymentFrequency;
use App\Enums\RecurringStatus;
use App\Enums\TransactionKind;
use App\Livewire\Concerns\NavigatesMonths;
use App\Livewire\Forms\RecurringPaymentForm;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Recurring\RecordPayment;
use App\Recurring\RecurringOverview;
use App\Recurring\RecurringState;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Recurring payments')]
class RecurringPayments extends Component
{
    use NavigatesMonths;

    public bool $form_open = false;

    public RecurringPaymentForm $form;

    public function create(): void
    {
        $this->closeForm();
        $this->form->start_month = $this->selectedMonth()->format('Y-m');
        $this->form->bank_account_id = (string) (BankAccount::query()->active()->get(['id', 'name'])->sortByLocale('name')->first()?->id ?? '');
        $this->form_open = true;
    }

    public function edit(int $id): void
    {
        $this->closeForm();
        $this->form->fillFrom(RecurringPayment::query()->findOrFail($id));
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
        session()->flash('status', __('Recurring payment saved.'));
    }

    public function closeForm(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->resetValidation();
        $this->form_open = false;
    }

    public function recordPayment(int $id, RecordPayment $recordPayment): void
    {
        $payment = RecurringPayment::query()->active()->findOrFail($id);
        $recordPayment->handle($payment, $this->selectedMonth());

        session()->flash('status', __('Payment :name recorded.', ['name' => $payment->name]));
    }

    public function archive(int $id): void
    {
        RecurringPayment::query()->findOrFail($id)->update(['archived_at' => now()]);
    }

    public function restore(int $id): void
    {
        RecurringPayment::query()->findOrFail($id)->update(['archived_at' => null]);
    }

    public function delete(int $id): void
    {
        $payment = RecurringPayment::query()->findOrFail($id);

        if ($payment->transactions()->exists()) {
            session()->flash('error', __('This recurring payment has linked transactions, so it cannot be deleted. Archive it instead.'));

            return;
        }

        $payment->delete();

        if ($this->form->payment_id === $id) {
            $this->closeForm();
        }
    }

    public function render(RecurringOverview $overview): View
    {
        $states = $overview->forMonth($this->selectedMonth());
        $active = RecurringPayment::query()->active()->get();

        return view('livewire.recurring-payments', [
            'states' => $states,
            'archivedPayments' => RecurringPayment::query()->archived()->with(['bankAccount', 'category'])->get()->sortByLocale('name'),
            'monthLabel' => $this->monthLabel(),
            'hasAccounts' => BankAccount::query()->active()->exists(),
            'monthlyExpenses' => $active->where('kind', TransactionKind::Expense)->sum(fn (RecurringPayment $payment): int => $payment->monthlyEquivalent()),
            'monthlyTransfers' => $active->where('kind', TransactionKind::TransferOut)->sum(fn (RecurringPayment $payment): int => $payment->monthlyEquivalent()),
            'statusCounts' => collect($states)->countBy(fn (RecurringState $state): string => $state->status->value)->all(),
            'kindOptions' => collect(RecurringPayment::KINDS)->mapWithKeys(fn (TransactionKind $kind): array => [$kind->value => $kind->label()])->all(),
            'frequencyOptions' => collect(PaymentFrequency::cases())->mapWithKeys(fn (PaymentFrequency $frequency): array => [$frequency->value => $frequency->label()])->all(),
            'accountOptions' => $this->accountOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'statuses' => [RecurringStatus::Paid, RecurringStatus::Waiting, RecurringStatus::Missing],
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function accountOptions(): array
    {
        $currentId = $this->form->payment_id === null
            ? null
            : RecurringPayment::query()->whereKey($this->form->payment_id)->value('bank_account_id');

        return BankAccount::query()
            ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $currentId))
            ->get(['id', 'name'])
            ->sortByLocale('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function categoryOptions(): array
    {
        $categoryType = (TransactionKind::tryFrom($this->form->kind) ?? TransactionKind::Expense)->categoryType();
        $currentId = $this->form->payment_id === null
            ? null
            : RecurringPayment::query()->whereKey($this->form->payment_id)->value('category_id');

        return ['' => __('— uncategorized —')] + Category::query()
            ->ofType($categoryType)
            ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $currentId))
            ->get(['id', 'name'])
            ->sortByLocale('name')
            ->pluck('name', 'id')
            ->all();
    }
}
