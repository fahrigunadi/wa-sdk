<?php

declare(strict_types=1);

namespace FahriGunadi\Whatsapp\Contracts;

/**
 * Interface WhatsappDeviceInterface
 *
 * Defines the contract for interacting with a WhatsApp service provider.
 */
interface WhatsappDeviceInterface
{
    /**
     * Set the device id for the WhatsApp service.
     *
     * @param  string  $device  The device id.
     */
    public function device(string $device): static;
}
