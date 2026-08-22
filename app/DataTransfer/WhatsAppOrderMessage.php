<?php

namespace App\DataTransfer;

/**
 * Built by WhatsAppService from an already-computed Order/OrderDetail —
 * it formats what OrderManager already calculated, it never recomputes a
 * price or total itself.
 */
final class WhatsAppOrderMessage
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $customerName,
        public readonly string $customerPhone,
        public readonly string $deliveryAddress,
        /** @var array<int, array{name: string, quantity: int}> */
        public readonly array $items,
        public readonly float $total,
        public readonly string $currencySymbol,
        public readonly string $status,
    ) {
    }

    public function toText(): string
    {
        $itemLines = collect($this->items)
            ->map(fn ($item) => "- {$item['name']} ×{$item['quantity']}")
            ->implode("\n");

        return <<<TEXT
            🛒 NEW ORDER #{$this->orderId}

            Customer: {$this->customerName}
            Phone: {$this->customerPhone}
            Delivery: {$this->deliveryAddress}

            Items:
            {$itemLines}

            Total: {$this->currencySymbol}{$this->total}
            Status: {$this->status}
            TEXT;
    }
}
