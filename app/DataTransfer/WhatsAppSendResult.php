<?php

namespace App\DataTransfer;

final class WhatsAppSendResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId,
        public readonly ?string $errorMessage,
    ) {
    }

    public static function ok(?string $providerMessageId): self
    {
        return new self(true, $providerMessageId, null);
    }

    public static function fail(string $errorMessage): self
    {
        return new self(false, null, $errorMessage);
    }
}
