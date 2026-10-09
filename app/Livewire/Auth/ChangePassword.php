<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('password_status', __('Password changed.'));
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
