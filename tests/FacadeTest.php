<?php

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Facades\Whatsapp as WhatsappFacade;
use FahriGunadi\Whatsapp\Whatsapp;

it('resolves Facades\Whatsapp directly to the bound WhatsappInterface', function () {
    expect(WhatsappFacade::getFacadeRoot())->toBeInstanceOf(WhatsappInterface::class);
});

it('forwards fluent calls through Facades\Whatsapp without error', function () {
    expect(WhatsappFacade::to('628123456789'))->toBeInstanceOf(WhatsappInterface::class);
});

it('resolves the root Whatsapp facade to the bound WhatsappInterface', function () {
    expect(Whatsapp::getFacadeRoot())->toBeInstanceOf(WhatsappInterface::class);
});
