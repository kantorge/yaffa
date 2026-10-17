<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PEST\Support\BrowserTestCase;
use Tests\TestCase;

/*
 * Function-style Pest tests only. Existing class-based PHPUnit tests are unaffected by these bindings.
 */
pest()->extend(TestCase::class)->in('Unit', 'Feature');

pest()->extend(BrowserTestCase::class)->use(RefreshDatabase::class)->in('PEST');

pest()->browser()->inChrome()->timeout(10_000);
