<?php

namespace App\Http\Controllers;

use App\Components\FlashMessages;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use DispatchesJobs;
    use FlashMessages;
}
