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
        'contact' => [
            'personName' => env('SHIPPING_DEFAULT_SHIPPER_NAME', ''),
            'phoneNumber' => env('SHIPPING_DEFAULT_SHIPPER_PHONE', ''),
            'emailAddress' => env('SHIPPING_DEFAULT_SHIPPER_EMAIL', ''),
        ],
        'address' => [
            'streetLines' => [env('SHIPPING_DEFAULT_SHIPPER_STREET', '')],
            'city' => env('SHIPPING_DEFAULT_SHIPPER_CITY', ''),
            'stateOrProvinceCode' => env('SHIPPING_DEFAULT_SHIPPER_STATE', ''),
            'postalCode' => env('SHIPPING_DEFAULT_SHIPPER_POSTAL_CODE', ''),
            'countryCode' => env('SHIPPING_DEFAULT_SHIPPER_COUNTRY', 'US'),
        ],
        'isResidential' => false,
    ],

    'default_return_address' => [
        'contact' => [
            'personName' => env('SHIPPING_DEFAULT_RETURN_NAME', ''),
            'phoneNumber' => env('SHIPPING_DEFAULT_RETURN_PHONE', ''),
            'emailAddress' => env('SHIPPING_DEFAULT_RETURN_EMAIL', ''),
        ],
        'address' => [
            'streetLines' => [env('SHIPPING_DEFAULT_RETURN_STREET', '')],
            'city' => env('SHIPPING_DEFAULT_RETURN_CITY', ''),
            'stateOrProvinceCode' => env('SHIPPING_DEFAULT_RETURN_STATE', ''),
            'postalCode' => env('SHIPPING_DEFAULT_RETURN_POSTAL_CODE', ''),
            'countryCode' => env('SHIPPING_DEFAULT_RETURN_COUNTRY', 'US'),
        ],
        'isResidential' => false,
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
        'fedex' => [
            'test_url' => 'https://apis-sandbox.fedex.com',
            'live_url' => 'https://apis.fedex.com',
        ],
        'ups' => [
            'use_negotiated_rates' => env('UPS_USE_NEGOTIATED_RATES', true),
            'test_url' => 'https://wwwcie.ups.com',
            'live_url' => 'https://onlinetools.ups.com',
        ],
    ],
]; 