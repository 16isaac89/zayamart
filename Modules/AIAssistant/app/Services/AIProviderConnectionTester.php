<?php

namespace Modules\AIAssistant\app\Services;

use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Modules\AIAssistant\app\Exceptions\AIProviderException;
use Modules\AIAssistant\app\Models\AIProvider;

/**
 * "Test Connection" (brief §6) — a minimal real provider request, never a
 * stored raw provider response (may contain sensitive echoes). Only the
 * outcome (message) is persisted, on vendor_ai_providers.last_test_message.
 */
class AIProviderConnectionTester
{
    public function __construct(private readonly AIProviderManager $providerManager)
    {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function test(AIProvider $providerCatalogEntry, string $apiKey, ?string $baseUrl, string $model): array
    {
        try {
            $adapter = $this->providerManager->resolveAdapterByKey($providerCatalogEntry->key);
            $adapter->setCredentials(new AIProviderCredentials(apiKey: $apiKey, baseUrl: $baseUrl));

            $adapter->chat(new AIChatRequest(
                model: $model,
                systemInstructions: null,
                messages: [ChatMessage::user('ping')],
                maxTokens: 8,
            ));

            return ['success' => true, 'message' => 'Connection successful.'];
        } catch (AIProviderException $exception) {
            // The exception message may embed the provider's raw HTTP body
            // (see AbstractOpenAICompatibleProvider::chat()) — truncate and
            // strip anything resembling a key/token before it's ever stored
            // or shown, since a provider's own error text can sometimes
            // echo back part of what was sent.
            return ['success' => false, 'message' => $this->sanitize($exception->getMessage())];
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => 'Could not reach the provider. Please check the API key and base URL.'];
        }
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/sk-[A-Za-z0-9_-]{10,}/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._-]+/i', 'Bearer [redacted]', $message) ?? $message;

        return mb_strimwidth($message, 0, 200, '…');
    }
}
