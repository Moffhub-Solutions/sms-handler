<?php

namespace Moffhub\SmsHandler;

use Illuminate\Foundation\Application;
use Illuminate\Support\Manager;
use Moffhub\SmsHandler\Providers\Advanta;
use Moffhub\SmsHandler\Providers\AfricasTalking;

/**
 * @property Application $app
 */
class SmsManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->app['config']['sms.default'];
    }

    public function createAdvantaDriver(): Advanta
    {
        return new Advanta(
            $this->app['config']['sms.providers.advanta.api_key'],
            $this->app['config']['sms.providers.advanta.api_url'],
        );
    }

    public function createAfricasTalkingDriver(): AfricasTalking
    {
        return new AfricasTalking(
            $this->app['config']['sms.providers.provider2.api_key'],
            $this->app['config']['sms.providers.provider2.api_url'],
        );
    }
}
