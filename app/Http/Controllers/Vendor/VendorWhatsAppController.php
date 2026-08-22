<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorWhatsAppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Modules\AIAssistant\app\Services\AuditLogger;

/**
 * Vendor-owned WhatsApp Cloud API credentials — brief §21. Kept in
 * app/Http/Controllers/Vendor (not the AIAssistant module) since WhatsApp
 * notifications fire for every order regardless of channel, not just
 * AI-originated ones — see WhatsAppService.
 */
class VendorWhatsAppController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function edit(): View
    {
        $settings = VendorWhatsAppSetting::firstOrNew(['seller_id' => auth('seller')->id()]);

        return view('aiassistant::vendor.whatsapp.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'access_token' => ['nullable', 'string'],
            'phone_number_id' => ['nullable', 'string', 'max:50'],
        ]);

        $sellerId = auth('seller')->id();

        $data = ['phone_number_id' => $request->input('phone_number_id')];
        if ($request->filled('access_token')) {
            $data['access_token'] = $request->input('access_token');
            $data['status'] = 'disabled'; // must pass a connection test before use
        }

        VendorWhatsAppSetting::updateOrCreate(['seller_id' => $sellerId], $data);

        $this->auditLogger->log('seller', $sellerId, $sellerId, 'whatsapp_configuration_changed', "Vendor #{$sellerId} updated their WhatsApp configuration.");

        return back()->with('success', translate('WhatsApp_settings_updated'));
    }

    public function testConnection(): JsonResponse
    {
        $sellerId = auth('seller')->id();
        $settings = VendorWhatsAppSetting::where('seller_id', $sellerId)->firstOrFail();

        if (!$settings->access_token || !$settings->phone_number_id) {
            return response()->json(['success' => false, 'message' => 'Enter an access token and phone number ID first.']);
        }

        try {
            $response = Http::withToken($settings->access_token)
                ->timeout(15)
                ->get("https://graph.facebook.com/v20.0/{$settings->phone_number_id}");

            $success = $response->successful();
            $message = $success ? 'Connection successful.' : 'Authentication failed — check the access token and phone number ID.';
        } catch (\Throwable $exception) {
            $success = false;
            $message = 'Could not reach WhatsApp Cloud API.';
        }

        $settings->update([
            'status' => $success ? 'connected' : 'error',
            'last_tested_at' => now(),
            'last_test_message' => $message,
        ]);

        return response()->json(['success' => $success, 'message' => $message]);
    }
}
