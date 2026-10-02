<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetOwnerPassword extends Command
{
    protected $signature = 'owner:reset-password
        {--email= : Email of the user to update (defaults to the Owner, the first user)}
        {--password= : The new password (skips the interactive prompt)}';

    protected $description = "Reset the Owner's password";

    public function handle(): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error($this->option('email')
                ? "No user found with email {$this->option('email')}."
                : 'No user exists yet. Run `php artisan db:seed` to create the Owner.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        $confirmation = $password;

        if (! is_string($password) || $password === '') {
            $password = (string) $this->secret('New password');
            $confirmation = (string) $this->secret('Confirm new password');
        }

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->update(['password' => $password]);

        $this->info("Password updated for {$user->email}.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $email = $this->option('email');

        if (is_string($email) && $email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return User::query()->oldest()->first();
    }
}
