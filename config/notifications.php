<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform default notification preferences (brief §23)
    |--------------------------------------------------------------------------
    |
    | Applied whenever a vendor hasn't set their own preference for an
    | event/channel pair (VendorNotificationSetting::isEnabled()). In-app,
    | PWA, and email default on (email has no per-message cost to the
    | vendor); WhatsApp defaults off (it's an optional, vendor-owned-cost
    | channel — see the notification architecture report).
    |
    */
    'default_preferences' => [
        'new_order' => ['in_app' => true, 'pwa' => true, 'whatsapp' => false, 'email' => true],
        'payment_received' => ['in_app' => true, 'pwa' => true, 'whatsapp' => false, 'email' => true],
        'order_status_changed' => ['in_app' => true, 'pwa' => false, 'whatsapp' => false, 'email' => false],
        'customer_needs_help' => ['in_app' => true, 'pwa' => true, 'whatsapp' => false, 'email' => false],
        'low_stock' => ['in_app' => true, 'pwa' => false, 'whatsapp' => false, 'email' => false],
        'system_alert' => ['in_app' => true, 'pwa' => false, 'whatsapp' => false, 'email' => false],
        'real_estate_inquiry' => ['in_app' => true, 'pwa' => true, 'whatsapp' => false, 'email' => false],
    ],

    'event_labels' => [
        'new_order' => 'New_Order',
        'payment_received' => 'Payment_Received',
        'order_status_changed' => 'Order_Status_Changed',
        'customer_needs_help' => 'Customer_Needs_Help',
        'low_stock' => 'Low_Stock',
        'system_alert' => 'System_Alerts',
        'real_estate_inquiry' => 'Real_Estate_Inquiry',
    ],
];
