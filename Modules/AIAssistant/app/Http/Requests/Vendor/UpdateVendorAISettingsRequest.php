<?php

namespace Modules\AIAssistant\app\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorAISettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('seller')->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'boolean'],
            'greeting' => ['nullable', 'string', 'max:500'],
            'personality' => ['nullable', 'string', 'in:friendly,professional,premium,casual'],
            'tone' => ['nullable', 'string', 'max:100'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:50'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            'delivery_policy' => ['nullable', 'string', 'max:2000'],
            'return_policy' => ['nullable', 'string', 'max:2000'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:100'],
            'custom_instructions' => ['nullable', 'string', 'max:3000'],
            'bot_name' => ['nullable', 'string', 'max:50'],
            'bot_avatar' => ['nullable', 'image', 'max:2048'],
            'short_description' => ['nullable', 'string', 'max:150'],
            'handling_mode' => ['nullable', 'in:ai,human,hybrid'],
            'handoff_phrases' => ['nullable', 'array'],
            'handoff_phrases.*' => ['string', 'max:100'],
        ];
    }
}
