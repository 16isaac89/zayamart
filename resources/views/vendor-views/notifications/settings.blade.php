@extends('layouts.vendor.app')

@section('title', translate('Notification_Settings'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('Notification_Settings') }}</h2>

        <div class="card mb-3 mb-lg-5">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('PWA_Push_Notifications') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">{{ translate('Get_instant_order_alerts_on_this_device_even_when_the_dashboard_is_closed') }}</p>
                <button type="button" id="js-enable-push" class="btn btn-primary">{{ translate('Enable_notifications_on_this_device') }}</button>
                <span id="js-push-status" class="ms-2 small"></span>
            </div>
        </div>

        <form method="post" action="{{ route('vendor.notifications.settings.update') }}">
            @csrf
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Event_Preferences') }}</h5>
                    <p class="small text-muted mb-0">{{ translate('Choose_which_channels_notify_you_for_each_event') }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ translate('Event') }}</th>
                            <th class="text-center">{{ translate('In_App') }}</th>
                            <th class="text-center">{{ translate('PWA') }}</th>
                            <th class="text-center">{{ translate('WhatsApp') }}</th>
                            <th class="text-center">{{ translate('Email') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($events as $eventKey => $label)
                            <tr>
                                <td>{{ translate($label) }}</td>
                                @foreach(['in_app', 'pwa', 'whatsapp', 'email'] as $channel)
                                    @php($checked = data_get($settings->preferences, "{$eventKey}.{$channel}", data_get($defaults, "{$eventKey}.{$channel}", true)))
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" name="{{ $eventKey }}_{{ $channel }}" value="1" {{ $checked ? 'checked' : '' }}>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('save') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/js/vendor-push.js') }}"></script>
@endpush
