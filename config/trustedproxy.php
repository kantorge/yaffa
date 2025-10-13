<?php

$proxies = null;

$trustedProxies = env('TRUSTED_PROXIES', null);
if ($trustedProxies === '*' || $trustedProxies === '**') {
    $proxies = $trustedProxies;
} elseif ($trustedProxies) {
    $proxies = [];

    foreach (explode(',', env('TRUSTED_PROXIES')) as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $proxies[] = $ip;
        }
    }
}

return [
    'proxies' => $proxies
];
