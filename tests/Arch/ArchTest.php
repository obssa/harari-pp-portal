<?php

arch('no debugging helpers are left in the code')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('env() is only read in config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('the application follows PHP hygiene rules')
    ->preset()->php();

arch('the application avoids insecure functions')
    ->preset()->security()
    ->ignoring('App\Console');

arch('middleware classes are final and live in the middleware namespace')
    ->expect('App\Http\Middleware')
    ->toBeClasses()
    ->toBeFinal();
