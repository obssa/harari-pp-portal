<?php

namespace Tests;

use App\Domain\Sites\Models\Site;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests (RefreshDatabase) seed once per run: permissions and the
     * system roles, exactly what every environment gets. Each test then runs
     * inside a rolled-back transaction.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // The current site is remembered per process; each test starts fresh.
        Site::forgetCurrent();

        // Built assets are not needed to assert on HTML; CI builds them separately.
        $this->withoutVite();
    }
}
