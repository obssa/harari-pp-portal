<?php

namespace App\Domain\Press\Actions;

use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Press\Jobs\DeliverPressRelease;
use App\Domain\Press\Mail\PressReleaseMail;
use App\Domain\Press\Models\MediaContact;
use App\Domain\Press\Models\PressDistribution;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends a published press release, statement or speech to media contacts
 * (PLAN.md §5, ADR-0054): each gets it in the first of their languages the
 * text is published in, with a link to the full text, in the queue.
 */
final class DistributePressRelease
{
    public const TYPES = [PostType::PressRelease, PostType::Statement, PostType::Speech];

    public static function canSend(Post $post): bool
    {
        return in_array($post->type, self::TYPES, true) && self::live($post) !== [];
    }

    /**
     * @param  list<int>|null  $contactIds  null for every contact who takes press releases
     */
    public function send(Post $post, ?array $contactIds, User $actor): int
    {
        abort_unless($actor->can('media_contacts.manage'), 403);
        if (! self::canSend($post)) {
            throw ValidationException::withMessages(['post' => __('press.validation.not_publishable')]);
        }
        $contacts = MediaContact::query()->active()
            ->when($contactIds !== null, fn ($q) => $q->whereKey($contactIds))
            ->get();
        if ($contacts->isEmpty()) {
            throw ValidationException::withMessages(['contacts' => __('press.validation.no_contacts')]);
        }

        $ids = [];
        foreach ($contacts as $contact) {
            $ids[] = PressDistribution::query()->create([
                'post_id' => $post->id, 'media_contact_id' => $contact->id, 'locale' => self::languageFor($post, $contact),
                'status' => PressDistribution::QUEUED, 'sent_by' => $actor->id,
            ])->id;
        }
        activity('press')->performedOn($post)->causedBy($actor)->event('distributed')->withProperties(['contacts' => count($ids)])->log('Press release sent to media');
        DeliverPressRelease::dispatch($ids);

        return count($ids);
    }

    /** One email, from the queue. */
    public function deliver(PressDistribution $distribution): void
    {
        $contact = $distribution->contact;
        $translation = self::live($distribution->post)[$distribution->locale] ?? null;
        if ($contact->opted_out_at !== null || $translation === null) {
            $distribution->forceFill(['status' => PressDistribution::SKIPPED])->save();

            return;
        }
        try {
            Mail::to($contact->email)->locale($distribution->locale)->send(new PressReleaseMail($translation, $contact));
            $distribution->forceFill(['status' => PressDistribution::SENT, 'sent_at' => CarbonImmutable::now(), 'error' => null])->save();
        } catch (Throwable $e) {
            $distribution->forceFill(['status' => PressDistribution::FAILED, 'error' => mb_substr($e->getMessage(), 0, 190)])->save();
        }
    }

    /** The first of the contact's languages the text is published in, else the closest published language. */
    public static function languageFor(Post $post, MediaContact $contact): string
    {
        $live = self::live($post);
        foreach ($contact->languages as $language) {
            foreach (Locales::fallbackChain($language) as $candidate) {
                if (isset($live[$candidate])) {
                    return $candidate;
                }
            }
        }

        return (string) array_key_first($live);
    }

    /**
     * @return array<string, PostTranslation>
     */
    private static function live(Post $post): array
    {
        $live = [];
        foreach ($post->translations as $translation) {
            /** @var PostTranslation $translation */
            if ($translation->isLive()) {
                $live[$translation->locale] = $translation;
            }
        }

        return $live;
    }
}
