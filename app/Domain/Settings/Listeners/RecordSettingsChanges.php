<?php

namespace App\Domain\Settings\Listeners;

use Spatie\LaravelSettings\Events\SavingSettings;

/**
 * Settings history (PLAN.md §7 Configuration: who changed what). Secret
 * values are never written to the audit log; only the fact they changed.
 */
final class RecordSettingsChanges
{
    public function handle(SavingSettings $event): void
    {
        $settings = $event->settings;
        $secrets = $settings::encrypted();

        $old = [];
        $new = [];
        foreach ($event->properties as $name => $value) {
            $before = $event->originalValues?->get($name);
            if ($before == $value) {
                continue;
            }

            if (in_array($name, $secrets, true)) {
                $old[$name] = $before === null ? '∅' : '••••';
                $new[$name] = $value === null ? '∅' : '•••• (changed)';

                continue;
            }

            $old[$name] = $before;
            $new[$name] = $value;
        }

        if ($new === []) {
            return;
        }

        activity('settings')
            ->event('updated')
            ->withProperties(['group' => $settings::group(), 'old' => $old, 'attributes' => $new])
            ->log('settings.'.$settings::group());
    }
}
