<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vendor-owned WhatsApp Cloud API credentials. WhatsAppService checks for a
 * row here before falling back to the platform's config/services.php
 * credentials — see app/Services/WhatsAppService.php.
 */
class VendorWhatsAppSetting extends Model
{
    protected $table = 'vendor_whatsapp_settings';

    protected $fillable = [
        'seller_id',
        'whatsapp_provider',
        'access_token',
        'phone_number_id',
        'status',
        'last_tested_at',
        'last_test_message',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'access_token' => 'encrypted',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
