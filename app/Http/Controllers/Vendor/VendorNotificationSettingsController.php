<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorNotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * brief §9/§21. Only real, wired business events are exposed — see
 * config('notifications.event_labels') and the notification architecture
 * report for which events actually have a live trigger in this release.
 */
class VendorNotificationSettingsController extends Controller
{
    public function edit(): View
    {
        $sellerId = auth('seller')->id();
        $settings = VendorNotificationSetting::firstOrNew(['seller_id' => $sellerId]);
        $events = config('notifications.event_labels', []);
        $defaults = config('notifications.default_preferences', []);

        return view('vendor-views.notifications.settings', compact('settings', 'events', 'defaults'));
    }

    public function update(Request $request): RedirectResponse
    {
        $sellerId = auth('seller')->id();
        $events = array_keys(config('notifications.event_labels', []));
        $channels = ['in_app', 'pwa', 'whatsapp', 'email'];

        $preferences = [];
        foreach ($events as $event) {
            foreach ($channels as $channel) {
                $preferences[$event][$channel] = $request->boolean("{$event}_{$channel}");
            }
        }

        VendorNotificationSetting::updateOrCreate(['seller_id' => $sellerId], ['preferences' => $preferences]);

        return back()->with('success', translate('Notification_settings_updated'));
    }
}
