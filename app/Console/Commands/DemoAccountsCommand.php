<?php

namespace App\Console\Commands;

use App\Domain\Access\Actions\SyncPermissions;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One clearly labelled sample account per role, for testing permissions on
 * staging (PLAN.md §13: every role tested with a dedicated test account).
 * Refuses to run unless PORTAL_DEMO_ACCOUNTS=true and APP_ENV is not production.
 */
final class DemoAccountsCommand extends Command
{
    public const DOMAIN = 'demo.hararipp.test';

    protected $signature = 'portal:demo-accounts {--remove : Delete the sample accounts instead}';

    protected $description = 'Create or remove one sample account per role (staging only)';

    public function handle(SyncPermissions $sync): int
    {
        if (app()->isProduction() || ! config('portal.demo_accounts')) {
            $this->components->error('Sample accounts are disabled here (needs PORTAL_DEMO_ACCOUNTS=true outside production).');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            // One at a time: the permission package reloads roles while syncing.
            $ids = User::withTrashed()->where('email', 'like', '%@'.self::DOMAIN)->pluck('id');
            foreach ($ids as $id) {
                $user = User::withTrashed()->findOrFail($id);
                $user->syncRoles([]);
                $user->forceDelete();
            }
            $count = $ids->count();
            $this->components->info("Removed {$count} sample accounts.");

            return self::SUCCESS;
        }

        $sync();
        $password = Str::password(20, symbols: false).'Aa1';
        $rows = [];

        foreach (SystemRole::cases() as $role) {
            $email = str_replace('_', '-', $role->value).'@'.self::DOMAIN;
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->fill(['name' => __('access.demo_name', ['role' => $role->label()]), 'password' => $password]);
            $user->email_verified_at = now();
            $user->deleted_at = null;
            $user->save();
            $user->syncRoles([$role->value]);

            if ($role === SystemRole::Translator) {
                $user->assignScopedRole($role, ScopeType::Language, 'har');
            }

            $rows[] = [$role->label(), $email, $role->requiresMfa() ? 'yes' : 'no'];
        }

        $this->table(['Role', 'Email', 'Must set up 2FA'], $rows);
        $this->line('Shared one-time password for all sample accounts (shown once):');
        $this->line('  '.$password);

        return self::SUCCESS;
    }
}
