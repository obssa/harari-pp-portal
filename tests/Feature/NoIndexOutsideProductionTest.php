<?php

it('tells search engines not to index non-production environments', function (string $env) {
    $this->app['env'] = $env;

    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['staging', 'local', 'testing']);

it('lets search engines index production', function () {
    $this->app['env'] = 'production';

    $this->get('/')->assertHeaderMissing('X-Robots-Tag');
});

it('applies to the health endpoint too', function () {
    $this->get('/up')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
