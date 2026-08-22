<?php

namespace App\Contracts;

use App\DataTransfer\WhatsAppCredentials;
use App\DataTransfer\WhatsAppOrderMessage;
use App\DataTransfer\WhatsAppSendResult;

/**
 * The order system only ever talks to WhatsAppService, which only ever
 * talks to this interface — no Meta/Twilio/etc. API detail exists outside
 * a concrete implementation. See the AI Order Assistant architecture doc,
 * Part II §8.
 */
interface WhatsAppProviderInterface
{
    /**
     * $credentials null = use the platform's own config/services.php
     * credentials; a WhatsAppCredentials instance = use a vendor's own
     * (brief §21, architecture doc Part III).
     */
    public function sendOrderNotification(string $toPhone, WhatsAppOrderMessage $message, ?WhatsAppCredentials $credentials = null): WhatsAppSendResult;
}
