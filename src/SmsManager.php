<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler;

use Illuminate\Foundation\Application;
use Illuminate\Support\Manager;
use Moffhub\SmsHandler\Providers\Advanta;
use Moffhub\SmsHandler\Providers\AfricasTalking;

class SmsManager extends Manager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->app = $app;
    }

    public function createAdvantaDriver(): Advanta
    {
        return new Advanta(
            app: $this->app,
            apiKey: $this->app['config']['sms.providers.advanta.api_key'],
            apiUrl: $this->app['config']['sms.providers.advanta.api_url'],
            partnerId: $this->app['config']['sms.providers.advanta.partner_id'],
            shortCode: $this->app['config']['sms.providers.advanta.short_code'],
            bulkApiUrl: $this->app['config']['sms.providers.advanta.bulk_api_url'],
        );
    }

    public function createAfricasTalkingDriver(): AfricasTalking
    {
        return new AfricasTalking(
            $this->app['config']['sms.providers.provider2.api_key'],
            $this->app['config']['sms.providers.provider2.api_url'],
        );
    }

    public function getDefaultDriver(): string
    {
        return $this->app['config']['sms.default'];
    }
}
