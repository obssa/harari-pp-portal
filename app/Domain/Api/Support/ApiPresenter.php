<?php

namespace App\Domain\Api\Support;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\CategoryTranslation;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Models\Tag;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Media\Models\Media;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\LeaderTranslation;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Models\OfficeTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectTranslation;
use App\Domain\Translation\Support\Locales;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * What the public API (ADR-0056) gives of each kind of record: the fields
 * the public pages show, in the reader's language or the one it falls back
 * to (`lang` says which), with the record's address on the site. Dates are
 * ISO 8601; the main date also comes written in the Ethiopian calendar.
 */
final class ApiPresenter
{
    public function __construct(private readonly EthiopianCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function post(Post $post, PostTranslation $t, bool $full = false): array
    {
        $data = [
            'id' => $post->id,
            'type' => $post->type->value,
            'type_label' => $post->type->label(),
            'lang' => $t->locale,
            'slug' => $t->slug,
            'title' => $t->title,
            'kicker' => $t->kicker,
            'excerpt' => $t->excerpt,
            'published_at' => $this->time($t->published_at),
            'published_ethiopian' => $this->ethiopian($t->published_at, $t->locale),
            'updated_at' => $this->time($t->getAttribute('updated_at')),
            'reading_time' => $t->reading_time,
            'featured' => $post->is_featured,
            'sticky' => $post->is_sticky,
            'image' => $this->image($post->featuredImage, 'landscape', $t->locale),
            'categories' => $post->categories->map(fn (Category $category): ?array => $this->term($category, $t->locale))->filter()->values()->all(),
            'url' => route('news.show', ['locale' => $t->locale, 'slug' => $t->slug]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $data['tags'] = $post->tags->map(fn (Tag $tag): ?array => $this->term($tag, $t->locale))->filter()->values()->all();
            $data['alternates'] = $this->alternates($post, 'news.show');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null null when the page has no address in its language
     */
    public function page(Page $page, PageTranslation $t, bool $full = false): ?array
    {
        $path = $page->path($t->locale);
        if ($path === null) {
            return null;
        }

        $data = [
            'id' => $page->id,
            'parent_id' => $page->parent_id,
            'lang' => $t->locale,
            'slug' => $t->slug,
            'path' => $path,
            'title' => $t->title,
            'excerpt' => $t->excerpt,
            'updated_at' => $this->time($t->getAttribute('updated_at')),
            'image' => $this->image($page->featuredImage, 'landscape', $t->locale),
            'url' => route('page.show', ['locale' => $t->locale, 'path' => $path]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $alternates = [];
            foreach (Locales::all() as $locale) {
                $other = $page->translation($locale, fallback: false);
                $otherPath = $page->path($locale);
                if ($other instanceof PageTranslation && $other->isLive() && $otherPath !== null) {
                    $alternates[$locale] = route('page.show', ['locale' => $locale, 'path' => $otherPath]);
                }
            }
            $data['alternates'] = $alternates;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(Event $event, EventTranslation $t, bool $full = false): array
    {
        $data = [
            'id' => $event->id,
            'lang' => $t->locale,
            'slug' => $t->slug,
            'title' => $t->title,
            'summary' => $t->summary,
            'starts_at' => $this->time($event->starts_at),
            'ends_at' => $this->time($event->ends_at),
            'all_day' => $event->all_day,
            'starts_ethiopian' => $this->ethiopian($event->starts_at, $t->locale),
            'venue' => $t->venue,
            'address' => $t->address,
            'online_url' => $event->online_url,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'image' => $this->image($event->cover, 'landscape', $t->locale),
            'office' => $this->office($event->office, $t->locale),
            'url' => route('events.show', ['locale' => $t->locale, 'slug' => $t->slug]),
            'calendar_url' => route('events.ics', ['locale' => $t->locale, 'slug' => $t->slug]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $data['agenda'] = $t->agenda ?? [];
            $data['registration'] = [
                'state' => $event->registrationState(),
                'places_left' => $event->registration_enabled ? $event->placesLeft() : null,
                'closes_at' => $event->registration_enabled ? $this->time($event->registrationClosesAt()) : null,
                'url' => $event->registration_enabled ? route('events.register', ['locale' => $t->locale, 'slug' => $t->slug]) : null,
            ];
            $data['speakers'] = $event->speakers
                ->map(function (Leader $leader) use ($t): ?array {
                    $shown = PublicDirectory::shown($leader, $t->locale);

                    return $shown instanceof LeaderTranslation ? $this->leader($leader, $shown) : null;
                })
                ->filter()->values()->all();
            $data['alternates'] = $this->alternates($event, 'events.show');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(Document $document, DocumentTranslation $t, bool $full = false): array
    {
        $file = $document->fileFor($t->locale);
        $media = $file?->file;

        $data = [
            'id' => $document->id,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'lang' => $t->locale,
            'slug' => $t->slug,
            'title' => $t->title,
            'summary' => $t->summary,
            'year' => $document->year,
            'issued_on' => $document->issued_on?->toDateString(),
            'deadline_at' => $this->time($document->deadline_at),
            'closed' => $document->type->isNotice() ? $document->isClosed() : null,
            'file' => $file !== null && $media !== null ? [
                'lang' => $file->locale,
                'version' => $file->version,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'download_url' => route('documents.download', ['locale' => $t->locale, 'slug' => $t->slug]),
            ] : null,
            'office' => $this->office($document->office, $t->locale),
            'url' => route('documents.show', ['locale' => $t->locale, 'slug' => $t->slug]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $data['alternates'] = $this->alternates($document, 'documents.show');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function officeRecord(Office $office, OfficeTranslation $t, bool $full = false): array
    {
        $data = [
            'id' => $office->id,
            'type' => $office->type->value,
            'type_label' => $office->type->label(),
            'parent_id' => $office->parent_id,
            'lang' => $t->locale,
            'slug' => $t->slug,
            'name' => $t->name,
            'summary' => $t->summary,
            'address' => $t->address,
            'hours' => $t->hours,
            'phone' => $office->phone,
            'email' => $office->email,
            'latitude' => $office->latitude,
            'longitude' => $office->longitude,
            'image' => $this->image($office->coverImage, 'landscape', $t->locale),
            'url' => route('offices.show', ['locale' => $t->locale, 'slug' => $t->slug]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $data['head_leader_id'] = $office->head_leader_id;
            $data['alternates'] = $this->alternates($office, 'offices.show');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function leader(Leader $leader, LeaderTranslation $t, bool $full = false): array
    {
        $data = [
            'id' => $leader->id,
            'body' => $leader->body->value,
            'body_label' => $leader->body->label(),
            'office_id' => $leader->office_id,
            'league_id' => $leader->league_id,
            'rank' => $leader->rank,
            'lang' => $t->locale,
            'slug' => $t->slug,
            'name' => $t->name,
            'position' => $t->position,
            'summary' => $t->summary,
            'term_start' => $leader->term_start?->toDateString(),
            'term_end' => $leader->term_end?->toDateString(),
            'portrait' => $this->image($leader->portrait, 'square', $t->locale),
            'url' => route('leaders.show', ['locale' => $t->locale, 'slug' => $t->slug]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $data['phone'] = $leader->phone;
            $data['email'] = $leader->email;
            $data['socials'] = $leader->socialLinks();
            $data['alternates'] = $this->alternates($leader, 'leaders.show');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function project(Project $project, ProjectTranslation $t, bool $full = false): array
    {
        $data = [
            'id' => $project->id,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'sector' => $project->sector !== null ? $this->term($project->sector, $t->locale) : null,
            'office_id' => $project->office_id,
            'lang' => $t->locale,
            'slug' => $t->slug,
            'title' => $t->title,
            'summary' => $t->summary,
            'location' => $t->location,
            'starts_on' => $project->starts_on?->toDateString(),
            'ends_on' => $project->ends_on?->toDateString(),
            'progress' => $project->progress,
            'budget' => $project->publicBudget(),
            'featured' => $project->featured,
            'latitude' => $project->latitude,
            'longitude' => $project->longitude,
            'image' => $this->image($project->cover, 'landscape', $t->locale),
            'url' => route('projects.show', ['locale' => $t->locale, 'slug' => $t->slug]),
        ];

        if ($full) {
            $data['body_html'] = BodyRenderer::toHtml($t->body);
            $data['alternates'] = $this->alternates($project, 'projects.show');
        }

        return $data;
    }

    /**
     * A member's own details and card (member API, ADR-0057).
     *
     * @return array<string, mixed>
     */
    public function member(Member $member, string $locale): array
    {
        $due = $member->renewal_due_on;

        return [
            'id' => $member->id,
            'member_no' => $member->member_no,
            'name' => $member->name,
            'status' => $member->status->value,
            'status_label' => $member->status->label(),
            'woreda' => $member->woreda !== null ? ['id' => $member->woreda->id, 'name' => $member->woreda->name($locale)] : null,
            'kebele' => $member->kebele !== null ? ['id' => $member->kebele->id, 'name' => $member->kebele->name($locale)] : null,
            'phone' => $member->phone,
            'email' => $member->email,
            'joined_on' => $member->joined_on->toDateString(),
            'renewal_due_on' => $due?->toDateString(),
            'renewal_due_soon' => $due !== null && $due->lessThanOrEqualTo(CarbonImmutable::today()->addDays((int) config('portal.membership_reminder_days'))),
            'card' => [
                'number' => $member->member_no,
                'valid' => $member->canUsePortal() && $due?->isFuture() !== false,
                'valid_until' => $due?->toDateString(),
                'valid_until_ethiopian' => $this->ethiopian($due, $locale),
                'verify_url' => $member->cardUrl($locale),
            ],
        ];
    }

    /**
     * One of the member's event registrations, with its ticket.
     *
     * @return array<string, mixed>
     */
    public function registration(EventRegistration $registration, string $locale): array
    {
        $event = $registration->event;
        $shown = PublicDirectory::shown($event, $locale);

        return [
            'id' => $registration->id,
            'status' => $registration->status->value,
            'status_label' => $registration->status->label(),
            'registered_at' => $this->time($registration->created_at),
            'checked_in_at' => $this->time($registration->checked_in_at),
            'cancelled_at' => $this->time($registration->cancelled_at),
            'ticket_url' => route('events.ticket', ['locale' => $registration->locale, 'token' => $registration->token]),
            'event' => [
                'id' => $event->id,
                'title' => $shown instanceof EventTranslation ? $shown->title : $event->title($locale),
                'starts_at' => $this->time($event->starts_at),
                'ends_at' => $this->time($event->ends_at),
                'all_day' => $event->all_day,
                'venue' => $shown instanceof EventTranslation ? $shown->venue : null,
                'url' => $shown instanceof EventTranslation ? route('events.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) : null,
            ],
        ];
    }

    /**
     * The largest ready copy of a photo and the other widths, with its alt text.
     *
     * @return array{url: string, alt: string, width: ?int, height: ?int, sizes: array<int, string>}|null
     */
    public function image(?Media $media, string $preset, string $locale): ?array
    {
        if ($media === null || ! $media->isImage()) {
            return null;
        }

        $sizes = [];
        foreach ((array) config("portal.media.presets.{$preset}.widths", []) as $width) {
            if ($media->hasGeneratedConversion("{$preset}-{$width}")) {
                $sizes[(int) $width] = $media->getUrl("{$preset}-{$width}");
            }
        }
        ksort($sizes);

        return [
            'url' => $sizes !== [] ? (string) end($sizes) : $media->getUrl(),
            'alt' => $media->altFor($locale),
            'width' => $media->width,
            'height' => $media->height,
            'sizes' => $sizes,
        ];
    }

    /**
     * @return array{id: string, slug: ?string, name: string}|null
     */
    private function term(Category|Tag $term, string $locale): ?array
    {
        $translation = $term->translation($locale);
        if ($translation === null) {
            return null;
        }

        return [
            'id' => (string) $term->getKey(),
            'slug' => $translation->slug,
            'name' => $translation instanceof CategoryTranslation ? $translation->name : (string) $translation->getAttribute('name'),
        ];
    }

    /**
     * @return array{id: string, name: string, url: string}|null
     */
    private function office(?Office $office, string $locale): ?array
    {
        $shown = $office !== null ? PublicDirectory::shown($office, $locale) : null;
        if ($office === null || ! $shown instanceof OfficeTranslation) {
            return null;
        }

        return [
            'id' => $office->id,
            'name' => $shown->name,
            'url' => route('offices.show', ['locale' => $shown->locale, 'slug' => $shown->slug]),
        ];
    }

    /**
     * The record's address in every language it is live in.
     *
     * @return array<string, string>
     */
    private function alternates(Model&TranslatableModel $record, string $route): array
    {
        $urls = [];
        foreach (Locales::all() as $locale) {
            $translation = $record->translation($locale, fallback: false);
            if ($translation !== null && PublicDirectory::live($translation)) {
                $urls[$locale] = route($route, ['locale' => $locale, 'slug' => $translation->slug]);
            }
        }

        return $urls;
    }

    private function time(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }

    private function ethiopian(?DateTimeInterface $value, string $locale): ?string
    {
        return $value !== null ? $this->calendar->formatEthiopian($value, $locale) : null;
    }
}
