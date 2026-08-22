<?php

namespace App\Jobs;

use App\Services\VendorNotificationOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The single queued entrypoint every notification call site (order
 * listeners, HandoffService, future events) dispatches through — never
 * calls VendorNotificationOrchestrator::notify() synchronously from a live
 * request. See the notification architecture report, §15.
 */
class DispatchVendorNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $sellerId,
        private readonly string $type,
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $relatedType = null,
        private readonly ?int $relatedId = null,
        private readonly ?string $actionUrl = null,
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Wrapped in its own try/catch — under this project's default
     * QUEUE_CONNECTION=sync, this job's handle() runs in-process wherever
     * it was dispatched from, including synchronously inside a live
     * customer chat turn (HandoffService) or inside AICheckoutService's
     * DB::transaction() (via the order listeners). An uncaught exception
     * here must never surface as a broken chat response or a rolled-back
     * order. See the notification architecture report.
     */
    public function handle(VendorNotificationOrchestrator $orchestrator): void
    {
        try {
            $orchestrator->notify(
                sellerId: $this->sellerId,
                type: $this->type,
                title: $this->title,
                message: $this->message,
                relatedType: $this->relatedType,
                relatedId: $this->relatedId,
                actionUrl: $this->actionUrl,
                metadata: $this->metadata,
            );
        } catch (\Throwable $exception) {
            Log::warning('DispatchVendorNotificationJob failed', [
                'seller_id' => $this->sellerId,
                'type' => $this->type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
