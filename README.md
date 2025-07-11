# whatsapp

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fahrigunadi/whatsapp.svg?style=flat-square)](https://packagist.org/packages/fahrigunadi/whatsapp)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/fahrigunadi/whatsapp/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/fahrigunadi/whatsapp/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/fahrigunadi/whatsapp/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/fahrigunadi/whatsapp/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/fahrigunadi/whatsapp.svg?style=flat-square)](https://packagist.org/packages/fahrigunadi/whatsapp)

## Installation

You can install the package via composer:

```bash
composer require fahrigunadi/whatsapp
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="whatsapp-config"
```

This is the contents of the published config file:

```php
return [
    /**
     * --------------------------------------------------------------------------
     * WhatsApp Driver
     * --------------------------------------------------------------------------
     *
     * This option defines which driver implementation to use for sending
     * WhatsApp messages. You may configure your own custom driver or use
     * one of the available drivers such as 'aldinokemal', 'wuzapi', etc.
     *
     * Supported: "aldinokemal", "wuzapi"
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
```

## Usage

```php
whatsapp()
    ->to('+628XXXXXXXXXX')
    ->message('Hello World')
    ->send();

whatsapp()
    ->to('+628XXXXXXXXXX')
    ->image('https://files.f-g.my.id/images/dummy/botol-2.jpg')
    ->send();

\FahriGunadi\Whatsapp\Whatsapp::to('+628XXXXXXXXXX')
    ->image('https://files.f-g.my.id/images/dummy/botol-2.jpg')
    ->message('Image Caption')
    ->send();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Fahri Gunadi](https://github.com/fahrigunadi)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
