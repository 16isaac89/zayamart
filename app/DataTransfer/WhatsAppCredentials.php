<?php

namespace App\DataTransfer;

/**
 * When null is passed to WhatsAppProviderInterface::sendOrderNotification()
 * instead of an instance of this, the provider falls back to the
 * platform's own config/services.php credentials — see
 * MetaCloudWhatsAppProvider.
 */
final class WhatsAppCredentials
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $phoneNumberId,
    ) {
    }
}
