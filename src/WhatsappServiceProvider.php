<?php

namespace FahriGunadi\Whatsapp;

use FahriGunadi\Whatsapp\Commands\WhatsappCommand;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\WhatsApp\Drivers\AldinokemalWhatsapp;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WhatsappServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('whatsapp')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(WhatsappCommand::class);
    }

    public function register()
    {
        $this->app->singleton(WhatsappInterface::class, fn () => match (config('whatsapp.driver')) {
            'aldinokemal' => new AldinokemalWhatsapp,
            default => new AldinokemalWhatsapp,
        });

        $this->app->alias(WhatsappInterface::class, 'whatsapp');

        parent::register();
    }
}
