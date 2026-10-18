<?php

namespace App\Console\Commands;

use App\Domain\Access\Actions\SyncPermissions;
use App\Domain\Access\Enums\SystemRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates the first Super Admin on a server. The generated password is shown
 * once in the terminal of whoever runs the command and stored nowhere else.
 */
final class CreateSuperAdminCommand extends Command
{
    protected $signature = 'portal:create-super-admin
        {email : Email address used to sign in}
        {--name= : Display name (defaults to the part of the email before @)}
        {--promote : If the account exists, give it the Super Admin role without touching its password}';

    protected $description = 'Create a Super Admin account and print a one-time password';

    public function handle(SyncPermissions $sync): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']])->fails()) {
            $this->components->error('That is not a valid email address.');

            return self::INVALID;
        }

        $sync();

        $existing = User::withTrashed()->where('email', $email)->first();
        if ($existing !== null) {
            if (! $this->option('promote')) {
                $this->components->error('An account with that email already exists. Use --promote to make it a Super Admin.');

                return self::FAILURE;
            }

            $existing->assignRole(SystemRole::SuperAdmin->value);
            $this->components->info("{$email} is now a Super Admin. Their password was not changed.");

            return self::SUCCESS;
        }

        $password = self::generatePassword();
        $user = User::query()->create([
            'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        $user->assignRole(SystemRole::SuperAdmin->value);

        $this->components->info("Super Admin {$email} created.");
        $this->line('One-time password (shown once, change it after the first sign-in):');
        $this->line('  '.$password);
        $this->line('Signing in will ask you to set up an authenticator app.');

        return self::SUCCESS;
    }

    private static function generatePassword(): string
    {
        // 20 characters, guaranteed upper, lower and digit (matches Password::defaults()).
        do {
            $password = Str::password(20, symbols: false);
        } while (! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
