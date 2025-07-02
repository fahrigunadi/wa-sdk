<?php

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;

if (! function_exists('whatsapp')) {
    function whatsapp(): WhatsappInterface
    {
        return app(WhatsappInterface::class);
    }
}
