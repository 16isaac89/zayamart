<?php

namespace Modules\AIAssistant\app\Services;

use Modules\AIAssistant\app\Models\AiAuditLog;

/**
 * brief §38 — administrative event trail. Callers must never pass a secret
 * (API key, token) into $metadata; nothing here redacts on their behalf.
 */
class AuditLogger
{
    public function log(
        string $actorType,
        ?int $actorId,
        ?int $sellerId,
        string $eventType,
        string $description,
        array $metadata = [],
    ): void {
        AiAuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'seller_id' => $sellerId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
