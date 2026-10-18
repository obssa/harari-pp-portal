<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the app and run against MySQL (harari_test) inside a
| transaction-safe refresh. Unit tests also get the app container so services
| can resolve config and translations, but never touch the database.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');
