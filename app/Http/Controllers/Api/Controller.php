<?php

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;


abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
