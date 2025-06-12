<?php

namespace Widia\Shipping;

use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('shipping', function ($app) {
            return new ShippingManager($app);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/shipping.php' => config_path('shipping.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__.'/../config/shipping.php', 'shipping'
        );
    }
} 