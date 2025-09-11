<?php

namespace Widia\Shipping\Payloads\FedEx;

use Widia\Shipping\Address\AddressFormatter;
use Widia\Shipping\Payloads\FedEx\Traits\Common;
class ShipmentPayloads
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
        $packages = $this->buildPackages($data['packages'], $returnShipment);
        $requestedShipment = [
            'shipper' => $this->formatAddress($shipper),
            'recipients' => [$this->formatAddress($recipient)],
            'pickupType' => 'USE_SCHEDULED_PICKUP',
            'serviceType' => $this->mapServiceType($recipient['service_type']),
            'packagingType' => 'YOUR_PACKAGING',
            'totalPackageCount' => count($packages),
            'requestedPackageLineItems' => $packages,
            'labelSpecification' => $this->getLabelSpecification(),
            'shippingChargesPayment' => $this->buildPaymentDetail($data, $shipper),
        ];

        //創建退貨標籤
        if ($returnShipment) {
            $requestedShipment = $this->returnShipment($requestedShipment, $data);
            $requestedShipment['blockInsightVisibility'] = false;
        }

        // 額外資訊 (notes, reference, signature, notify, 海關文件...) 
        $requestedShipment = $this->applyExtraOptions($requestedShipment, $data, $recipient);

        return [
            'requestedShipment' => $requestedShipment,
            'accountNumber' => ['value' => $data['account_number']],
            'labelResponseOptions' => 'URL_ONLY',
        ];
    }
    private function formatAddress(array $address): array
    {
        $formattedAddress = [
            'contact' => [
                'personName' => $address['contact']['personName'],
                'phoneNumber' => $address['contact']['phoneNumber'],
                'emailAddress' => $address['contact']['emailAddress']
            ],
            'address' => [
                'streetLines' => $address['address']['streetLines'],
                'city' => $address['address']['city'],
                'stateOrProvinceCode' => $address['address']['stateOrProvinceCode'],
                'postalCode' => $address['address']['postalCode'],
                'countryCode' => $address['address']['countryCode'],
                'residential' => $address['address']['isResidential']
            ]
        ];

        // 如果是住宅地址，确保使用 HOME_DELIVERY 服务
        if ($address['address']['isResidential'] && $address['address']['countryCode'] === 'US') {
            $formattedAddress['serviceType'] = 'HOME_DELIVERY';
        }

        return $formattedAddress;
    }
    private function buildPackages(array $packages, $returnShipment): array
    {
        $result = [];
        if (isset($packages['weight'], $packages['length'], $packages['width'], $packages['height'])) {
            $packages = [$packages];
        }

        foreach ($packages as $package) {
            $packageData = [
                'dimensions' => [
                    'units' => 'IN',
                    'length' => $package['length'],
                    'width' => $package['width'],
                    'height' => $package['height'],
                ],
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
    private function prepareCommodities(array $data): array
    {
        $commodities = [];

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $commodities[] = [
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'quantityUnits' => 'PCS',
                    'unitPrice' => [
                        'currency' => $data['currency'] ?? 'USD',
                        'amount' => $item['unitPrice'] ?? 0
                    ],
                    'customsValue' => [
                        'currency' => $data['currency'] ?? 'USD',
                        'amount' => ($item['unitPrice'] ?? 0) * ($item['quantity'] ?? 1)
                    ],
                    'weight' => [
                        'units' => 'LB',
                        'value' => $item['weight'] ?? 0
                    ],
                    'harmonizedCode' => $item['harmonizedCode'] ?? '',
                    'countryOfManufacture' => $item['countryOfManufacture'] ?? $data['shipper']['address']['countryCode'] ?? 'US'
                ];
            }
        } else {
            // 如果没有详细商品信息，使用默认值
            $commodities[] = [
                'description' => $data['description'] ?? 'General Merchandise',
                'quantity' => 1,
                'quantityUnits' => 'PCS',
                'unitPrice' => [
                    'currency' => $data['currency'] ?? 'USD',
                    'amount' => $data['customsValue'] ?? 0
                ],
                'customsValue' => [
                    'currency' => $data['currency'] ?? 'USD',
                    'amount' => $data['customsValue'] ?? 0
                ],
                'weight' => [
                    'units' => 'LB',
                    'value' => $data['weight'] ?? 0
                ],
                'harmonizedCode' => $data['harmonizedCode'] ?? '',
                'countryOfManufacture' => $data['countryOfManufacture'] ?? $data['shipper']['address']['countryCode'] ?? 'US'
            ];
        }

        return $commodities;
    }
}