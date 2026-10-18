<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

it('runs the admin panel with strict (deny-by-default) authorization', function () {
    expect(Filament::getPanel('admin')->isAuthorizationStrict())->toBeTrue();
});

it('has a policy for the model of every admin resource', function () {
    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)->not->toBeEmpty();
    foreach ($resources as $resource) {
        expect(Gate::getPolicyFor($resource::getModel()))
            ->not->toBeNull("{$resource} has no policy for ".$resource::getModel());
    }
});
