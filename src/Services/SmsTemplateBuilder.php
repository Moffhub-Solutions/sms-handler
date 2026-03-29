<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use Illuminate\Support\Collection;
use Throwable;

class SmsTemplateBuilder
{
    protected ?string $phoneNumber = null;

    public function __construct(
        protected SmsService $smsService,
        protected string $message,
    ) {}

    /**
     * Set the recipient phone number.
     */
    public function to(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Get the rendered message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Send the templated SMS.
     *
     * @throws Throwable
     */
    public function send(): ?Collection
    {
        if ($this->phoneNumber === null) {
            throw new \InvalidArgumentException('Recipient phone number is required. Call ->to($phone) before ->send().');
        }

        return $this->smsService->sendSms($this->phoneNumber, $this->message);
    }
}
