<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Banking\CzechAccountNumber;
use App\Enums\PaymentFrequency;
use App\Enums\TransactionKind;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Money\Amount;
use App\Rules\ValidAmount;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RecurringPaymentForm extends Form
{
    private const MONTH_FORMAT = 'Y-m';

    public ?int $payment_id = null;

    public string $name = '';

    public string $kind = 'expense';

    public string $amount = '';

    public string $frequency = 'monthly';

    public string $start_month = '';

    public string $end_month = '';

    public string $due_day = '';

    public string $bank_account_id = '';

    public string $category_id = '';

    public string $counterparty_account = '';

    public string $match_text = '';

    public function fillFrom(RecurringPayment $payment): void
    {
        $this->payment_id = $payment->id;
        $this->name = $payment->name;
        $this->kind = $payment->kind->value;
        $this->amount = Amount::toInput($payment->amount);
        $this->frequency = $payment->frequency->value;
        $this->start_month = $payment->start_month->format(self::MONTH_FORMAT);
        $this->end_month = $payment->end_month?->format(self::MONTH_FORMAT) ?? '';
        $this->due_day = (string) ($payment->due_day ?? '');
        $this->bank_account_id = (string) $payment->bank_account_id;
        $this->category_id = (string) ($payment->category_id ?? '');
        $this->counterparty_account = $payment->counterparty_account ?? '';
        $this->match_text = $payment->match_text ?? '';
    }

    public function save(): RecurringPayment
    {
        $this->name = trim($this->name);
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'kind' => TransactionKind::from($this->kind),
            'amount' => Amount::parse($this->amount) ?? 0,
            'frequency' => PaymentFrequency::from($this->frequency),
            'start_month' => $this->start_month.'-01',
            'end_month' => $this->end_month === '' ? null : $this->end_month.'-01',
            'due_day' => $this->due_day === '' ? null : (int) $this->due_day,
            'bank_account_id' => (int) $this->bank_account_id,
            'category_id' => $this->category_id === '' ? null : (int) $this->category_id,
            'counterparty_account' => self::normalizeAccount($this->counterparty_account),
            'match_text' => trim($this->match_text) === '' ? null : trim($this->match_text),
        ];

        if ($this->payment_id === null) {
            return RecurringPayment::query()->create($attributes);
        }

        $payment = RecurringPayment::query()->findOrFail($this->payment_id);
        $payment->update($attributes);

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(array_map(fn (TransactionKind $kind): string => $kind->value, RecurringPayment::KINDS))],
            'amount' => ['required', 'string', new ValidAmount],
            'frequency' => ['required', Rule::enum(PaymentFrequency::class)],
            'start_month' => ['required', 'date_format:'.self::MONTH_FORMAT],
            'end_month' => ['nullable', 'date_format:'.self::MONTH_FORMAT, 'after_or_equal:start_month'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where(
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('archived_at')
                    ->orWhere('id', $this->original()?->bank_account_id)),
            )],
            'category_id' => ['nullable', $this->categoryMatchesKind(...)],
            'counterparty_account' => ['nullable', 'string', 'max:50'],
            'match_text' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function categoryMatchesKind(string $attribute, mixed $value, Closure $fail): void
    {
        $expectedType = TransactionKind::tryFrom($this->kind)?->categoryType();
        $category = ctype_digit((string) $value) ? Category::query()->find((int) $value) : null;

        if ($category === null || $category->type !== $expectedType) {
            $fail('validation.category_mismatch')->translate();

            return;
        }

        if ($category->archived_at !== null && $category->id !== $this->original()?->category_id) {
            $fail('validation.category_archived')->translate();
        }
    }

    private function original(): ?RecurringPayment
    {
        return $this->payment_id === null ? null : RecurringPayment::query()->find($this->payment_id);
    }

    private static function normalizeAccount(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return CzechAccountNumber::parse($trimmed)?->toString() ?? $trimmed;
    }
}
