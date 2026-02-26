<?php

namespace App\Http\Controllers;

use App\Components\FlashMessages;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
    use FlashMessages;
}
