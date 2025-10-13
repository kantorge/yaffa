<?php

namespace App\Http\Controllers;

use App\Components\FlashMessages;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use FlashMessages;
}
