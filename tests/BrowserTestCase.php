<?php

namespace Tests;

/**
 * Browser tests (ADR-0042) empty the tables before each test instead of
 * rolling back a transaction. The rows the migrations write themselves (the
 * default site, the settings and the import sources) are kept.
 */
abstract class BrowserTestCase extends TestCase
{
    /** @var list<string> */
    protected $exceptTables = ['sites', 'settings', 'content_sources'];
}
