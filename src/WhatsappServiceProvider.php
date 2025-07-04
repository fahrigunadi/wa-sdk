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

    public function register(): void
    {
        $this->app->singleton(WhatsappInterface::class, fn () => match (config('whatsapp.driver')) {
            'aldinokemal' => new AldinokemalWhatsapp,
            default => new AldinokemalWhatsapp,
        });

        $this->app->alias(WhatsappInterface::class, 'whatsapp');

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerLoggingChannels();
    }

    protected function registerLoggingChannels(): void
    {
        $channels = config('logging.channels');

        if (! isset($channels['whatsapp'])) {
            $channels['whatsapp'] = [
                'driver' => 'daily',
                'path' => storage_path('logs/whatsapp.log'),
                'level' => 'debug',
                'days' => 14,
            ];
        }

        if (! isset($channels['whatsapp-webhook'])) {
            $channels['whatsapp-webhook'] = [
                'driver' => 'daily',
                'path' => storage_path('logs/whatsapp-webhook.log'),
                'level' => 'debug',
                'days' => 14,
            ];
        }

        config(['logging.channels' => $channels]);
    }
}
