<?php

namespace Modules\AIAssistant\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AIAssistant\app\Models\AIProvider;
use Modules\AIAssistant\app\Models\AIProviderConfig;
use Modules\AIAssistant\app\Models\AIProviderModel;
use Modules\AIAssistant\app\Services\AuditLogger;

/**
 * Platform-level provider/model/pricing configuration (brief item 3/10).
 * Credentials and prices live in these rows, never in application code —
 * see architecture doc Part II §5/§7.
 */
class AIProviderSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    private const KNOWN_PROVIDERS = [
        'deepseek' => 'DeepSeek',
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Gemini',
    ];

    public function index(): View
    {
        foreach (self::KNOWN_PROVIDERS as $key => $displayName) {
            AIProvider::firstOrCreate(['key' => $key], ['display_name' => $displayName, 'status' => 'disabled']);
        }

        $providers = AIProvider::with('models')->orderBy('display_name')->get();
        $configs = AIProviderConfig::with(['provider', 'model'])->get();

        return view('aiassistant::admin.providers.index', compact('providers', 'configs'));
    }

    public function updateProvider(Request $request, AIProvider $provider): RedirectResponse
    {
        $request->validate([
            'api_key' => ['nullable', 'string'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:connected,disabled'],
        ]);

        $provider->update($request->only(['api_key', 'base_url', 'status']));

        $adminId = auth('admin')->id();
        $this->auditLogger->log('admin', $adminId, null, 'ai_provider_changed', "Admin #{$adminId} updated platform provider {$provider->display_name} (status: {$request->input('status')}).");
        // Never log the API key value itself — only that it was changed.

        ToastMagic::success(translate('AI_provider_updated'));
        return back();
    }

    /**
     * Superadmin control surface (brief §29/§30): whether vendors may bring
     * their own key for this provider, and/or use the platform's own
     * credentials for it.
     */
    public function updateVendorAvailability(Request $request, AIProvider $provider): RedirectResponse
    {
        $request->validate([
            'vendor_owned_allowed' => ['nullable', 'boolean'],
            'vendor_managed_available' => ['nullable', 'boolean'],
        ]);

        $provider->update([
            'vendor_owned_allowed' => $request->boolean('vendor_owned_allowed'),
            'vendor_managed_available' => $request->boolean('vendor_managed_available'),
        ]);

        ToastMagic::success(translate('Vendor_availability_updated'));
        return back();
    }

    public function storeModel(Request $request): RedirectResponse
    {
        $request->validate([
            'ai_provider_id' => ['required', 'exists:ai_providers,id'],
            'model_name' => ['required', 'string', 'max:100'],
            'input_price' => ['required', 'numeric', 'min:0'],
            'output_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
        ]);

        AIProviderModel::create($request->only([
            'ai_provider_id', 'model_name', 'input_price', 'output_price', 'currency',
        ]) + ['active' => true]);

        ToastMagic::success(translate('Model_added'));
        return back();
    }

    public function setPlatformDefault(Request $request): RedirectResponse
    {
        $request->validate(['ai_provider_model_id' => ['required', 'exists:ai_provider_models,id']]);

        $model = AIProviderModel::findOrFail($request->ai_provider_model_id);

        AIProviderConfig::query()->update(['is_platform_default' => false]);

        AIProviderConfig::updateOrCreate(
            ['ai_provider_id' => $model->ai_provider_id, 'ai_provider_model_id' => $model->id],
            ['is_platform_default' => true],
        );

        $adminId = auth('admin')->id();
        $this->auditLogger->log('admin', $adminId, null, 'ai_model_changed', "Admin #{$adminId} set the platform default AI model to {$model->model_name}.");

        ToastMagic::success(translate('Platform_default_AI_model_updated'));
        return back();
    }
}
