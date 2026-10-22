<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\RequireMfaForPrivilegedRoles;
use App\Http\Middleware\RestrictAdminByIp;
use App\Http\Middleware\SetAdminLocale;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * A public file with its modification time, so browsers fetch it again after a deploy.
     */
    private static function asset(string $path): string
    {
        $file = public_path($path);

        return '/'.$path.(is_file($file) ? '?v='.filemtime($file) : '');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn (): string => __('site.name'))
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->colors([
                // Prosperity Blue taken from the party's logo (ADR-0062).
                'primary' => Color::hex('#0B6CA8'),
            ])
            ->favicon('/favicon-96.png')
            // ADR-0064: the public site's brand in the admin; fonts served from this site.
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->font('Inter Variable', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): HtmlString => new HtmlString(
                '<link rel="stylesheet" href="'.e(self::asset('brand/admin.css')).'">'
            ))
            // Authenticator-app MFA for everyone who wants it; required for the
            // roles in config('portal.mfa_required_roles') by our middleware.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('Harari PP')],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(RequireMfaForPrivilegedRoles::class)
            // Deny by default: a resource without a policy method fails closed.
            ->strictAuthorization()
            // In-app notifications from the editorial workflow (ADR-0013).
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            // Initials drawn locally: no staff names sent to a third party, CSP-safe.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // ADR-0038: optional allow-list of addresses, also for the sign-in page and Livewire requests.
            ->middleware([RestrictAdminByIp::class], isPersistent: true)
            // Persistent: also applies to Livewire requests made from admin pages.
            ->authMiddleware([
                Authenticate::class,
                SetAdminLocale::class,
            ], isPersistent: true);
    }
}
