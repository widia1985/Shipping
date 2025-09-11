<?php

namespace Widia\Shipping\Payloads\FedEx;

use Widia\Shipping\Address\AddressFormatter;
use Widia\Shipping\Payloads\FedEx\Traits\Common;
use DateTime;
class TagPayloads
{
    use Common;
    protected $addressFormatter;
    public function __construct(
        AddressFormatter $addressFormatter
    ) {
        $this->addressFormatter = $addressFormatter;
    }
    public function build(array $data, $returnShipment = false): array
    {

        [$shipper, $recipient] = $this->initAddress($data);
        $packages = $this->tagPackages($data['packages'], $returnShipment);
        $shipDate = new DateTime($data['shipDatestamp']);
        $readyPickup = clone $shipDate;
        $latestPickup = clone $shipDate;
        $readyPickup->setTime(9, 0, 0);  // 設定為 09:00:00
        $latestPickup->setTime(14, 0, 0); // 設定為 14:00:00
        $requestedShipment = [
            'shipper' => [
                'contact' => [
                    'personName' => $shipper['contact']['personName'],
                    'phoneNumber' => $shipper['contact']['phoneNumber'],
                ],
                'address' => [
                    'streetLines' => $shipper['address']['streetLines'],
                    'city' => $shipper['address']['city'],
                    'stateOrProvinceCode' => $shipper['address']['stateOrProvinceCode'],
                    'postalCode' => $shipper['address']['postalCode'],
                    'countryCode' => $shipper['address']['countryCode'],
                ]
            ],
            'recipients' => [
                [
                    'contact' => [
                        'personName' => $recipient['contact']['personName'],
                        'phoneNumber' => $recipient['contact']['phoneNumber'],
                    ],
                    'address' => [
                        'streetLines' => $recipient['address']['streetLines'],
                        'city' => $recipient['address']['city'],
                        'stateOrProvinceCode' => $recipient['address']['stateOrProvinceCode'],
                        'postalCode' => $recipient['address']['postalCode'],
                        'countryCode' => $recipient['address']['countryCode'],
                    ]
                ]
            ],
            "shipDatestamp" => $data['shipDatestamp'],
            'pickupType' => 'CONTACT_FEDEX_TO_SCHEDULE',
            'serviceType' => $this->mapServiceType($recipient['service_type']),
            'packagingType' => 'YOUR_PACKAGING',
            // 'labelSpecification' => $this->getLabelSpecification(),
            'shippingChargesPayment' => $this->buildPaymentDetail($data, $shipper),
            'shipmentSpecialServices' => [
                'specialServiceTypes' => [
                    'RETURN_SHIPMENT'
                ],
                'returnShipmentDetail' => [
                    'returnType' => 'FEDEX_TAG'
                ],
            ],
            'blockInsightVisibility' => false,
            'pickupDetail' => [
                'readyPickupDateTime' => $readyPickup->format('Y-m-d\TH:i:s\Z'),
                'latestPickupDateTime' => $latestPickup->format('Y-m-d\TH:i:s\Z'),
            ],
            'requestedPackageLineItems' => $packages,
        ];

        //創建退貨標籤
        if ($returnShipment) {
            $requestedShipment = $this->returnShipment($requestedShipment, $data);
            $requestedShipment['blockInsightVisibility'] = false;
        }

        $requestedShipment = $this->applyExtraOptions($requestedShipment, $data, $recipient);

        return [
            'requestedShipment' => $requestedShipment,
            'accountNumber' => ['value' => $data['account_number']],
        ];

    }
    private function tagPackages(array $packages, $returnShipment): array
    {
        $result = [];
        if (isset($packages['weight'], $packages['length'], $packages['width'], $packages['height'])) {
            $packages = [$packages];
        }

        foreach ($packages as $package) {
            $packageData = [
                'weight' => [
                    'units' => 'LB',
                    'value' => $package['weight']
                ]
            ];
            if ($returnShipment) {
                $packageData['itemDescription'] = 'Return item description';
            }

            $result[] = $packageData;
        }

        return $result;
    }
    public function cancel(array $data): array
    {
        return [
            'accountNumber' => ['value' => $data['account_number']],
            "serviceType" => "PRIORITY_OVERNIGHT",
            "trackingNumber" => $data['trackingNumber'],
            "completedTagDetail" => [
                "confirmationNumber" => "275",
                "location" => "NQAA",
                "dispatchDate" => "2019-08-03"
            ]
        ];
    }


}