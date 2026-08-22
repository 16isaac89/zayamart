<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\DataTransfer\WhatsAppCredentials;
use App\DataTransfer\WhatsAppOrderMessage;
use App\DataTransfer\WhatsAppSendResult;
use Illuminate\Support\Facades\Http;

/**
 * Meta WhatsApp Cloud API (Graph API). All Meta-specific detail — access
 * token, phone-number ID, endpoint shape — is contained here; nothing
 * outside this class knows it exists. See architecture doc Part II §8.
 */
class MetaCloudWhatsAppProvider implements WhatsAppProviderInterface
{
    public function sendOrderNotification(string $toPhone, WhatsAppOrderMessage $message, ?WhatsAppCredentials $credentials = null): WhatsAppSendResult
    {
        $accessToken = $credentials?->accessToken ?? config('services.whatsapp_cloud.access_token');
        $phoneNumberId = $credentials?->phoneNumberId ?? config('services.whatsapp_cloud.phone_number_id');
        $apiVersion = config('services.whatsapp_cloud.api_version');

        if (!$accessToken || !$phoneNumberId) {
            return WhatsAppSendResult::fail('WhatsApp Cloud API is not configured.');
        }

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($toPhone),
                'type' => 'text',
                'text' => ['body' => $message->toText()],
            ]);

        if ($response->failed()) {
            return WhatsAppSendResult::fail("Meta Cloud API error ({$response->status()}): " . $response->body());
        }

        return WhatsAppSendResult::ok($response->json('messages.0.id'));
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone);
    }
}
