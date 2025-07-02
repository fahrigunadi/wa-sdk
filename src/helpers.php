<?php

if (! function_exists('whatsapp')) {
    function whatsapp(): \FahriGunadi\WhatsApp\Contracts\WhatsappInterface
    {
        return app(\FahriGunadi\Whatsapp\Contracts\WhatsappInterface::class);
    }
}
