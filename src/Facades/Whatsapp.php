<?php

namespace FahriGunadi\Whatsapp\Facades;

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Testing\WhatsappFake;
use Illuminate\Support\Facades\Facade;

/**
 * @see WhatsappInterface
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WhatsappInterface::class;
    }

    public static function fake(): WhatsappFake
    {
        $fake = new WhatsappFake;

        static::swap($fake);

        return $fake;
    }
}
