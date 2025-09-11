<?php

namespace Widia\Shipping\Payloads\FedEx;

class CancelPayloads
{
    public function build(string $accountNumber, string $trackingNumber): array
    {
        return [
            'accountNumber' => ['value' => $accountNumber],
            'emailShipment' => 'false',
            'senderCountryCode' => 'US',
            'deletionControl' => 'DELETE_ALL_PACKAGES',
            'trackingNumber' => $trackingNumber,
            'version1' => [
                'major' => '1',
                'minor' => '1',
                'patch' => '1'
            ]
        ];
    }
}