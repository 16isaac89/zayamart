<?php

namespace Modules\AIAssistant\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Traits\FileManagerTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\AIAssistant\app\Http\Requests\Vendor\UpdateVendorAISettingsRequest;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\VendorAISetting;
use Modules\AIAssistant\app\Services\AuditLogger;

/**
 * Vendors edit structured settings only — never raw prompt text. See
 * PromptBuilder, which is the only thing that turns these fields into a
 * system prompt, and architecture doc Part II §6.
 *
 * Bot identity (brief §7) reuses the project's existing FileManagerTrait
 * upload convention — the same one Shop/ChattingService already use — not
 * a second upload system.
 */
class AIAgentSettingsController extends Controller
{
    use FileManagerTrait;

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function edit(): View
    {
        $sellerId = auth('seller')->id();

        $agent = AIAgent::firstOrCreate(
            ['seller_id' => $sellerId],
            ['shop_id' => Shop::where('seller_id', $sellerId)->value('id'), 'status' => false],
        );

        $settings = $agent->settings ?? VendorAISetting::create(['ai_agent_id' => $agent->id]);

        return view('aiassistant::vendor.edit', compact('agent', 'settings'));
    }

    public function update(UpdateVendorAISettingsRequest $request): RedirectResponse
    {
        $sellerId = auth('seller')->id();
        $agent = AIAgent::where('seller_id', $sellerId)->firstOrFail();

        $identity = [
            'status' => (bool)$request->boolean('status'),
            'greeting' => $request->input('greeting'),
            'bot_name' => $request->input('bot_name'),
            'short_description' => $request->input('short_description'),
            'handling_mode' => $request->input('handling_mode', AIAgent::HANDLING_AI),
        ];

        if ($request->hasFile('bot_avatar')) {
            // Not FileManagerTrait::update() — that method name collides
            // with this controller action's own name (update()), which
            // would silently shadow it. delete() + upload() replicate it
            // directly instead.
            if ($agent->bot_avatar) {
                $this->delete('ai-agents/avatar/' . $agent->bot_avatar);
            }
            $identity['bot_avatar'] = $this->upload('ai-agents/avatar/', 'webp', $request->file('bot_avatar'));
            $identity['bot_avatar_storage_type'] = config('filesystems.disks.default') ?? 'public';
        }

        $wasEnabled = $agent->status;
        $agent->update($identity);

        if ($wasEnabled !== $identity['status']) {
            $this->auditLogger->log('seller', $sellerId, $sellerId, $identity['status'] ? 'ai_assistant_enabled' : 'ai_assistant_disabled', "Vendor #{$sellerId} " . ($identity['status'] ? 'enabled' : 'disabled') . ' the AI assistant.');
        }

        VendorAISetting::updateOrCreate(
            ['ai_agent_id' => $agent->id],
            [
                'personality' => $request->input('personality'),
                'tone' => $request->input('tone'),
                'languages' => $request->input('languages', []),
                'business_description' => $request->input('business_description'),
                'delivery_policy' => $request->input('delivery_policy'),
                'return_policy' => $request->input('return_policy'),
                'payment_methods' => $request->input('payment_methods', []),
                'custom_instructions' => $request->input('custom_instructions'),
                'handoff_phrases' => $request->input('handoff_phrases', []),
            ],
        );

        return back()->with('success', translate('AI_assistant_settings_updated'));
    }
}
