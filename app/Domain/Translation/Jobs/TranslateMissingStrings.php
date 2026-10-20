<?php

namespace App\Domain\Translation\Jobs;

use App\Domain\Notifications\Support\Recipients;
use App\Domain\Translation\Actions\EditUiTranslation;
use App\Domain\Translation\Notifications\MachineTranslationNotice;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Models\User;
use App\Services\Translation\MachineTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Validation\ValidationException;

/**
 * Machine-translates every interface string still missing in one language
 * (ADR-0035), from English, marked for review. Runs in the queue: it can
 * take a while and sends texts to the provider. The person who started it
 * is told when it is done (ADR-0036).
 */
final class TranslateMissingStrings implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public readonly string $locale,
        public readonly int $userId,
    ) {}

    public function handle(TranslationCatalog $catalog, MachineTranslator $translator, EditUiTranslation $edit): void
    {
        $actor = User::query()->find($this->userId);
        if (! $actor instanceof User || ! $translator->enabledFor($this->locale)) {
            return;
        }

        $english = $catalog->values('en');
        $texts = [];
        foreach ($catalog->missing($this->locale) as $key) {
            $texts[$key] = (string) ($english[$key] ?? '');
        }

        $translated = 0;
        foreach ($translator->translate($texts, 'en', $this->locale) as $key => $translation) {
            if ($translation === null || $translation === '') {
                continue;
            }
            try {
                $edit->handle((string) $key, $this->locale, $translation, $actor, 'machine');
                $translated++;
            } catch (ValidationException) {
                // A text the rules refuse stays missing for a person to translate.
            }
        }

        Recipients::notify([$actor], new MachineTranslationNotice($this->locale, $translated, count($texts) - $translated));
    }
}
