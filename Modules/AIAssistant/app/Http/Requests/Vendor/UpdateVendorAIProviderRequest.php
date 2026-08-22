<?php

namespace Modules\AIAssistant\app\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorAIProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('seller')->check();
    }

    public function rules(): array
    {
        return [
            'billing_mode' => ['required', 'in:platform_default,platform_managed,vendor_owned'],
            'ai_provider_config_id' => ['nullable', 'required_if:billing_mode,platform_managed', 'integer'],
            'ai_provider_id' => ['nullable', 'required_if:billing_mode,vendor_owned', 'integer'],
            'vendor_model_name' => ['nullable', 'required_if:billing_mode,vendor_owned', 'string', 'max:100'],
        ];
    }
}
