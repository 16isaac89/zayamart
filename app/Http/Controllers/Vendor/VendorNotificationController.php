<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notification Center — brief §19. Every query is scoped to
 * auth('seller')->id(); a notification id belonging to another vendor
 * simply doesn't resolve (firstOrFail() 404s), never leaks or updates.
 */
class VendorNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = auth('seller')->id();
        $filter = $request->input('filter', 'all');

        $notifications = VendorNotification::where('seller_id', $sellerId)
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = VendorNotification::where('seller_id', $sellerId)->whereNull('read_at')->count();

        return view('vendor-views.notifications.index', compact('notifications', 'filter', 'unreadCount'));
    }

    /** Lightweight JSON used by the bell-icon dropdown / polling. */
    public function recent(): JsonResponse
    {
        $sellerId = auth('seller')->id();

        return response()->json([
            'unread_count' => VendorNotification::where('seller_id', $sellerId)->whereNull('read_at')->count(),
            'notifications' => VendorNotification::where('seller_id', $sellerId)
                ->latest()
                ->limit(10)
                ->get(['id', 'type', 'title', 'message', 'action_url', 'read_at', 'created_at']),
        ]);
    }

    public function markRead(int $notification): RedirectResponse
    {
        $notification = VendorNotification::where('id', $notification)->where('seller_id', auth('seller')->id())->firstOrFail();
        $notification->update(['read_at' => now()]);

        return $notification->action_url ? redirect($notification->action_url) : back();
    }

    public function markAllRead(): RedirectResponse
    {
        VendorNotification::where('seller_id', auth('seller')->id())->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
