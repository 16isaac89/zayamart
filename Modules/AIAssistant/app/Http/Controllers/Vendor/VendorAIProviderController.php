<?php

namespace Modules\AIAssistant\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AIAssistant\app\Http\Requests\Vendor\UpdateVendorAIProviderRequest;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIProvider;
use Modules\AIAssistant\app\Models\AIProviderConfig;
use Modules\AIAssistant\app\Models\VendorAIProvider;
use Modules\AIAssistant\app\Services\AIProviderConnectionTester;
use Modules\AIAssistant\app\Services\AuditLogger;

/**
 * "Vendor must be able to choose how their AI is powered" — brief §2/§4.
 * Three billing modes: platform_default, platform_managed, vendor_owned.
 * See architecture doc Part III §1 and AIProviderManager::resolveForAgent().
 */
class VendorAIProviderController extends Controller
{
    public function __construct(
        private readonly AIProviderConnectionTester $tester,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function edit(): View
    {
        $sellerId = auth('seller')->id();
        $agent = AIAgent::firstOrCreate(['seller_id' => $sellerId]);

        // Comparison table (brief §5): only providers vendors are actually
        // allowed to touch, with pricing pulled from the real catalog —
        // never hard-coded.
        $availableProviders = AIProvider::with(['models' => fn ($q) => $q->where('active', true)])
            ->where(function ($q) {
                $q->where('vendor_owned_allowed', true)->orWhere('vendor_managed_available', true);
            })
            ->get();

        $platformManagedConfigs = AIProviderConfig::with(['provider', 'model'])
            ->whereHas('provider', fn ($q) => $q->where('vendor_managed_available', true)->where('status', 'connected'))
            ->get();

        $vendorProviders = VendorAIProvider::with('provider')->where('seller_id', $sellerId)->get();
        $platformDefault = AIProviderConfig::platformDefault();

        return view('aiassistant::vendor.ai-provider.edit', compact(
            'agent', 'availableProviders', 'platformManagedConfigs', 'vendorProviders', 'platformDefault',
        ));
    }

    public function update(UpdateVendorAIProviderRequest $request): RedirectResponse
    {
        $sellerId = auth('seller')->id();
        $agent = AIAgent::where('seller_id', $sellerId)->firstOrFail();

        $data = ['billing_mode' => $request->input('billing_mode')];

        if ($request->input('billing_mode') === 'platform_managed') {
            $config = AIProviderConfig::whereHas('provider', fn ($q) => $q->where('vendor_managed_available', true))
                ->findOrFail($request->input('ai_provider_config_id'));
            $data['ai_provider_config_id'] = $config->id;
            $data['vendor_ai_provider_id'] = null;
            $data['vendor_model_name'] = null;
        } elseif ($request->input('billing_mode') === 'vendor_owned') {
            $vendorProvider = VendorAIProvider::where('seller_id', $sellerId)
                ->where('id', $request->input('ai_provider_id'))
                ->firstOrFail();
            $data['vendor_ai_provider_id'] = $vendorProvider->id;
            $data['vendor_model_name'] = $request->input('vendor_model_name');
            $data['ai_provider_config_id'] = null;
        } else {
            $data['ai_provider_config_id'] = null;
            $data['vendor_ai_provider_id'] = null;
            $data['vendor_model_name'] = null;
        }

        $agent->update($data);

        $this->auditLogger->log('seller', $sellerId, $sellerId, 'ai_provider_changed', "Vendor #{$sellerId} switched AI billing mode to {$data['billing_mode']}.");
        ToastMagic::success(translate('AI_provider_updated'));

        return back();
    }

    public function storeCredentials(Request $request): RedirectResponse
    {
        $request->validate([
            'ai_provider_id' => ['required', 'exists:ai_providers,id'],
            'api_key' => ['required', 'string'],
            'base_url' => ['nullable', 'string', 'max:255'],
        ]);

        $sellerId = auth('seller')->id();
        $provider = AIProvider::where('id', $request->input('ai_provider_id'))->where('vendor_owned_allowed', true)->firstOrFail();

        VendorAIProvider::updateOrCreate(
            ['seller_id' => $sellerId, 'ai_provider_id' => $provider->id],
            [
                'api_key' => $request->input('api_key'),
                'base_url' => $request->input('base_url'),
                'status' => 'disabled', // must pass a connection test before it can be selected
            ],
        );

        $this->auditLogger->log('seller', $sellerId, $sellerId, 'vendor_api_key_saved', "Vendor #{$sellerId} saved credentials for {$provider->display_name}.");
        // Never log/echo the key itself — see class docblocks across this feature.

        ToastMagic::success(translate('API_key_saved'));

        return back();
    }

    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'ai_provider_id' => ['required', 'exists:ai_providers,id'],
            'model' => ['required', 'string', 'max:100'],
        ]);

        $sellerId = auth('seller')->id();
        $vendorProvider = VendorAIProvider::where('seller_id', $sellerId)
            ->where('ai_provider_id', $request->input('ai_provider_id'))
            ->firstOrFail();

        $result = $this->tester->test($vendorProvider->provider, $vendorProvider->api_key, $vendorProvider->base_url, $request->input('model'));

        $vendorProvider->update([
            'status' => $result['success'] ? 'connected' : 'error',
            'last_tested_at' => now(),
            'last_test_message' => $result['message'],
        ]);

        return response()->json($result);
    }
}
