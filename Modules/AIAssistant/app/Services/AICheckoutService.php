<?php

namespace Modules\AIAssistant\app\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\DB;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Models\AIConversation;

/**
 * The AI's CreateOrder tool's only job: assemble the same explicit `data`
 * array RestAPI\v1\OrderController already passes to
 * OrderManager::generateOrder(), and call it — unmodified. See architecture
 * doc Part II §9/§10/§12.
 *
 * NOT an "AIOrderManager" — this holds none of the pricing/tax/stock logic
 * generateOrder() already owns. Its only real responsibility beyond
 * assembling that array is scoping the customer's (possibly multi-vendor)
 * cart down to this one seller before calling it, via the exact same
 * is_checked flag the storefront's own multi-vendor checkout selection
 * already uses (see CartController::updateCheckedCartItems) — without
 * that, generateOrder() would place — and WhatsApp-notify — orders for
 * every vendor in the customer's cart, not just this one.
 */
class AICheckoutService
{
    public function __construct(private readonly WhatsAppLinkService $whatsAppLinkService)
    {
    }

    public function confirmOrder(AIConversation $conversation, ToolExecutionContext $context, array $arguments): array
    {
        if ($conversation->hasConfirmedOrder()) {
            return $this->existingOrderSummary($conversation);
        }

        if ($conversation->status !== 'awaiting_confirmation') {
            throw new \RuntimeException('Call start_checkout before create_order.');
        }

        $ownerId = $context->isGuest ? $context->guestId : $context->customerId;
        $isGuestFlag = $context->isGuest ? 1 : 0;

        // A cache-based lock (Cache::lock()) is deliberately not used here:
        // this project's default cache driver is 'file', which does not
        // implement Laravel's LockProvider contract and would throw. A
        // pessimistic row lock on the conversation itself is portable
        // across every cache config and gives the same guarantee — only
        // one transaction can hold this conversation row at a time.
        //
        // Address resolution (which can insert a new ShippingAddress row)
        // happens inside this same lock/transaction — otherwise two
        // concurrent calls with a new_address payload could each insert a
        // row before either reaches the confirmed-order check below. That
        // wouldn't duplicate the order (the lock still guarantees that),
        // just leave one orphaned address row, which is worth avoiding
        // outright rather than accepting.
        return DB::transaction(function () use ($conversation, $context, $arguments, $ownerId, $isGuestFlag) {
            $locked = AIConversation::where('id', $conversation->id)->lockForUpdate()->first();

            // Re-check inside the lock — another concurrent call (retry,
            // repeated tool call) may have already confirmed this
            // conversation while we were waiting for it.
            if ($locked->hasConfirmedOrder()) {
                return $this->existingOrderSummary($locked);
            }

            $addressId = $this->resolveAddressId($arguments, $ownerId, $isGuestFlag);
            if (!$addressId) {
                throw new \RuntimeException('A valid delivery address is required.');
            }

            // Scope this checkout to this seller only — see class docblock.
            Cart::where('customer_id', $ownerId)
                ->where('is_guest', $isGuestFlag)
                ->update(['is_checked' => 0]);

            $sellerCart = Cart::where('customer_id', $ownerId)
                ->where('is_guest', $isGuestFlag)
                ->where('seller_id', $context->sellerId)
                ->get();

            if ($sellerCart->isEmpty()) {
                throw new \RuntimeException('Cart is empty for this vendor.');
            }

            Cart::whereIn('id', $sellerCart->pluck('id'))->update(['is_checked' => 1]);

            // OrderManager::generateOrder()'s cart lookup (getVendorWiseCartList
            // -> CartManager::getCartListQuery() -> get_cart_group_ids()) resolves
            // "whose cart" from the container-bound *ambient* request/session —
            // not from the 'requestObj' passed in $data below (only the
            // top-level coupon/address/customer resolution honors that
            // override; see architecture doc Part II §10). In the real
            // controller flow $context->request already *is* the ambient
            // request, so this is normally a no-op — but guest_id must be
            // present on it (get_cart_group_ids() falls back to
            // session('guest_id') ?? request('guest_id')), and it must
            // actually be the container's bound request. Both are made
            // explicit here rather than assumed, since get_cart_group_ids()
            // silently resolves an empty cart instead of throwing when
            // either isn't true — the failure mode is a phantom empty
            // order, not an error.
            $context->request->merge(['guest_id' => $context->guestId]);
            app()->instance('request', $context->request);
            if ($context->isGuest) {
                session(['guest_id' => $context->guestId]);
            }

            $orderIds = OrderManager::generateOrder(data: [
                'is_guest' => $isGuestFlag,
                'guest_id' => $context->guestId,
                'customer_id' => $ownerId,
                'order_status' => 'pending',
                'payment_method' => $arguments['payment_method'] ?? 'cash_on_delivery',
                'payment_status' => 'unpaid',
                'transaction_ref' => '',
                'address_id' => $addressId,
                'billing_address_id' => $addressId,
                'coupon_code' => null,
                'order_note' => $arguments['order_note'] ?? '',
                'requestObj' => $context->request,
                // Stock is committed once the vendor actually delivers this
                // order (order_status -> 'delivered'), not the instant the
                // chatbot creates it — see OrderManager::addOrderDetailsData()
                // and OrderRepository::updateStockOnOrderStatusChange().
                'defer_stock_deduction' => true,
            ]);

            $orders = Order::whereIn('id', $orderIds)->where('seller_id', $context->sellerId)->get();

            if ($orders->isEmpty()) {
                // generateOrder() ran but produced nothing for this seller —
                // e.g. every item in the checked cart failed an active()/
                // stock/minimum-order check it enforces internally. Do not
                // mark the conversation confirmed with a null order group;
                // that would permanently block any further attempt via the
                // idempotency guard above without ever having placed an
                // order.
                throw new \RuntimeException('Order could not be created — please check product availability and try again.');
            }

            $orderGroupId = $orders->first()->order_group_id;

            $locked->update([
                'status' => 'confirmed',
                'checkout_confirmed_at' => now(),
                'confirmed_order_group_id' => $orderGroupId,
            ]);

            $customerName = $this->resolveCustomerName($arguments, $addressId);

            return [
                'order_ids' => $orders->pluck('id')->toArray(),
                'order_group_id' => $orderGroupId,
                'order_amount' => (float)$orders->sum('order_amount'),
                // Bypasses the Meta Cloud API entirely (WhatsAppService) —
                // the customer clicks this and sends it themselves from
                // their own WhatsApp, so it needs no vendor-side setup.
                'whatsapp_link' => $this->whatsAppLinkService->checkoutLink($orders, $customerName),
            ];
        });
    }

