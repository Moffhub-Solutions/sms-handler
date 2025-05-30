<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler;

use Illuminate\Foundation\Application;
use Illuminate\Support\Manager;
use Moffhub\SmsHandler\Providers\AdvantaProvider;
use Moffhub\SmsHandler\Providers\AfricasTalkingProvider;
use Moffhub\SmsHandler\Providers\NexmoProvider;
use Moffhub\SmsHandler\Providers\OnfonMediaProvider;
use Moffhub\SmsHandler\Providers\TwilioProvider;

class SmsManager extends Manager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->app = $app;
    }

    public function createAdvantaDriver(): AdvantaProvider
    {
        return new AdvantaProvider(
            app: $this->app,
            apiKey: $this->app['config']['sms.providers.advanta.api_key'],
            apiUrl: $this->app['config']['sms.providers.advanta.api_url'],
            partnerId: $this->app['config']['sms.providers.advanta.partner_id'],
            shortCode: $this->app['config']['sms.providers.advanta.short_code'],
            bulkApiUrl: $this->app['config']['sms.providers.advanta.bulk_api_url'],
        );
    }

    public function createAfricasTalkingDriver(): AfricasTalkingProvider
    {
        return new AfricasTalkingProvider(
            $this->app['config']['sms.providers.at.api_key'],
            $this->app['config']['sms.providers.at.api_url'],
        );
    }

    public function createOnfonMediaDriver()
    {
        return new OnfonMediaProvider(
            app: $this->app,
            apiKey: $this->app['config']['sms.providers.onfon.api_key'],
            apiUrl: $this->app['config']['sms.providers.onfon.api_url'],
            senderId: $this->app['config']['sms.providers.onfon.sender_id'],
            clientId: $this->app['config']['sms.providers.onfon.client_id'],
        );
    }

    public function createNexmoDriver(): NexmoProvider
    {
        return new NexmoProvider(
            key: $this->app['config']['sms.providers.nexmo.key'],
            secret: $this->app['config']['sms.providers.nexmo.secret'],
            from: $this->app['config']['sms.providers.nexmo.from'],
            apiUrl: $this->app['config']['sms.providers.nexmo.api_url'],
        );
    }

    public function createTwilioDriver(): TwilioProvider
    {
        return new TwilioProvider(
            accountSid: $this->app['config']['sms.providers.twilio.account_sid'],
            authToken: $this->app['config']['sms.providers.twilio.auth_token'],
            from: $this->app['config']['sms.providers.twilio.from'],
            apiUrl: $this->app['config']['sms.providers.twilio.api_url'],
        );
    }


    public function getDefaultDriver(): string
    {
        return $this->app['config']['sms.default'];
    }
}
