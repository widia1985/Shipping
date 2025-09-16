<?php

namespace Widia\Shipping;

use Illuminate\Support\ServiceProvider;
use Widia\Shipping\Shipping;

class ShippingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('shipping', function ($app) {
            return new Shipping();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/shipping.php' => config_path('shipping.php'),
            __DIR__ . '/config/serviceTypes.php' => config_path('serviceTypes.php'),
            __DIR__ . '/config/fedex.php' => config_path('fedex.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/config/shipping.php',
            'shipping'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/config/serviceTypes.php',
            'serviceTypes'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/config/fedex.php',
            'fedex'
        );

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/shipping.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shipping');
    }
}