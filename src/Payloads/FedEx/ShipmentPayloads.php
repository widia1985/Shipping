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

        [$shipper, $recipient] = $this->formatAddress($data, $returnShipment);
        $data['service_type'] = $this->mapServiceType($data['service_type'], $recipient);

        $packages = $data['packages'] ?? $data['package'];
        $packages = $this->buildPackages($data['service_type'], $packages, $returnShipment);
        // 如果是住宅地址，确保使用 HOME_DELIVERY 服务
        // if (!$returnShipment && $recipient['address']['residential'] && $recipient['address']['countryCode'] === 'US') {
        //     $data['service_type'] = 'HOME_DELIVERY';
        // }
        $requestedShipment = [
            'shipper' => $shipper,
            'recipients' => [$recipient],
            'pickupType' => $data['pickup_type'] ?? 'USE_SCHEDULED_PICKUP',
            'serviceType' => $data['service_type'],
            'packagingType' => $data['packaging_type']??'YOUR_PACKAGING',
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

        if ($data['service_type'] == 'FEDEX_GROUND') {
            unset($requestedShipment['totalPackageCount']);
            if (!isset($requestedShipment['shipDatestamp'])) {
                $requestedShipment['shipDatestamp'] = date('Y-m-d');
            }
            if (!isset($requestedShipment['blockInsightVisibility'])) {
                $requestedShipment['blockInsightVisibility'] = false;
            }

        }

        $payload = [
            'requestedShipment' => $requestedShipment,
            'accountNumber' => ['value' => $data['account_number']],
            'labelResponseOptions' => $data['labelResponseOptions'] ?? 'URL_ONLY',
        ];

        if($data['labelResponseOptions'] =='LABEL' ){
            $payload['openShipmentAction'] = 'PROVIDE_DOCUMENTS_INCREMENTALLY';
        }

        return $payload;
    }
    private function formatAddress(array $data, $returnShipment): array
    {
        $shipper = $data['shipper'];
        $recipient = $data['recipient'] ?? $data['return_address'];

        $shipper['address']['residential'] = false;
        $recipient['address']['residential'] = false;
        if (isset($shipper['address']['isResidential'])) {
            $shipper['address']['residential'] = $shipper['address']['isResidential'];
            unset($shipper['address']['isResidential']);
        }
        if (isset($recipient['address']['isResidential'])) {
            $recipient['address']['residential'] = $recipient['address']['isResidential'];
            unset($recipient['address']['isResidential']);
        }

        $serviceType = $this->mapServiceType($data['service_type'], $recipient);
        if ($returnShipment || $serviceType === 'FEDEX_GROUND') {
            // 在退貨 (RETURN_SHIPMENT + PENDING/EMAIL) 場景時，API 會做更嚴格的驗證。
            unset($shipper['contact']['emailAddress']);
            unset($recipient['contact']['emailAddress']);
            unset($shipper['address']['residential']);
            unset($recipient['address']['residential']);
            $shipper['contact']['companyName'] = $address['contact']['companyName'] ?? 'N/A';
            $recipient['contact']['companyName'] = $address['contact']['companyName'] ?? 'N/A';
            if (count($recipient['address']['streetLines']) < 2) {
                $recipient['address']['streetLines'][] = 'N/A';
            }
        }
        return [$shipper, $recipient];
    }
    private function buildPackages(string $serviceType, array $packages, $returnShipment): array
    {
        $result = [];
        if (isset($packages['weight'], $packages['length'], $packages['width'], $packages['height'])) {
            $packages = [$packages];
        }

        foreach ($packages as $package) {
            $packageData = [
                'dimensions' => [
                    'units' => $package['dimensions_units']??'IN',
                    'length' => $package['length'],
                    'width' => $package['width'],
                    'height' => $package['height'],
                ],
                'weight' => [
                    'units' => $package['weight_units']??'LB',
                    'value' => $package['weight']
                ]
            ];
            if ($returnShipment) {
                /*注意：郵件標籤退貨貨件和地面建立標籤需要提供商品描述。*/
                $packageData['itemDescription'] = 'item description';
            }
            if ($serviceType == 'FEDEX_GROUND') {
                unset($packageData['dimensions']);
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
                    'quantityUnits' => $data['UnitOfMeasurement'] ?? 'PCS',
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
                'quantity' => $data['quantity'] ?? 0,
                'quantityUnits' => $data['UnitOfMeasurement'] ?? 'PCS',
                'unitPrice' => [
                    'currency' => $data['currency'] ?? 'USD',
                    'amount' => $data['unitPrice'] ?? 0
                ],
                'customsValue' => [
                    'currency' => $data['currency'] ?? 'USD',
                    'amount' => $data['unitPrice'] ?? 0
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