    private function resolveCustomerName(array $arguments, ?int $addressId): string
    {
        if (!empty($arguments['new_address']['contact_person_name'])) {
            return $arguments['new_address']['contact_person_name'];
        }

        return $addressId
            ? (ShippingAddress::find($addressId)?->contact_person_name ?? 'Customer')
            : 'Customer';
    }

    private function resolveAddressId(array $arguments, int|string $ownerId, int $isGuestFlag): ?int
    {
        if (!empty($arguments['address_id'])) {
            $owned = ShippingAddress::where('id', $arguments['address_id'])
                ->where('customer_id', $ownerId)
                ->where('is_guest', $isGuestFlag)
                ->exists();

            return $owned ? (int)$arguments['address_id'] : null;
        }

        if (!empty($arguments['new_address'])) {
            $new = $arguments['new_address'];
            $address = ShippingAddress::create([
                'customer_id' => $ownerId,
                'is_guest' => $isGuestFlag,
                'contact_person_name' => $new['contact_person_name'] ?? null,
                'phone' => $new['phone'] ?? null,
                'address' => $new['address'] ?? null,
                'city' => $new['city'] ?? null,
                'zip' => $new['zip'] ?? null,
                'country' => $new['country'] ?? null,
                'address_type' => 'home',
            ]);

            return $address->id;
        }

        return null;
    }

    private function existingOrderSummary(AIConversation $conversation): array
    {
        $orders = Order::where('order_group_id', $conversation->confirmed_order_group_id)->get();
        $address = (object)($orders->first()?->shipping_address_data ?? []);

        return [
            'order_ids' => $orders->pluck('id')->toArray(),
            'order_group_id' => $conversation->confirmed_order_group_id,
            'order_amount' => (float)$orders->sum('order_amount'),
            'already_confirmed' => true,
            'whatsapp_link' => $this->whatsAppLinkService->checkoutLink($orders, $address->contact_person_name ?? 'Customer'),
        ];
    }
}
