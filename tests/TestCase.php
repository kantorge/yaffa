<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
    * If true, setup has run at least once.
    * @var boolean
    */
    protected static $setUpHasRunOnce = false;

    public function setUp() :void
    {
        echo ("TestCase setup before\r\n");
        echo ("Env: ". env('APP_ENV'). "\r\n");
        echo ("DB connection set: ". env('DB_CONNECTION'). "\r\n");

        parent::setUp();

        echo ("TestCase setup after\r\n");

        echo ("DB connection used: " . DB::connection()->getDatabaseName() . "\r\n");

        echo ("Seed start\r\n");
        if (!static::$setUpHasRunOnce) {
            echo ("Seeding...\r\n");
            Artisan::call('migrate:fresh', ['--database' => env('DB_CONNECTION')]);
            Artisan::call('db:seed', ['--class' => 'TestSeeder']);

            static::$setUpHasRunOnce = true;
        }
        echo ("Seed end\r\n");

    }
}
