@extends('layouts.vendor.app')

@section('title', translate('AI_Assistant'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('AI_Assistant') }}</h2>
            <a class="btn btn-primary px-3 rounded" href="{{ route('vendor.ai-assistant.dashboard') }}">
                <i class="fi fi-rr-chart-histogram"></i>
                <span class="d-none d-md-block">{{ translate('Dashboard') }}</span>
            </a>
        </div>

        <form action="{{ route('vendor.ai-assistant.update') }}" method="post" enctype="multipart/form-data" novalidate>
            @csrf

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Status') }}</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" value="1"
                               {{ $agent->status ? 'checked' : '' }}>
                        <label class="form-check-label" for="status">{{ translate('Enable_AI_assistant_for_customers') }}</label>
                    </div>
                </div>
            </div>

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('AI_Assistant_Identity') }}</h5>
                    <p class="text-muted mb-0 small">{{ translate('How_your_assistant_appears_to_customers_no_longer_has_to_be_generic_AI_Assistant') }}</p>
                </div>
                <div class="card-body row g-3 align-items-start">
                    <div class="col-md-2 text-center">
                        <img src="{{ getStorageImages(path: $agent->bot_avatar_full_url, type: 'backend-profile') }}"
                             class="rounded-circle border" width="80" height="80" style="object-fit: cover;" alt="">
                        <label class="form-label d-block mt-2 small">{{ translate('Avatar') }}</label>
                        <input type="file" class="form-control form-control-sm" name="bot_avatar" accept="image/*">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ translate('Bot_Name') }}</label>
                        <input type="text" class="form-control" name="bot_name" maxlength="50" value="{{ $agent->bot_name }}"
                               placeholder="{{ translate('e.g._Sarah') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ translate('Short_Description') }}</label>
                        <input type="text" class="form-control" name="short_description" maxlength="150" value="{{ $agent->short_description }}"
                               placeholder="{{ translate('e.g._Your_personal_shopping_assistant') }}">
                    </div>
                </div>
            </div>

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Who_handles_conversations') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach(['ai' => 'AI_handles_all_conversations', 'hybrid' => 'AI_handles_conversations_you_can_take_over_anytime', 'human' => 'You_and_your_team_handle_all_conversations'] as $mode => $label)
                            <div class="col-md-4">
                                <div class="form-check border rounded p-3 h-100">
                                    <input class="form-check-input" type="radio" name="handling_mode" id="handling_mode_{{ $mode }}"
                                           value="{{ $mode }}" {{ $agent->handling_mode == $mode ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold text-uppercase small" for="handling_mode_{{ $mode }}">{{ translate($mode) }}</label>
                                    <p class="small text-muted mb-0">{{ translate($label) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Personality') }}</h5>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Personality') }}</label>
                        <select class="form-select" name="personality">
                            @foreach(['friendly', 'professional', 'premium', 'casual'] as $option)
                                <option value="{{ $option }}" {{ $settings->personality == $option ? 'selected' : '' }}>
                                    {{ translate(ucfirst($option)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Tone') }}</label>
                        <input type="text" class="form-control" name="tone" value="{{ $settings->tone }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Languages') }}</label>
                        <select class="form-select" name="languages[]" multiple>
                            @foreach(['English', 'Luganda', 'Swahili'] as $language)
                                <option value="{{ $language }}" {{ in_array($language, $settings->languages ?? []) ? 'selected' : '' }}>
                                    {{ $language }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ translate('Greeting') }}</label>
                        <input type="text" class="form-control" name="greeting" maxlength="500" value="{{ $agent->greeting }}"
                               placeholder="{{ translate('e.g._Hi!_Welcome_to_our_shop,_how_can_I_help_you_today') }}">
                    </div>
                </div>
            </div>

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Business_Knowledge') }}</h5>
                    <p class="text-muted mb-0 small">
                        {{ translate('Products,_prices_and_stock_come_from_your_catalog_automatically') }} —
                        <a href="{{ route('vendor.ai-assistant.knowledge.index') }}">{{ translate('upload_documents_for_anything_else') }}</a>
                    </p>
                </div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label">{{ translate('Business_description') }}</label>
                        <textarea class="form-control" name="business_description" rows="3" maxlength="2000">{{ $settings->business_description }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Delivery_policy') }}</label>
                        <textarea class="form-control" name="delivery_policy" rows="3" maxlength="2000">{{ $settings->delivery_policy }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Return_policy') }}</label>
                        <textarea class="form-control" name="return_policy" rows="3" maxlength="2000">{{ $settings->return_policy }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ translate('Accepted_payment_methods') }}</label>
                        <input type="text" class="form-control" name="payment_methods_raw"
                               value="{{ implode(', ', $settings->payment_methods ?? []) }}"
                               placeholder="{{ translate('Comma_separated,_e.g._Cash_on_delivery,_Mobile_money') }}">
                        {{-- Kept as a single comma-separated field for a simple v1 form; split client-side into array inputs on submit. --}}
                    </div>
                </div>
            </div>

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Human_Handoff') }}</h5>
                    <p class="text-muted mb-0 small">{{ translate('The_assistant_always_offers_to_connect_a_human_when_a_customer_asks_add_your_own_trigger_phrases_below') }}</p>
                </div>
                <div class="card-body">
                    <label class="form-label">{{ translate('Custom_escalation_phrases') }}</label>
                    <input type="text" class="form-control" name="handoff_phrases_raw"
                           value="{{ implode(', ', $settings->handoff_phrases ?? []) }}"
                           placeholder="{{ translate('Comma_separated,_e.g._manager,_complaint') }}">
                </div>
            </div>

            <div class="card mb-3 mb-lg-5">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Custom_Instructions') }}</h5>
                    <p class="text-muted mb-0 small">
                        {{ translate('These_are_preferences_only_they_cannot_override_pricing_stock_or_security_rules') }}
                    </p>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="custom_instructions" rows="4" maxlength="3000"
                              placeholder="{{ translate('e.g._Always_ask_for_shoe_size_before_confirming_footwear_orders') }}">{{ $settings->custom_instructions }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary px-4">{{ translate('save') }}</button>
        </form>
    </div>
@endsection

@push('script')
    <script>
        // Comma-separated inputs -> real array fields on submit.
        document.querySelector('form[action="{{ route('vendor.ai-assistant.update') }}"]').addEventListener('submit', function () {
            const fields = [
                {raw: 'payment_methods_raw', name: 'payment_methods[]'},
                {raw: 'handoff_phrases_raw', name: 'handoff_phrases[]'},
            ];
            fields.forEach(({raw, name}) => {
                const el = document.querySelector(`[name=${raw}]`);
                if (!el) return;
                el.value.split(',').map(v => v.trim()).filter(Boolean).forEach((value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    this.appendChild(input);
                });
            });
        });
    </script>
@endpush
