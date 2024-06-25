<?php

namespace Caracom\Drivers\Rest\Facades;

use Illuminate\Support\Facades\Facade;

class MicroRest extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'rest-method';
    }
}
