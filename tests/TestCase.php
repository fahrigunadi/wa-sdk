<?php

namespace FahriGunadi\Whatsapp\Tests;

use FahriGunadi\Whatsapp\WhatsappServiceProvider;
use Illuminate\whatsapp\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Factory::guessFactoryNamesUsing(
        //     fn (string $modelName) => 'FahriGunadi\\Whatsapp\\Database\\Factories\\'.class_basename($modelName).'Factory'
        // );
    }

    protected function getPackageProviders($app)
    {
        return [
            WhatsappServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/whatsapp/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}
