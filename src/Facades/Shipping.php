<?php

namespace Widia\Shipping\Facades;

use Illuminate\Support\Facades\Facade;
use Widia\Shipping\Shipping as ShippingClass;

class Shipping extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'shipping';
    }

    public static function compareRates(array $data, array $carriers = [])
    {
        return static::getFacadeRoot()->compareRates($data, $carriers);
    }

    public static function getRates(array $data)
    {
        return static::getFacadeRoot()->getRates($data);
    }

    public static function createLabel(array $data)
    {
        return static::getFacadeRoot()->createLabel($data);
    }

    public static function cancelLabel(string $trackingNumber)
    {
        return static::getFacadeRoot()->cancelLabel($trackingNumber);
    }

    public static function trackShipment(string $trackingNumber)
    {
        return static::getFacadeRoot()->trackShipment($trackingNumber);
    }

    public static function getCheapestRate(array $data, array $carriers = [])
    {
        return static::getFacadeRoot()->getCheapestRate($data, $carriers);
    }

    public static function getCheapestRateByService(array $data, string $serviceType, array $carriers = [])
    {
        return static::getFacadeRoot()->getCheapestRateByService($data, $serviceType, $carriers);
    }
} 