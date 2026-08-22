<?php

namespace Modules\AIAssistant\app\Models;

use App\Models\Seller;
use App\Models\Shop;
use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One row per vendor's assistant instance, keyed by seller_id — the same
 * tenant discriminator the rest of the marketplace already uses
 * (architecture doc Part I §4).
 */
class AIAgent extends Model
{
    use StorageTrait;

    public const BILLING_PLATFORM_DEFAULT = 'platform_default';
    public const BILLING_PLATFORM_MANAGED = 'platform_managed';
    public const BILLING_VENDOR_OWNED = 'vendor_owned';

    public const HANDLING_AI = 'ai';
    public const HANDLING_HUMAN = 'human';
    public const HANDLING_HYBRID = 'hybrid';

    protected $table = 'ai_agents';

    protected $fillable = [
        'seller_id',
        'shop_id',
        'status',
        'greeting',
        'ai_provider_config_id',
        'billing_mode',
        'vendor_ai_provider_id',
        'vendor_model_name',
        'bot_name',
        'bot_avatar',
        'bot_avatar_storage_type',
        'short_description',
        'handling_mode',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'shop_id' => 'integer',
        'status' => 'boolean',
        'ai_provider_config_id' => 'integer',
        'vendor_ai_provider_id' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(VendorAISetting::class, 'ai_agent_id');
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(AIProviderConfig::class, 'ai_provider_config_id');
    }

    public function vendorProvider(): BelongsTo
    {
        return $this->belongsTo(VendorAIProvider::class, 'vendor_ai_provider_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class, 'ai_agent_id');
    }

    /**
     * Kept for backward compatibility with the platform_default /
     * platform_managed billing modes — see AIProviderManager::resolveForAgent()
     * for the full resolution (including vendor_owned) that ConversationService
     * actually calls now.
     */
    public function resolvedProviderConfig(): ?AIProviderConfig
    {
        return $this->providerConfig ?? AIProviderConfig::platformDefault();
    }

    public function displayName(): string
    {
        return $this->bot_name ?: ($this->shop?->name ?: 'AI Assistant');
    }

    public function getBotAvatarFullUrlAttribute(): string|null|array
    {
        return $this->storageLink('ai-agents/avatar', $this->bot_avatar, $this->bot_avatar_storage_type ?? 'public');
    }
}
