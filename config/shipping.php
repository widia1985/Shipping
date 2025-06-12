<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipper Information
    |--------------------------------------------------------------------------
    |
    | This is the default shipper information that will be used when creating
    | shipping labels if no shipper information is provided.
    |
    */
    'default_shipper' => [
        'name' => env('SHIPPING_SHIPPER_NAME', ''),
        'address' => [
            'street' => env('SHIPPING_SHIPPER_STREET', ''),
            'city' => env('SHIPPING_SHIPPER_CITY', ''),
            'state' => env('SHIPPING_SHIPPER_STATE', ''),
            'postal_code' => env('SHIPPING_SHIPPER_POSTAL_CODE', ''),
            'country' => env('SHIPPING_SHIPPER_COUNTRY', 'US'),
        ],
        'phone' => env('SHIPPING_SHIPPER_PHONE', ''),
        'email' => env('SHIPPING_SHIPPER_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the database tables and models to use for storing shipping data.
    |
    */
    'database' => [
        'tables' => [
            'shipping_labels' => 'shipping_labels',
            'tokens' => 'token',
            'applications' => 'application',
        ],
        'models' => [
            'shipping_label' => \Widia\Shipping\Models\ShippingLabel::class,
            'token' => \Widia\Shipping\Models\Token::class,
            'application' => \Widia\Shipping\Models\Application::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Carrier
    |--------------------------------------------------------------------------
    |
    | This is the default carrier that will be used when no carrier is specified.
    |
    */
    'default_carrier' => env('SHIPPING_DEFAULT_CARRIER', 'fedex'),

    'carriers' => [
        'fedex' => [],
        'ups' => [
            'use_negotiated_rates' => env('UPS_USE_NEGOTIATED_RATES', true),
        ],
    ],
]; 