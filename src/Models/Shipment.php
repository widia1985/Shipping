<?php

namespace Widia\Shipping\Models;

use Widia\Shipping\Contracts\CarrierInterface;

class Shipment
{
    protected CarrierInterface $carrier;
    protected array $order;

    public function __construct(array $order)
    {
        $this->order = $order;
        $this->carrier = $this->resolveCarrier();
    }

    public function setAccount(string $accountNumber): self
    {
        $this->carrier->setAccount($accountNumber);
        return $this;
    }

    public function createLabel(): array
    {
        return $this->carrier->createLabel($this->prepareData());
    }

    public function getRates(): array
    {
        return $this->carrier->getRates($this->prepareData());
    }

    public function cancelLabel(string $trackingNumber): bool
    {
        return $this->carrier->cancelLabel($trackingNumber);
    }

    public function trackShipment(string $trackingNumber): array
    {
        return $this->carrier->trackShipment($trackingNumber);
    }

    protected function resolveCarrier(): CarrierInterface
    {
        $carrierClass = 'Widia\\Shipping\\Carriers\\' . ucfirst($this->order['carrier']);
        return new $carrierClass();
    }

    protected function prepareData(): array
    {
        return [
            'shipper' => $this->order['shipper'],
            'recipient' => $this->order['recipient'],
            'service_type' => $this->order['service_type'],
            'weight' => $this->order['weight'],
            'length' => $this->order['length'],
            'width' => $this->order['width'],
            'height' => $this->order['height'],
        ];
    }
} 