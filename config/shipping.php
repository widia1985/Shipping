<?php

return [
    'default' => env('SHIPPING_DEFAULT', 'fedex'),

    'carriers' => [
        'fedex' => [
            'client_id' => env('FEDEX_CLIENT_ID'),
            'client_secret' => env('FEDEX_CLIENT_SECRET'),
            'account_number' => env('FEDEX_ACCOUNT_NUMBER'),
            'sandbox' => env('FEDEX_SANDBOX', true),
        ],
        'ups' => [
            'client_id' => env('UPS_CLIENT_ID'),
            'client_secret' => env('UPS_CLIENT_SECRET'),
            'account_number' => env('UPS_ACCOUNT_NUMBER'),
            'sandbox' => env('UPS_SANDBOX', true),
        ],
    ],
]; 