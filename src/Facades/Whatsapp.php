<?php

namespace FahriGunadi\Whatsapp\Facades;

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
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
}
