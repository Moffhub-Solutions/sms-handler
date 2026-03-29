<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * Validates webhook signatures per provider. The provider name is extracted
     * from the route name (e.g., 'sms.webhooks.twilio' -> 'twilio').
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provider = $this->resolveProvider($request);

        if (! $provider) {
            return $next($request);
        }

        $secret = config("sms.webhooks.secrets.{$provider}");

        // If no secret is configured, skip validation (allows opt-in)
        if (empty($secret)) {
            return $next($request);
        }

        if (! $this->isValidSignature($request, $provider, $secret)) {
            return response()->json(['error' => 'Invalid webhook signature'], 403);
        }

        return $next($request);
    }

    /**
     * Resolve the provider name from the route.
     */
    protected function resolveProvider(Request $request): ?string
    {
        $routeName = $request->route()->getName();

        if (! $routeName) {
            return null;
        }

        if (str_starts_with($routeName, 'sms.webhooks.')) {
            return str_replace('sms.webhooks.', '', $routeName);
        }

        if (str_starts_with($routeName, 'sms.inbound.')) {
            return str_replace('sms.inbound.', '', $routeName);
        }

        return null;
    }

    /**
     * Validate the webhook signature for a given provider.
     */
    protected function isValidSignature(Request $request, string $provider, string $secret): bool
    {
        return match ($provider) {
            'twilio' => $this->validateTwilioSignature($request, $secret),
            'africastalking' => $this->validateAfricasTalkingSignature($request, $secret),
            'advanta' => $this->validateAdvantaSignature($request, $secret),
            'nexmo' => $this->validateNexmoSignature($request, $secret),
            'onfon' => $this->validateOnfonSignature($request, $secret),
            default => false,
        };
    }

    /**
     * Validate Twilio X-Twilio-Signature header.
     *
     * Twilio signs requests using HMAC-SHA1 over the URL + sorted POST params.
     */
    protected function validateTwilioSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Twilio-Signature');

        if (! $signature) {
            return false;
        }

        $url = $request->fullUrl();
        $params = $request->post();

        // Sort parameters alphabetically by key
        ksort($params);

        // Append each key-value pair to the URL
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Validate Africa's Talking callback using a shared token.
     */
    protected function validateAfricasTalkingSignature(Request $request, string $secret): bool
    {
        $token = $request->header('X-AT-Signature') ?? $request->input('token');

        if (! $token) {
            return false;
        }

        return hash_equals($secret, $token);
    }

    /**
     * Validate Advanta webhook using a shared secret header.
     */
    protected function validateAdvantaSignature(Request $request, string $secret): bool
    {
        $headerSecret = $request->header('X-Webhook-Secret');

        if (! $headerSecret) {
            return false;
        }

        return hash_equals($secret, $headerSecret);
    }

    /**
     * Validate Nexmo/Vonage webhook signature.
     *
     * Nexmo uses a shared secret token or HMAC-SHA256 signature.
     */
    protected function validateNexmoSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Vonage-Signature')
            ?? $request->header('X-Nexmo-Signature');

        if ($signature) {
            // HMAC-SHA256 validation
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $secret);

            return hash_equals($expected, $signature);
        }

        // Fall back to token-based validation
        $token = $request->input('token');

        if (! $token) {
            return false;
        }

        return hash_equals($secret, $token);
    }

    /**
     * Validate Onfon webhook using API key header.
     */
    protected function validateOnfonSignature(Request $request, string $secret): bool
    {
        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return false;
        }

        return hash_equals($secret, $apiKey);
    }
}
