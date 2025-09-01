<?php

namespace Widia\Shipping\Exceptions;

use Exception;

class CarrierNotFoundException extends Exception
{
    public function __construct(string $carrier, int $code = 0, Exception $previous = null)
    {
        $message = "Carrier '{$carrier}' not found or not supported.";
        parent::__construct($message, $code, $previous);
    }
} 