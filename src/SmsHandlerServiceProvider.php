<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler;

use Illuminate\Support\ServiceProvider;
use Moffhub\SmsHandler\Notifications\SmsChannel;
use Moffhub\SmsHandler\Services\SmsService;

class SmsHandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/sms.php', 'sms');

        $this->app->singleton(SmsManager::class, function ($app) {
            return new SmsManager($app);
        });

        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService($app->make(SmsManager::class));
        });

        $this->app->singleton('sms', function ($app) {
            return $app->make(SmsService::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/Config/sms.php' => config_path('sms.php'),
        ], 'sms-config');

        $this->publishesMigrations([
            __DIR__.'/Database/Migrations' => database_path('migrations'),
        ], 'sms-migrations');

        $this->app->singleton(SmsChannel::class, function ($app) {
            return new SmsChannel($app->make(SmsService::class));
        });
    }
}
