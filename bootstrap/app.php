<?php

use App\Domain\Access\Actions\RecordAccessDenied;
use App\Domain\Content\Actions\FollowRedirect;
use App\Domain\Translation\Support\Locales;
use App\Http\Middleware\NoIndexOutsideProduction;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(NoIndexOutsideProduction::class);
        $middleware->append(SecurityHeaders::class);

        // The language choice is not sensitive and must stay readable without the app key.
        $middleware->encryptCookies(except: [Locales::COOKIE]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ADR-0038: errors go to Sentry once SENTRY_LARAVEL_DSN is set; without it nothing is sent.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API errors (ADR-0056) answer in JSON with a message in the language asked for.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $status === 404 ? __('api.error.not_found') : ($e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$status] ?? 'Error'));

            return response()->json(['message' => $message], $status, [...$e->getHeaders(), 'Cache-Control' => 'no-store']);
        });

        // Moved pages (ADR-0015): a known old address redirects instead of 404.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->isMethod('GET') ? FollowRedirect::for($request) : null;
        });

        // Every refused request lands in the audit log, then renders as usual.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 403) {
                app(RecordAccessDenied::class)($request);
            }

            return null;
        });
    })->create();
