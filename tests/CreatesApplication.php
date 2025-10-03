<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

trait CreatesApplication
{
    /**
     * If true, setup has run at least once.
     *
     * @var bool
     */
    protected static bool $createApplicationHasRunOnce = false;


    /**
     * Clears Laravel Cache.
     */
    protected function clearCache(): void
    {
        $commands = ['clear-compiled', 'cache:clear', 'view:clear', 'config:clear', 'route:clear'];
        foreach ($commands as $command) {
            Artisan::call($command);
        }

        static::$createApplicationHasRunOnce = true;
    }
}
