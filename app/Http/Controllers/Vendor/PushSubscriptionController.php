<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * brief §6/§7/§8: multi-device, authorized server-side only. A vendor
 * manages only their own subscriptions — auth('seller')->id() scopes every
 * query here; nothing is ever trusted from request input.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'device_type' => ['nullable', 'string', 'max:20'],
        ]);

        $sellerId = auth('seller')->id();
        $token = $request->input('token');

        VendorPushSubscription::updateOrCreate(
            ['token_hash' => VendorPushSubscription::hashToken($token)],
            [
                'seller_id' => $sellerId,
                'fcm_token' => $token,
                'device_type' => $request->input('device_type', 'web'),
                'user_agent' => mb_substr((string)$request->userAgent(), 0, 250),
                'last_active_at' => now(),
            ],
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        VendorPushSubscription::where('seller_id', auth('seller')->id())
            ->where('token_hash', VendorPushSubscription::hashToken($request->input('token')))
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }
}
