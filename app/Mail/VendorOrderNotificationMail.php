<?php

namespace App\Mail;

use App\Models\Currency;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email counterpart to WhatsAppOrderNotificationListener's WhatsApp message
 * — same "new order" content (customer, address, items, total, status),
 * built straight from the Order the same way WhatsAppService::buildMessage()
 * does, since this also runs inside a queued listener with no bound session.
 */
class VendorOrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly Order $order)
    {
    }

    public function build(): self
    {
        $order = $this->order;
        $order->loadMissing('details', 'customer');
        $address = (object)($order->shipping_address_data ?? []);

        $items = $order->details->map(function ($detail) {
            $snapshot = json_decode((string)$detail->product_details, true);
            return [
                'name' => $snapshot['name'] ?? ('Product #' . $detail->product_id),
                'quantity' => (int)$detail->qty,
            ];
        })->all();

        return $this->subject(translate('New_order_received') . ' #' . $order->id)
            ->view('email-templates.vendor-order-notification', [
                'order' => $order,
                'customerName' => $address->contact_person_name ?? $order->customer?->name ?? 'Customer',
                'customerPhone' => $address->phone ?? $order->customer?->phone ?? '',
                'deliveryAddress' => $address->address ?? '',
                'items' => $items,
                'total' => (float)$order->order_amount,
                'currencySymbol' => Currency::find(getWebConfig(name: 'system_default_currency'))?->symbol ?? '',
                'status' => $order->order_status,
            ]);
    }
}
