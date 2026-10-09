<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Banking\CzechAccountNumber;
use App\Models\BankAccount;
use App\Models\Member;
use App\Rules\ValidCzechAccountNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bank accounts')]
class BankAccounts extends Component
{
    public const SHARED = 'shared';

    public bool $form_open = false;

    public ?int $editing_id = null;

    public string $account_name = '';

    public string $owner = self::SHARED;

    public string $account_number = '';

    public function create(): void
    {
        $this->closeForm();
        $this->form_open = true;
    }

    public function edit(int $id): void
    {
        $account = BankAccount::query()->findOrFail($id);

        $this->resetValidation();
        $this->editing_id = $account->id;
        $this->account_name = $account->name;
        $this->owner = $account->member_id === null ? self::SHARED : (string) $account->member_id;
        $this->account_number = $account->account_number;
        $this->form_open = true;
    }

    public function save(): void
    {
        $this->account_name = trim($this->account_name);

        $this->validate([
            'account_name' => ['required', 'string', 'max:100'],
            'owner' => ['required', Rule::in(array_map(strval(...), array_keys($this->ownerOptions())))],
            'account_number' => ['required', 'string', new ValidCzechAccountNumber],
        ]);

        $normalizedNumber = CzechAccountNumber::parse($this->account_number)?->toString() ?? '';

        $alreadyRegistered = BankAccount::query()
            ->where('account_number', $normalizedNumber)
            ->when($this->editing_id !== null, fn ($query) => $query->whereKeyNot($this->editing_id))
            ->exists();

        if ($alreadyRegistered) {
            $this->addError('account_number', __('This account number is already registered.'));

            return;
        }

        $attributes = [
            'name' => $this->account_name,
            'member_id' => $this->owner === self::SHARED ? null : (int) $this->owner,
            'account_number' => $normalizedNumber,
        ];

        if ($this->editing_id === null) {
            BankAccount::query()->create($attributes);
        } else {
            BankAccount::query()->findOrFail($this->editing_id)->update($attributes);
        }

        $this->closeForm();
        session()->flash('status', __('Bank account saved.'));
    }

    public function closeForm(): void
    {
        $this->reset('form_open', 'editing_id', 'account_name', 'owner', 'account_number');
        $this->resetValidation();
    }

    public function archive(int $id): void
    {
        BankAccount::query()->findOrFail($id)->update(['archived_at' => now()]);
    }

    public function restore(int $id): void
    {
        BankAccount::query()->findOrFail($id)->update(['archived_at' => null]);
    }

    public function delete(int $id): void
    {
        BankAccount::query()->findOrFail($id)->delete();

        if ($this->editing_id === $id) {
            $this->closeForm();
        }
    }

    public function render(): View
    {
        return view('livewire.bank-accounts', [
            'activeAccounts' => BankAccount::query()->active()->with('member')->orderBy('name')->get(),
            'archivedAccounts' => BankAccount::query()->archived()->with('member')->orderBy('name')->get(),
            'ownerOptions' => $this->ownerOptions(),
            'hasMembers' => Member::query()->exists(),
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function ownerOptions(): array
    {
        $options = Member::query()->orderBy('position')->pluck('name', 'id')->all();
        $options[self::SHARED] = __('Shared');

        return $options;
    }
}
