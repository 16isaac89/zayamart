<?php

namespace Modules\AIAssistant\app\DataTransfer;

final class AIProviderCredentials
{
    public function __construct(
        public readonly ?string $apiKey,
        public readonly ?string $baseUrl = null,
    ) {
    }
}
