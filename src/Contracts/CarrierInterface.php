<?php

namespace Widia\Shipping\Contracts;

interface CarrierInterface
{
    public function createLabel(array $data): array;
    public function getRates(array $data): array;
    public function cancelLabel(string $trackingNumber): bool;
    public function trackShipment(string $trackingNumber): array;
} 