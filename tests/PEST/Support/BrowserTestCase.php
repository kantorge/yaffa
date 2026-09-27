<?php

namespace Tests\PEST\Support;

use Tests\TestCase;

abstract class BrowserTestCase extends TestCase
{
    /**
     * Run DatabaseSeeder once per run, together with RefreshDatabase's initial migrate:fresh.
     * Each test then runs in its own rolled-back transaction on top of the seeded demo data.
     */
    protected $seed = true;
}
