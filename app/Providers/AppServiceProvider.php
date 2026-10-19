<?php

namespace App\Providers;

use App\Domain\Access\Listeners\RecordAccessChanges;
use App\Domain\Access\Listeners\RecordAuthenticationActivity;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Policies\ActivityPolicy;
use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Access\Policies\UserPolicy;
use App\Domain\Translation\FallbackTranslator;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;
use Spatie\TranslationLoader\TranslationLoaderManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
        $this->bootAuthorization();
        $this->bootAuditing();
        $this->bootSafetyNets();
    }

    private function bootAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

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
