<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('hashes passwords when they are set', function () {
    $user = User::factory()->create(['password' => 'correct horse battery staple']);

    expect($user->getRawOriginal('password'))
        ->not->toBe('correct horse battery staple')
        ->and(Hash::check('correct horse battery staple', $user->password))->toBeTrue();
});

it('never exposes the password or remember token when serialised', function () {
    $user = User::factory()->create();

    expect($user->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('casts email verification time to a date', function () {
    $user = User::factory()->create(['email_verified_at' => '2026-09-11 10:00:00']);

    expect($user->email_verified_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('stores Ethiopic names without loss', function () {
    $user = User::factory()->create(['name' => 'አበበ በቀለ']);

    expect($user->fresh()->name)->toBe('አበበ በቀለ');
});
