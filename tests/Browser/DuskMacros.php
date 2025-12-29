<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Exception;

class DuskMacros
{
    public static function register()
    {
        Browser::macro('waitForDataLayerEvent', function (string $event, array $conditions = [], int $seconds = 5) {
            $start = microtime(true);
            do {
                $script = "
                    return window.dataLayer.filter(function(item) {
                        return item.event === '{$event}'" .
                        collect($conditions)->map(fn ($v, $k) => " && item['{$k}'] === " . json_encode($v))->implode('') . ";
                    });
                ";

                info('waitForDataLayerEvent: ' . $script);
                $events = $this->script($script);

                if (!empty($events[0])) {
                    return $this;
                }

                usleep(250000); // wait 250ms
            } while ((microtime(true) - $start) < $seconds);

            throw new Exception("Timed out waiting for dataLayer event '{$event}'");
        });
    }
}
