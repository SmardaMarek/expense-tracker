<?php

declare(strict_types=1);

namespace App\Livewire\Household;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Members extends Component
{
    public string $first_name = '';

    public string $second_name = '';

    public function mount(): void
    {
        $names = Member::query()->pluck('name', 'position');

        $this->first_name = $names[Member::FIRST] ?? '';
        $this->second_name = $names[Member::SECOND] ?? '';
    }

    public function save(): void
    {
        $this->first_name = trim($this->first_name);
        $this->second_name = trim($this->second_name);

        $this->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'second_name' => [
                Rule::requiredIf(fn (): bool => Member::query()->where('position', Member::SECOND)->exists()),
                'nullable',
                'string',
                'max:50',
                'different:first_name',
            ],
        ]);

        DB::transaction(function (): void {
            Member::query()->updateOrCreate(['position' => Member::FIRST], ['name' => $this->first_name]);

            if ($this->second_name !== '') {
                Member::query()->updateOrCreate(['position' => Member::SECOND], ['name' => $this->second_name]);
            }
        });

        session()->flash('members_status', __('Household saved.'));
    }

    public function render(): View
    {
        return view('livewire.household.members');
    }
}
