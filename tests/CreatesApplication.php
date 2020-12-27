<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
    * If true, setup has run at least once.
    * @var boolean
    */
    protected static $setUpHasRunOnce = false;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        //echo ("createApplication start\r\n");

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (!static::$setUpHasRunOnce) {
            $this->clearCache();
        }

        //echo ("createApplication end\r\n");

        return $app;
    }

    /**
     * Clears Laravel Cache.
     */
    protected function clearCache()
    {
        //echo ("Clear cache start\r\n");

        $commands = ['clear-compiled', 'cache:clear', 'view:clear', 'config:clear', 'route:clear'];
        foreach ($commands as $command) {
            \Illuminate\Support\Facades\Artisan::call($command);
        }

        //echo ("Clear cache end\r\n");

        static::$setUpHasRunOnce = true;
    }
}
