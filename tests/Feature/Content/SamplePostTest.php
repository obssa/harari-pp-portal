<?php

use App\Console\Commands\SamplePostCommand;
use App\Domain\Content\Models\Post;

it('publishes one labelled sample story in every language, once, and removes it', function () {
    $this->artisan('portal:sample-post')->assertSuccessful();
    $this->artisan('portal:sample-post')->expectsOutputToContain('already published')->assertSuccessful();

    $post = Post::query()->with('translations')->sole();
    $am = $post->translation('am', fallback: false);
    $om = $post->translation('om', fallback: false);

    $this->get('/en/news/'.SamplePostCommand::SLUG)->assertOk()->assertSee('Sample: a story in four languages')->assertSee('not an official statement');
    $this->get('/am/news/'.$am?->slug)->assertOk()->assertSee('ናሙና፦ በአራት ቋንቋዎች የቀረበ ዜና');
    $this->get('/om/news/'.$om?->slug)->assertOk()->assertSee('Fakkeenya: oduu afaanota afuriin');
    $this->get('/har/news')->assertOk()->assertSee('ናሙና፦ በአራት ቋንቋዎች የቀረበ ዜና')->assertSee('lang="am"', escape: false);
    expect($post->featuredImage?->hasAltFor('om'))->toBeTrue();

    $post->delete();
    $this->artisan('portal:sample-post', ['--remove' => true])->expectsOutputToContain('removed')->assertSuccessful();
    expect(Post::withTrashed()->count())->toBe(0);
});

it('never adds sample content to production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('portal:sample-post')->assertFailed();
    expect(Post::query()->count())->toBe(0);
});
