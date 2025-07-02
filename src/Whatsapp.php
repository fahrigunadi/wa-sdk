<?php

namespace FahriGunadi\Whatsapp;

use Illuminate\Support\Facades\Facade;

class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \FahriGunadi\Whatsapp\Contracts\WhatsappInterface::class;
    }
}
