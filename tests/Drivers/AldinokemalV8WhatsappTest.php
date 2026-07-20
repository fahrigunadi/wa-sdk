<?php

use FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp;
use FahriGunadi\Whatsapp\Drivers\WuzapiWhatsapp;

describe('driver base defaults', function () {
    it('throws not implemented for file() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->file('https://example.com/a.pdf');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for video() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->video('https://example.com/a.mp4');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for file() on wuzapi', function () {
        (new WuzapiWhatsapp)->file('https://example.com/a.pdf');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for video() on wuzapi', function () {
        (new WuzapiWhatsapp)->video('https://example.com/a.mp4');
    })->throws(Exception::class, 'Not implemented');

    it('stores optional flags fluently without affecting drivers that do not read them', function () {
        $driver = (new AldinokemalWhatsapp)
            ->forwarded()
            ->duration(86400)
            ->viewOnce()
            ->compress()
            ->gifPlayback();

        expect($driver)->toBeInstanceOf(AldinokemalWhatsapp::class);
    });
});
