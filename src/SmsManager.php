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
            username: $this->app['config']['sms.providers.at.username'],
            apiKey: $this->app['config']['sms.providers.at.api_key'],
            from: $this->app['config']['sms.providers.at.from'],
            apiUrl: $this->app['config']['sms.providers.at.api_url'],
        );
    }

    public function createOnfonMediaDriver(): OnfonMediaProvider
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

    /**
     * Get the list of available SMS providers.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return [
            'advanta',
            'africastalking',
            'onfon',
            'nexmo',
            'twilio',
        ];
    }

    /**
     * Check if a provider is configured.
     */
    public function isProviderConfigured(string $provider): bool
    {
        $configKey = match ($provider) {
            'africastalking' => 'at',
            'onfon' => 'onfon',
            default => $provider,
        };

        $config = $this->app['config']["sms.providers.{$configKey}"] ?? [];

        // Check if at least the basic required config is set
        return match ($provider) {
            'advanta' => ! empty($config['api_key']) && ! empty($config['api_url']),
            'africastalking' => ! empty($config['api_key']),
            'onfon' => ! empty($config['api_key']) && ! empty($config['api_url']),
            'nexmo' => ! empty($config['key']) && ! empty($config['secret']),
            'twilio' => ! empty($config['account_sid']) && ! empty($config['auth_token']),
            default => false,
        };
    }
}
