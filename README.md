# Widia Shipping Package

A Laravel package for integrating multiple shipping carriers (FedEx, UPS, etc.).

## Requirements

- PHP >= 7.3
- Laravel >= 6.0

## Installation

You can install the package via composer:

```bash
composer require widia/shipping
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Widia\Shipping\ShippingServiceProvider"
```

This will create a `config/shipping.php` file in your config directory.

## Usage

```php
use Widia\Shipping\Facades\Shipping;

// Set carrier account
Shipping::setCarrier('fedex')->setAccount('your_account_number');

// Create shipping label
$label = Shipping::createLabel([
    'service_type' => 'GROUND',
    'shipper' => [
        'name' => 'John Doe',
        'address' => [
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ]
    ],
    'recipient' => [
        'name' => 'Jane Smith',
        'address' => [
            'street' => '456 Oak St',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'postal_code' => '90001',
            'country' => 'US'
        ]
    ],
    'package' => [
        'weight' => 10,
        'length' => 12,
        'width' => 8,
        'height' => 6
    ]
]);

$shipping = new Shipping();

$data = [
    'shipper' => [
        'name' => 'John Doe',
        'address' => [
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ]
    ],
    'recipient' => [
        'name' => 'Jane Smith',
        'address' => [
            'street' => '456 Oak St',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'postal_code' => '90001',
            'country' => 'US'
        ]
    ],
    'package' => [
        'weight' => 10,
        'length' => 12,
        'width' => 8,
        'height' => 6
    ]
];

// 只比较 FedEx 和 UPS
$comparison = $shipping->compareRates($data, ['fedex', 'ups']);

// 获取所有承运商中最便宜的选项
$cheapest = $shipping->getCheapestRate($data);

// 获取特定服务类型的最便宜选项
$cheapestGround = $shipping->getCheapestRateByService($data, 'GROUND');
```

## Supported Carriers

- FedEx
- UPS

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 

[
    'all_rates' => [
        'fedex' => [
            'GROUND' => [
                'carrier' => 'fedex',
                'service_type' => 'GROUND',
                'total_charge' => 15.99,
                'currency' => 'USD',
                'delivery_time' => '2024-03-25',
                'transit_time' => '3 days'
            ],
            // ... 其他服务类型
        ],
        'ups' => [
            'GROUND' => [
                'carrier' => 'ups',
                'service_type' => 'GROUND',
                'total_charge' => 14.99,
                'currency' => 'USD',
                'delivery_time' => '2024-03-24',
                'transit_time' => '2 days'
            ],
            // ... 其他服务类型
        ]
    ],
    'cheapest_rates' => [
        'GROUND' => [
            'carrier' => 'ups',
            'service_type' => 'GROUND',
            'total_charge' => 14.99,
            'currency' => 'USD',
            'delivery_time' => '2024-03-24',
            'transit_time' => '2 days'
        ],
        // ... 其他服务类型的最便宜选项
    ]
] 