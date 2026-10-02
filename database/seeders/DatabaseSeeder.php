<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\User\CreateUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Create the single Owner with their Account and Workspace. Safe to run
     * again: it does nothing once a user exists.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command?->info('An Owner already exists, skipping.');

            return;
        }

        $email = (string) config('app.owner.email');
        $password = (string) config('app.owner.password');
        $generatedPassword = $password === '';

        if ($generatedPassword) {
            $password = Str::password(20, symbols: false);
        }

        CreateUser::execute([
            'name' => (string) config('app.owner.name'),
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Owner created: {$email}");

        if ($generatedPassword) {
            $this->command?->warn("Generated password (shown once): {$password}");
            $this->command?->line('Change it with `php artisan owner:reset-password`.');
        }
    }
}
