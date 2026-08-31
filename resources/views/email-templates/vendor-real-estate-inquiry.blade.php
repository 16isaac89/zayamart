<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>{{ translate('New_real_estate_inquiry') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/email-basic.css') }}">
</head>
<body>

<?php
$companyEmail = getWebConfig(name: 'company_email');
$companyName = getWebConfig(name: 'company_name');
$companyLogo = getWebConfig(name: 'company_web_logo');
?>

<div class="d-flex justify-content-center align-items-center m-auto vh-100">
    <div class="card">
        <div class="m-auto bg-white pt-40px pb-40px text-center">
            <div class="d-block">
                <div class="d-flex justify-content-center align-items-center gap-1">
                    <img src="{{ getStorageImages(path: $companyLogo, type: 'backend-logo') }}" alt="{{ $companyName }}"
                         class="width-auto h-50px">
                    {{ $companyName }}
                </div>
            </div>
        </div>
        <div class="card-header mb-3 text-center">
            <h3 class="pb-20px">{{ translate('New_real_estate_inquiry') }}</h3>
        </div>
        <div class="card-body text-start">
            <p><strong>{{ translate('Name') }}:</strong> {{ $guestName }}</p>
            <p><strong>{{ translate('Phone') }}:</strong> {{ $guestPhone }}</p>
            @if($guestEmail)
                <p><strong>{{ translate('Email') }}:</strong> {{ $guestEmail }}</p>
            @endif

            <p><strong>{{ translate('Message') }}:</strong></p>
            <p>{{ $inquiryMessage }}</p>

            <p><strong>{{ translate('Listings') }}:</strong></p>
            <ul>
                @foreach($listings as $listing)
                    <li>{{ $listing['title'] }} ({{ $currencySymbol }}{{ number_format($listing['price'], 2) }}) &mdash; {{ $listing['url'] }}</li>
                @endforeach
            </ul>

            <p class="text-center pt-20px">
                {{ translate('Log_in_to_your_vendor_panel_to_view_and_respond_to_this_inquiry') }}.
                <br/>
                {{ translate('From') }} {{ $companyName }}
            </p>
        </div>
    </div>
</div>
</body>
</html>
