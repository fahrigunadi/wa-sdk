<?php

/**
 * Configuration file for FahriGunadi/Whatsapp package.
 *
 * This configuration controls how your application interacts with
 * the selected WhatsApp provider or driver.
 */

return [

    /**
     * --------------------------------------------------------------------------
     * WhatsApp Driver
     * --------------------------------------------------------------------------
     *
     * This option defines which driver implementation to use for sending
     * WhatsApp messages. You may configure your own custom driver or use
     * one of the available drivers such as 'aldinokemal', etc.
     *
     * Supported: "aldinokemal"
     */
    'driver' => env('WHATSAPP_DRIVER', 'aldinokemal'),

    /**
     * --------------------------------------------------------------------------
     * WhatsApp Username
     * --------------------------------------------------------------------------
     *
     * The username used to authenticate with the selected WhatsApp provider.
     * This value is typically provided by the API service you are integrating with.
     */
    'username' => env('WHATSAPP_USERNAME'),

    /**
     * --------------------------------------------------------------------------
     * WhatsApp Password or Token
     * --------------------------------------------------------------------------
     *
     * The password or access token required to authenticate with the provider.
     * This credential should be kept secure and never exposed publicly.
     */
    'password' => env('WHATSAPP_PASSWORD'),

    /**
     * --------------------------------------------------------------------------
     * WhatsApp Base URL
     * --------------------------------------------------------------------------
     *
     * The base endpoint URL for the WhatsApp API. This is where all requests
     * will be sent. Make sure the URL is correct and includes the necessary
     * protocol (http or https).
     */
    'base_url' => env('WHATSAPP_BASE_URL'),
];
