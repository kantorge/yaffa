<?php

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

return [

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => [
        Collection::class,
        Currency::class,
    ],

];
