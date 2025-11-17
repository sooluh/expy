<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Sqids extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'sqids';
    }
}
