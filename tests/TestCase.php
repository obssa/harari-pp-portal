<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Built assets are not needed to assert on HTML; CI builds them separately.
        $this->withoutVite();
    }
}
