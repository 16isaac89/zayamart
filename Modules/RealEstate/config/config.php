<?php

return [
    'name' => 'RealEstate',

    /*
    |--------------------------------------------------------------------------
    | Amenities checklist
    |--------------------------------------------------------------------------
    |
    | Shown as checkboxes on the listing create/edit form and stored as
    | {key: true} pairs in real_estate_listings.amenities. Editing this list
    | only changes what a broker can pick going forward — it never rewrites
    | amenities already saved on existing listings.
    |
    */
    'amenities' => [
        'pool' => 'Swimming pool',
        'garden' => 'Garden',
        'security' => '24/7 security',
        'generator' => 'Backup generator',
        'elevator' => 'Elevator',
        'air_conditioning' => 'Air conditioning',
        'pet_friendly' => 'Pet friendly',
        'balcony' => 'Balcony',
        'gym' => 'Gym',
        'water_supply' => 'Borehole / reliable water supply',
    ],

    'area_units' => ['sqft', 'sqm', 'acre', 'hectare'],
];
