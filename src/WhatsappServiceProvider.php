<?php

namespace FahriGunadi\Whatsapp;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use FahriGunadi\Whatsapp\Commands\WhatsappCommand;

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
            ->name('wa-sdk')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_wa_sdk_table')
            ->hasCommand(WhatsappCommand::class);
    }
}
