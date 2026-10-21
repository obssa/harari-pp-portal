<?php

use App\Domain\Api\Enums\ApiScope;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Middleware\ApiCacheHeaders;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\SetApiLanguage;
use Illuminate\Support\Facades\Route;

/*
| Public API, version 1 (ADR-0056): read-only, an API key per app, no
| session or cookies. The description is at /api/v1/openapi.json.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('openapi.json', OpenApiController::class)->name('openapi');

    $key = '[A-Za-z0-9]+(?:-[a-z0-9]+)*';
    $scoped = fn (ApiScope $scope) => Route::middleware([SetApiLanguage::class, ApiCacheHeaders::class, AuthenticateApiKey::class.':'.$scope->value]);

    $scoped(ApiScope::Content)->group(function () use ($key) {
        Route::get('posts', [ContentController::class, 'posts'])->name('posts.index');
        Route::get('posts/{key}', [ContentController::class, 'post'])->where('key', $key)->name('posts.show');
        Route::get('pages', [ContentController::class, 'pages'])->name('pages.index');
        Route::get('pages/{key}', [ContentController::class, 'showPage'])->where('key', $key)->name('pages.show');
    });

    $scoped(ApiScope::Events)->group(function () use ($key) {
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::get('events/{key}', [EventController::class, 'show'])->where('key', $key)->name('events.show');
    });

    $scoped(ApiScope::Documents)->group(function () use ($key) {
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{key}', [DocumentController::class, 'show'])->where('key', $key)->name('documents.show');
    });

    $scoped(ApiScope::Directory)->group(function () use ($key) {
        Route::get('offices', [DirectoryController::class, 'offices'])->name('offices.index');
        Route::get('offices/{key}', [DirectoryController::class, 'office'])->where('key', $key)->name('offices.show');
        Route::get('leaders', [DirectoryController::class, 'leaders'])->name('leaders.index');
        Route::get('leaders/{key}', [DirectoryController::class, 'leader'])->where('key', $key)->name('leaders.show');
    });

    $scoped(ApiScope::Projects)->group(function () use ($key) {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{key}', [ProjectController::class, 'show'])->where('key', $key)->name('projects.show');
    });

    $scoped(ApiScope::Search)->group(function () {
        Route::get('search', SearchController::class)->name('search');
    });
});
