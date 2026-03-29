<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Moffhub\SmsHandler\Console\Commands\CheckDeliveryStatus;
use Moffhub\SmsHandler\Console\Commands\SendSmsCommand;
use Moffhub\SmsHandler\Console\Commands\SmsHealthCheckCommand;
use Moffhub\SmsHandler\Console\Commands\SmsStatsCommand;
use Moffhub\SmsHandler\Http\Controllers\DeliveryReportController;
use Moffhub\SmsHandler\Http\Controllers\InboundSmsController;
use Moffhub\SmsHandler\Http\Middleware\ValidateWebhookSignature;
use Moffhub\SmsHandler\Notifications\SmsChannel;
use Moffhub\SmsHandler\Services\AdaptiveRetryStrategy;
use Moffhub\SmsHandler\Services\CostEstimator;
use Moffhub\SmsHandler\Services\SmsAnalytics;
use Moffhub\SmsHandler\Services\SmsRateLimiter;
use Moffhub\SmsHandler\Services\SmsService;
use Moffhub\SmsHandler\Services\TemplateService;

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

        $this->app->singleton(SmsRateLimiter::class, function () {
            return new SmsRateLimiter;
        });

        $this->app->singleton(TemplateService::class, function () {
            return new TemplateService;
        });

        $this->app->singleton(CostEstimator::class, function () {
            return new CostEstimator;
        });

        $this->app->singleton(SmsAnalytics::class, function () {
            return new SmsAnalytics;
        });

        $this->app->singleton(AdaptiveRetryStrategy::class, function () {
            return new AdaptiveRetryStrategy;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckDeliveryStatus::class,
                SendSmsCommand::class,
                SmsHealthCheckCommand::class,
                SmsStatsCommand::class,
            ]);
        }

        $this->validateConfig();
        $this->registerWebhookRoutes();
        $this->registerInboundRoutes();
    }

    /**
     * Validate SMS configuration on boot.
     */
    protected function validateConfig(): void
    {
        $default = config('sms.default');
        $recognizedProviders = ['advanta', 'africastalking', 'at', 'onfon', 'nexmo', 'twilio'];

        if ($default && ! in_array($default, $recognizedProviders, true)) {
            Log::warning("SMS: Unrecognized default provider '{$default}'. Recognized providers: ".implode(', ', $recognizedProviders));
        }

        $logChannel = config('sms.log_channel');
        if ($logChannel && ! in_array($logChannel, ['log', 'model'], true)) {
            Log::warning("SMS: Invalid log_channel '{$logChannel}'. Must be 'log' or 'model'.");
        }

        // Warn if configured provider has missing credentials
        if ($default && in_array($default, $recognizedProviders, true)) {
            $manager = $this->app->make(SmsManager::class);
            $driverName = match ($default) {
                'at' => 'africastalking',
                default => $default,
            };

            if (! $manager->isProviderConfigured($driverName)) {
                Log::warning("SMS: Default provider '{$default}' has missing credentials. SMS sending may fail.");
            }
        }
    }

    protected function registerWebhookRoutes(): void
    {
        if (! config('sms.webhooks.enabled', false)) {
            return;
        }

        $prefix = config('sms.webhooks.prefix', 'sms/webhooks');
        $middleware = config('sms.webhooks.middleware', []);
        $rateLimit = (int) config('sms.webhooks.rate_limit', 60);

        // Register rate limiter for webhook routes
        RateLimiter::for('sms-webhooks', function (Request $request) use ($rateLimit) {
            return Limit::perMinute($rateLimit)->by($request->ip());
        });

        $routeGroup = Route::prefix($prefix)
            ->withoutMiddleware(['auth', 'auth:sanctum', 'auth:api'])
            ->middleware([
                'throttle:sms-webhooks',
                ValidateWebhookSignature::class,
            ]);

        if (! empty($middleware)) {
            $routeGroup->middleware($middleware);
        }

        $routeGroup->group(function () {
            Route::post('/advanta', [DeliveryReportController::class, 'advanta'])
                ->name('sms.webhooks.advanta');
            Route::post('/africastalking', [DeliveryReportController::class, 'africastalking'])
                ->name('sms.webhooks.africastalking');
            Route::post('/onfon', [DeliveryReportController::class, 'onfon'])
                ->name('sms.webhooks.onfon');
            Route::post('/nexmo', [DeliveryReportController::class, 'nexmo'])
                ->name('sms.webhooks.nexmo');
            Route::post('/twilio', [DeliveryReportController::class, 'twilio'])
                ->name('sms.webhooks.twilio');
        });
    }

    protected function registerInboundRoutes(): void
    {
        if (! config('sms.inbound.enabled', false)) {
            return;
        }

        $prefix = config('sms.inbound.route_prefix', 'sms/inbound');
        $webhookMiddleware = config('sms.webhooks.middleware', []);
        $rateLimit = (int) config('sms.webhooks.rate_limit', 60);

        // Reuse the webhook rate limiter if not already registered
        RateLimiter::for('sms-inbound', function (Request $request) use ($rateLimit) {
            return Limit::perMinute($rateLimit)->by($request->ip());
        });

        $routeGroup = Route::prefix($prefix)
            ->withoutMiddleware(['auth', 'auth:sanctum', 'auth:api'])
            ->middleware([
                'throttle:sms-inbound',
                ValidateWebhookSignature::class,
            ]);

        if (! empty($webhookMiddleware)) {
            $routeGroup->middleware($webhookMiddleware);
        }

        $routeGroup->group(function () {
            Route::post('/advanta', [InboundSmsController::class, 'advanta'])
                ->name('sms.inbound.advanta');
            Route::post('/africastalking', [InboundSmsController::class, 'africastalking'])
                ->name('sms.inbound.africastalking');
            Route::post('/onfon', [InboundSmsController::class, 'onfon'])
                ->name('sms.inbound.onfon');
            Route::post('/nexmo', [InboundSmsController::class, 'nexmo'])
                ->name('sms.inbound.nexmo');
            Route::post('/twilio', [InboundSmsController::class, 'twilio'])
                ->name('sms.inbound.twilio');
        });
    }
}
