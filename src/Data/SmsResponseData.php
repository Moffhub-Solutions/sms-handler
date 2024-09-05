<?php

class SmsResponseData
{
    public function __construct(
        public string $messageId,
        public string $status,
        public string $to,
        public string $message,
        public string $provider,
        public array $response = []
    ) {
        ///
    }
}
