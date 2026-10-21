<?php

namespace App\Providers;

use App\Domain\Access\Listeners\RecordAccessChanges;
use App\Domain\Access\Listeners\RecordAuthenticationActivity;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Policies\ActivityPolicy;
use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Access\Policies\UserPolicy;
use App\Domain\Analytics\Models\AnalyticsReport;
use App\Domain\Analytics\Policies\AnalyticsReportPolicy;
use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Content\Listeners\NotifyAboutTransition;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Redirect;
use App\Domain\Content\Models\Revision;
use App\Domain\Content\Models\Tag;
use App\Domain\Content\Policies\CommentPolicy;
use App\Domain\Content\Policies\MenuPolicy;
use App\Domain\Content\Policies\PagePolicy;
use App\Domain\Content\Policies\PostPolicy;
use App\Domain\Content\Policies\RedirectPolicy;
use App\Domain\Content\Policies\RevisionPolicy;
use App\Domain\Content\Policies\TaxonomyPolicy;
use App\Domain\Dashboard\Models\Announcement;
use App\Domain\Dashboard\Policies\AnnouncementPolicy;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Policies\DocumentPolicy;
use App\Domain\Documents\Policies\DocumentVersionPolicy;
use App\Domain\Events\Models\Event as PortalEvent;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Policies\EventPolicy;
use App\Domain\Events\Policies\EventRegistrationPolicy;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\SubmissionNote;
use App\Domain\Forms\Policies\FormPolicy;
use App\Domain\Forms\Policies\FormSubmissionPolicy;
use App\Domain\Forms\Policies\SubmissionNotePolicy;
use App\Domain\Health\HealthChecks;
use App\Domain\Health\Listeners\NotifyAboutBackupProblems;
use App\Domain\Health\Listeners\NotifyAboutFailingCheck;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Homepage\Policies\HomepageSectionPolicy;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Import\Policies\ContentSourcePolicy;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Domain\Media\Models\Video;
use App\Domain\Media\Policies\GalleryPolicy;
use App\Domain\Media\Policies\MediaFolderPolicy;
use App\Domain\Media\Policies\MediaPolicy;
use App\Domain\Media\Policies\VideoPolicy;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberImport;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Policies\MemberImportPolicy;
use App\Domain\Membership\Policies\MemberPolicy;
use App\Domain\Membership\Policies\MembershipApplicationPolicy;
use App\Domain\Membership\Policies\PersonNotePolicy;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Policies\LeaderPolicy;
use App\Domain\Organisation\Policies\LeaguePolicy;
use App\Domain\Organisation\Policies\OfficePolicy;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Policies\PrivacyRequestPolicy;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Statistic;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Projects\Policies\StatisticPolicy;
use App\Domain\Search\Engines\WordMatchEngine;
use App\Domain\Search\Models\SearchTerm;
use App\Domain\Search\Policies\SearchTermPolicy;
use App\Domain\Settings\Listeners\RecordSettingsChanges;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Policies\SocialPostPolicy;
use App\Domain\Social\Policies\SocialSourcePolicy;
use App\Domain\Translation\FallbackTranslator;
use App\Domain\Translation\Models\GlossaryTerm;
use App\Domain\Translation\Models\TranslationImport;
use App\Domain\Translation\Policies\GlossaryTermPolicy;
use App\Domain\Translation\Policies\TranslationImportPolicy;
use App\Domain\Translation\Support\Locales;
use App\Domain\Webhooks\Listeners\QueueWebhooks;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Policies\WebhookPolicy;
use App\Models\User;
use App\Services\Cdn\PublicContentChanges;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Settings\SiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Rules\Password;
use Laravel\Scout\EngineManager;
use Spatie\Activitylog\Models\Activity;
use Spatie\Health\Events\CheckEndedEvent;
use Spatie\LaravelSettings\Events\SavingSettings;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\TranslationLoader\TranslationLoaderManager;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ADR-0025: text messages go to the log until an SMS provider is chosen.
        $this->app->bind(SmsGateway::class, fn (): SmsGateway => new LogSmsGateway);

        // spatie/laravel-translation-loader builds its loader from lang/ only, which
        // drops Laravel's built-in English framework strings (validation, auth,
        // passwords, pagination). Rebuild it with both paths, framework first, as
        // Laravel's own TranslationServiceProvider does.
        $this->app->extend('translation.loader', function (FileLoader $loader, Application $app): TranslationLoaderManager {
            $framework = dirname((string) (new \ReflectionClass(TranslationServiceProvider::class))->getFileName()).'/lang';

            return new TranslationLoaderManager($app['files'], [$framework, $app->langPath()]);
        });

        // The portal's fallback chain (har → am → en, om → en) on top of the
        // file + database loader from spatie/laravel-translation-loader.
        $this->app->extend('translator', function (Translator $translator): FallbackTranslator {
            $portal = new FallbackTranslator($translator->getLoader(), $translator->getLocale());
            $portal->setFallback($translator->getFallback());

            return $portal;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootSearch();
        $this->bootAuthorization();
        $this->bootAuditing();
        $this->bootLocalization();
        $this->bootMedia();
        $this->bootSafetyNets();
        $this->bootHealth();
        $this->bootEdgeCache();
    }

    /** ADR-0039: a change to anything public asks Cloudflare to drop this site's cached pages. */
    private function bootEdgeCache(): void
    {
        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *'], [PublicContentChanges::class, 'model']);
        Event::listen(SettingsSaved::class, [PublicContentChanges::class, 'settings']);
    }

    /** ADR-0037: the health checks, and notices when a check or a backup fails. */
    private function bootHealth(): void
    {
        HealthChecks::register();
        Event::listen(CheckEndedEvent::class, NotifyAboutFailingCheck::class);
        Event::subscribe(NotifyAboutBackupProblems::class);
    }

    private function bootMedia(): void
    {
        // Public listings use a translated previous/next pagination.
        Paginator::defaultView('pagination.simple');
        Paginator::defaultSimpleView('pagination.simple');

        // Livewire's temporary uploads (admin upload field): the largest file
        // the media library accepts, and no server-side preview of SVGs, which
        // are only safe once sanitised (ADR-0009).
        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.MediaKind::largestUploadKb()],
            'livewire.temporary_file_upload.preview_mimes' => [
                'png', 'gif', 'jpg', 'jpeg', 'webp', 'mp4', 'webm', 'mp3', 'm4a',
            ],
        ]);
    }

    private function bootSearch(): void
    {
        // ADR-0020: the database driver matches every word (MySQL full-text skips short Amharic words).
        $this->app->make(EngineManager::class)->extend('database', fn (): WordMatchEngine => new WordMatchEngine);

        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()));

        // ADR-0044: the membership steps post several times; codes have their own limits.
        RateLimiter::for('membership', fn (Request $request): array => [
            Limit::perMinute(20)->by('membership-minute:'.$request->ip()),
            Limit::perHour(60)->by('membership-hour:'.$request->ip()),
        ]);

        // ADR-0040: page view beacons, generous but bounded per address.
        RateLimiter::for('beacon', fn (Request $request): Limit => Limit::perMinute(120)->by('beacon:'.$request->ip()));

        // Public forms (PLAN.md §11): a few tries a minute, a few dozen an hour, per address.
        RateLimiter::for('forms', fn (Request $request): array => [
            Limit::perMinute(5)->by('forms-minute:'.$request->ip()),
            Limit::perHour(30)->by('forms-hour:'.$request->ip()),
        ]);
    }

    private function bootLocalization(): void
    {
        // The admin setting decides the default language; config is the fallback
        // while settings are unavailable (e.g. before migrations run).
        Locales::resolveDefaultUsing(function (): ?string {
            try {
                return app(SiteSettings::class)->default_locale;
            } catch (Throwable) {
                return null;
            }
        });

        Blade::directive('ethiopianDate', fn (string $expression): string => "<?php echo e(app(\\App\\Services\\Calendar\\EthiopianCalendar::class)->formatEthiopian({$expression})); ?>");
    }

    private function bootAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(MediaFolder::class, MediaFolderPolicy::class);
        Gate::policy(Category::class, TaxonomyPolicy::class);
        Gate::policy(Tag::class, TaxonomyPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Revision::class, RevisionPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
        Gate::policy(Redirect::class, RedirectPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(Office::class, OfficePolicy::class);
        Gate::policy(Leader::class, LeaderPolicy::class);
        Gate::policy(League::class, LeaguePolicy::class);
        Gate::policy(HomepageSection::class, HomepageSectionPolicy::class);
        Gate::policy(SearchTerm::class, SearchTermPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(ContentSource::class, ContentSourcePolicy::class);
        Gate::policy(Gallery::class, GalleryPolicy::class);
        Gate::policy(Video::class, VideoPolicy::class);
        Gate::policy(PortalEvent::class, EventPolicy::class);
        Gate::policy(EventRegistration::class, EventRegistrationPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentVersion::class, DocumentVersionPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Statistic::class, StatisticPolicy::class);
        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);
        Gate::policy(SubmissionNote::class, SubmissionNotePolicy::class);
        Gate::policy(SocialSource::class, SocialSourcePolicy::class);
        Gate::policy(SocialPost::class, SocialPostPolicy::class);
        Gate::policy(TranslationImport::class, TranslationImportPolicy::class);
        Gate::policy(GlossaryTerm::class, GlossaryTermPolicy::class);
        Gate::policy(AnalyticsReport::class, AnalyticsReportPolicy::class);
        Gate::policy(PrivacyRequest::class, PrivacyRequestPolicy::class);
        Gate::policy(MembershipApplication::class, MembershipApplicationPolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(PersonNote::class, PersonNotePolicy::class);
        Gate::policy(MemberImport::class, MemberImportPolicy::class);

        // Super Admins hold every permission (PLAN.md §8). Only permission
        // names (`module.action`) are short-circuited, so policy rules such as
        // "nobody deletes their own account" still apply to them.
        Gate::before(function (mixed $user, string $ability): ?bool {
            if ($user instanceof User && str_contains($ability, '.') && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->mixedCase()->numbers();

            return $this->app->environment('production', 'staging') ? $rule->uncompromised() : $rule;
        });
    }

    private function bootAuditing(): void
    {
        Event::subscribe(RecordAccessChanges::class);
        Event::subscribe(RecordAuthenticationActivity::class);
        Event::listen(SavingSettings::class, RecordSettingsChanges::class);
        Event::listen(ContentTransitioned::class, NotifyAboutTransition::class);
        Event::listen(ContentTransitioned::class, QueueWebhooks::class);

        // Every audit entry made during a web request records where it came from.
        Activity::saving(function (Activity $activity): void {
            $ip = request()->ip();
            if ($ip === null) {
                return; // console: scheduler, queue workers, artisan commands
            }

            $activity->properties = $activity->properties->merge([
                'ip' => $ip,
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        });
    }

    private function bootSafetyNets(): void
    {
        // N+1 queries fail loudly in development and tests, and are logged on staging.
        Model::preventLazyLoading(! $this->app->isProduction());

        if ($this->app->environment('staging')) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Lazy loading violation', ['model' => $model::class, 'relation' => $relation]);
            });
        }
    }
}
