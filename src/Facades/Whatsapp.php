<?php

namespace FahriGunadi\Whatsapp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \FahriGunadi\Whatsapp\Whatsapp
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FahriGunadi\Whatsapp\Whatsapp::class;
    }
}
