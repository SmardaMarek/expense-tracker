<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Banking\CzechAccountNumber;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Money\Amount;
use App\Rules\ValidAmount;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TransactionForm extends Form
{
    public ?int $transaction_id = null;

    public string $bank_account_id = '';

    public string $booked_on = '';

    public string $kind = 'expense';

    public string $amount = '';

    public string $category_id = '';

    public string $recurring_payment_id = '';

    public string $counterparty_name = '';

    public string $counterparty_account = '';

    public string $variable_symbol = '';

    public string $message = '';

    public string $note = '';

    public function fillFrom(Transaction $transaction): void
    {
        $this->transaction_id = $transaction->id;
        $this->bank_account_id = (string) $transaction->bank_account_id;
        $this->booked_on = $transaction->booked_on->toDateString();
        $this->kind = $transaction->kind()->value;
        $this->amount = Amount::toInput($transaction->amount);
        $this->category_id = (string) ($transaction->category_id ?? '');
        $this->recurring_payment_id = (string) ($transaction->recurring_payment_id ?? '');
        $this->counterparty_name = $transaction->counterparty_name ?? '';
        $this->counterparty_account = $transaction->counterparty_account ?? '';
        $this->variable_symbol = $transaction->variable_symbol ?? '';
        $this->message = $transaction->message ?? '';
        $this->note = $transaction->note ?? '';
    }

    public function save(): Transaction
    {
        $this->validate();

        $kind = TransactionKind::from($this->kind);
        $attributes = [
            'bank_account_id' => (int) $this->bank_account_id,
            'booked_on' => $this->booked_on,
            'amount' => $kind->sign() * (Amount::parse($this->amount) ?? 0),
            'type' => $kind->type(),
            'category_id' => $this->category_id === '' ? null : (int) $this->category_id,
            'recurring_payment_id' => $this->recurring_payment_id === '' ? null : (int) $this->recurring_payment_id,
            'counterparty_name' => self::nullIfBlank($this->counterparty_name),
            'counterparty_account' => self::normalizeAccount($this->counterparty_account),
            'variable_symbol' => self::nullIfBlank($this->variable_symbol),
            'message' => self::nullIfBlank($this->message),
            'note' => self::nullIfBlank($this->note),
        ];

        if ($this->transaction_id === null) {
            return Transaction::query()->create($attributes + ['source' => TransactionSource::Manual]);
        }

        $transaction = Transaction::query()->findOrFail($this->transaction_id);
        $transaction->update($attributes);

        return $transaction;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where(
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('archived_at')
                    ->orWhere('id', $this->original()?->bank_account_id)),
            )],
            'booked_on' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::enum(TransactionKind::class)],
            'amount' => ['required', 'string', new ValidAmount],
            'category_id' => ['nullable', $this->categoryMatchesKind(...)],
            'recurring_payment_id' => ['nullable', $this->recurringPaymentMatchesKind(...)],
            'counterparty_name' => ['nullable', 'string', 'max:150'],
            'counterparty_account' => ['nullable', 'string', 'max:50'],
            'variable_symbol' => ['nullable', 'regex:/^\d{1,10}$/'],
            'message' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function categoryMatchesKind(string $attribute, mixed $value, Closure $fail): void
    {
        $expectedType = TransactionKind::tryFrom($this->kind)?->categoryType();
        $category = ctype_digit((string) $value) ? Category::query()->find((int) $value) : null;

        if ($category === null || $expectedType === null || $category->type !== $expectedType) {
            $fail('validation.category_mismatch')->translate();

            return;
        }

        if ($category->archived_at !== null && $category->id !== $this->original()?->category_id) {
            $fail('validation.category_archived')->translate();
        }
    }

    public function applyRecurringPayment(RecurringPayment $payment): void
    {
        $this->recurring_payment_id = (string) $payment->id;
        $this->kind = $payment->kind->value;
        $this->amount = Amount::toInput($payment->amount);
        $this->bank_account_id = (string) $payment->bank_account_id;
        $this->category_id = (string) ($payment->category_id ?? '');
        $this->counterparty_name = $payment->name;
        $this->counterparty_account = $payment->counterparty_account ?? '';
    }

    private function recurringPaymentMatchesKind(string $attribute, mixed $value, Closure $fail): void
    {
        $payment = ctype_digit((string) $value) ? RecurringPayment::query()->find((int) $value) : null;

        if ($payment === null || $payment->kind->value !== $this->kind) {
            $fail('validation.recurring_mismatch')->translate();

            return;
        }

        if ($payment->archived_at !== null && $payment->id !== $this->original()?->recurring_payment_id) {
            $fail('validation.recurring_archived')->translate();
        }
    }

    private function original(): ?Transaction
    {
        return $this->transaction_id === null ? null : Transaction::query()->find($this->transaction_id);
    }

    private static function nullIfBlank(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function normalizeAccount(string $value): ?string
    {
        $trimmed = self::nullIfBlank($value);

        if ($trimmed === null) {
            return null;
        }

        return CzechAccountNumber::parse($trimmed)?->toString() ?? $trimmed;
    }
}
