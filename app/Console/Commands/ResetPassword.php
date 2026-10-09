<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

class ResetPassword extends Command
{
    protected $signature = 'app:reset-password';

    protected $description = 'Set a new password for the account (use when the password is forgotten)';

    public function handle(): int
    {
        $user = User::query()->first();

        if ($user === null) {
            $this->components->error('No account exists yet. Open the app in the browser to create it.');

            return self::FAILURE;
        }

        $newPassword = password(
            label: "New password for {$user->email}",
            validate: fn (string $value): ?string => Validator::make(
                ['password' => $value],
                ['password' => ['required', 'string', Password::defaults()]],
            )->errors()->first('password') ?: null,
        );

        $user->update(['password' => $newPassword]);

        $this->components->info('Password changed.');

        return self::SUCCESS;
    }
}
