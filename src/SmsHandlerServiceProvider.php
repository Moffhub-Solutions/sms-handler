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
        ], 'config');

        $migrationStub = __DIR__.'/Database/Migrations/create_sms_logs_table.php.stub';
        $migrationFilename = 'create_sms_logs_table.php';

        $existing = collect(glob(database_path("migrations/*_{$migrationFilename}")))->first();
        $targetPath = $existing ?: database_path('migrations/'.date('Y_m_d_His')."_{$migrationFilename}");

        $this->publishes([
            $migrationStub => $targetPath,
        ], 'migrations');

        $this->app->singleton(SmsChannel::class, function ($app) {
            return new SmsChannel($app->make(SmsService::class));
        });
    }
}
