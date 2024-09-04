<?php

namespace Moffhub\SmsHandler;

use Illuminate\Support\Manager;
use Moffhub\SmsHandler\Providers\Advanta;
use Moffhub\SmsHandler\Providers\AfricasTalking;

class SmsManager extends Manager
{
    public function createAdvantaDriver(): Advanta
    {
        return new Advanta(
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

    public function getDefaultDriver()
    {
        return $this->app['config']['sms.default'];
    }
}
