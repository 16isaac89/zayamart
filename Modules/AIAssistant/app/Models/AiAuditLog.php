<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;

class AiAuditLog extends Model
{
    protected $table = 'ai_audit_logs';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'seller_id',
        'event_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'seller_id' => 'integer',
        'metadata' => 'array',
    ];
}
