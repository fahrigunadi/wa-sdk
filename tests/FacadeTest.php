<?php

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Facades\Whatsapp as WhatsappFacade;
use FahriGunadi\Whatsapp\Testing\WhatsappFake;
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

it('fake() swaps the container binding to a WhatsappFake instance', function () {
    $fake = WhatsappFacade::fake();

    expect($fake)->toBeInstanceOf(WhatsappFake::class);
    expect(app(WhatsappInterface::class))->toBe($fake);
    expect(whatsapp())->toBe($fake);
    expect(WhatsappFacade::getFacadeRoot())->toBe($fake);
});
