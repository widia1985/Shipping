<?php

namespace Widia\Shipping\Payloads\FedEx;

use Widia\Shipping\Address\AddressFormatter;
use Widia\Shipping\Payloads\FedEx\Traits\Common;
class RatePayloads
{
    use Common;
    protected $addressFormatter;
    public function __construct(
        AddressFormatter $addressFormatter
    ) {
        $this->addressFormatter = $addressFormatter;
    }

    public function build(array $data): array
    {
        // 获取默认发件人信息
        $defaultShipper = config('shipping.default_shipper');

        // 合并默认发件人信息和传入的数据
        $data['shipper'] = array_merge($defaultShipper, $data['shipper'] ?? []);

        $data['service_type'] = isset($data['service_type']) ?: null;
        $formattedAddress = $this->addressFormatter->format($data['recipient'], $data['service_type']);

        // 获取格式化后的地址
        //$shipperAddress = $this->formatAddress($formattedAddress['shipper']);
        // $recipientAddress = $this->formatAddress($formattedAddress['recipient']);

        // 确定最终的服务类型
        $serviceType = $this->mapServiceType($formattedAddress['service_type']);

        // 处理包裹数据
        $packageLineItems = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            // 多个包裹的情况
            $index = 0;
            foreach ($data['packages'] as $package) {
                $index++;
                $packageLineItems[] = [
                    'sequenceNumber' => $index,
                    'weight' => [
                        'units' => 'LB',
                        'value' => (float) $package['weight']
                    ],
                    'dimensions' => [
                        'length' => (float) $package['length'],
                        'width' => (float) $package['width'],
                        'height' => (float) $package['height'],
                        'units' => 'IN'
                    ]
                ];
            }
        } else {
            // 单个包裹的情况
            $packageLineItems[] = [
                'sequenceNumber' => 1,
                'weight' => [
                    'units' => 'LB',
                    'value' => (float) $data['package']['weight']
                ],
                'dimensions' => [
                    'length' => (float) $data['package']['length'],
                    'width' => (float) $data['package']['width'],
                    'height' => (float) $data['package']['height'],
                    'units' => 'IN'
                ]
            ];
        }

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            /*$requestedShipment['shipmentSpecialServices'] = [
                'specialServiceTypes' => ['SIGNATURE_OPTION'],
                'signatureOptionDetail' => [
                    'optionType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                ]
            ];*/
            $signatureOptionType = $this->mapSignatureType($data['signature_type'] ?? 'DIRECT');
            foreach ($packageLineItems as $key => $item) {
                $packageLineItems[$key]['packageSpecialServices'] = [
                    'signatureOptionType' => $signatureOptionType
                ];
            }
        }

        unset($formattedAddress['service_type']);
        $formattedAddress['address']['residential'] = False;
        if ($formattedAddress['address']['isResidential']) {
            $formattedAddress['address']['residential'] = True;
        }
        $requestedShipment = [
            'shipper' => $data['shipper'],
            'recipient' => $formattedAddress,
            'pickupType' => 'USE_SCHEDULED_PICKUP',
            'rateRequestType' => ["LIST", "ACCOUNT"],
            //'serviceType' => $serviceType,
            'packagingType' => 'YOUR_PACKAGING',
            'requestedPackageLineItems' => $packageLineItems
        ];

        // 如果存在DepartmentNotes，添加到请求中
        if (!empty($data['DepartmentNotes'])) {
            $requestedShipment['departmentNotes'] = $data['DepartmentNotes'];
        }

        // 如果存在Reference，添加到请求中
        if (!empty($data['Reference'])) {
            $requestedShipment['shipmentSpecialServices'] = array_merge(
                $requestedShipment['shipmentSpecialServices'] ?? ['specialServiceTypes' => []],
                [
                    'specialServiceTypes' => array_merge(
                        $requestedShipment['shipmentSpecialServices']['specialServiceTypes'] ?? [],
                        ['REFERENCE']
                    ),
                    'reference' => [
                        'referenceType' => 'CUSTOMER_REFERENCE',
                        'value' => $data['Reference']
                    ]
                ]
            );
        }

        // 国际订单处理
        if ($this->isInternationalShipment($data['shipper']['address']['countryCode'], $formattedAddress['address']['countryCode'])) {
            $requestedShipment['customsClearanceDetail'] = [
                'dutiesPayment' => [
                    'paymentType' => 'RECIPIENT' // 默认收货方支付清关税
                ]
            ];

            // 如果特别指定清关税支付方
            if (!empty($data['duties_payment_type'])) {
                switch ($data['duties_payment_type']) {
                    case 'SHIPPER':
                        $requestedShipment['customsClearanceDetail']['dutiesPayment']['paymentType'] = 'SENDER';
                        break;
                    case 'THIRD_PARTY':
                        if (!empty($data['third_party_account'])) {
                            $requestedShipment['customsClearanceDetail']['dutiesPayment'] = [
                                'paymentType' => 'THIRD_PARTY',
                                'payor' => [
                                    'responsibleParty' => [
                                        'accountNumber' => [
                                            'value' => $data['third_party_account']
                                        ],
                                        'address' => [
                                            'streetLines' => $formattedAddress['shipper']['address']['streetLines'],
                                            'city' => $formattedAddress['shipper']['address']['city'],
                                            'stateOrProvinceCode' => $formattedAddress['shipper']['address']['stateOrProvinceCode'],
                                            'postalCode' => $formattedAddress['shipper']['address']['postalCode'],
                                            'countryCode' => $formattedAddress['shipper']['address']['countryCode']
                                        ]
                                    ]
                                ]
                            ];
                        }
                        break;
                }
            }

            // 如果进口商信息与收货人不同，添加进口商信息
            if (!empty($data['importer']) && $this->isImporterDifferent($data['importer'], $formattedAddress['recipient'])) {
                $requestedShipment['customsClearanceDetail']['importerOfRecord'] = [
                    'contact' => [
                        'personName' => $data['importer']['contact']['personName'],
                        'phoneNumber' => $data['importer']['contact']['phoneNumber'],
                        'emailAddress' => $data['importer']['contact']['emailAddress']
                    ],
                    'address' => [
                        'streetLines' => $data['importer']['address']['streetLines'],
                        'city' => $data['importer']['address']['city'],
                        'stateOrProvinceCode' => $data['importer']['address']['stateOrProvinceCode'],
                        'postalCode' => $data['importer']['address']['postalCode'],
                        'countryCode' => $data['importer']['address']['countryCode']
                    ],
                    'accountNumber' => [
                        'value' => $data['importer']['account_number'] ?? ''
                    ],
                    'tins' => [
                        [
                            'number' => $data['importer']['tax_id'] ?? '',
                            'tinType' => 'BUSINESS_NATIONAL'
                        ],
                        [
                            'number' => $data['importer']['vat_number'] ?? '',
                            'tinType' => 'BUSINESS_VAT'
                        ]
                    ]
                ];
            }
        }
        //print_r($requestedShipment);
        if ($data['account_number']) {
            return [
                'accountNumber' => ['value' => $data['account_number']],
                'requestedShipment' => $requestedShipment
            ];
        } else {
            return [
                'requestedShipment' => $requestedShipment
            ];
        }
    }
}