<?php

namespace FahriGunadi\Whatsapp;

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Support\Facades\Facade;

class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return WhatsappInterface::class;
    }
}
