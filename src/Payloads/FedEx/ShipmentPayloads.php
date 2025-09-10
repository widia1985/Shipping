<?php

namespace Widia\Shipping\Payloads\FedEx;

use Widia\Shipping\Address\AddressFormatter;
use Widia\Shipping\Payloads\FedEx\Traits\Common;
class ShipmentPayloads
{
    use Common;
    protected $addressFormatter;
    protected $formatAddress;
    public function __construct(
        AddressFormatter $addressFormatter
    ) {
        $this->addressFormatter = $addressFormatter;
    }
    public function build(array $data, $returnShipment = false): array
    {
        $defaultShipper = config('shipping.default_shipper');
        $data['shipper'] = array_merge($defaultShipper, $data['shipper'] ?? []);

        $recipient = $data['recipient'] ?? $data['return_address'];
        $formattedAddress = $this->addressFormatter->format($recipient, $data['service_type']);

        if (isset($recipient['contact'])) {
            $formattedAddress['contact'] = $recipient['contact'];
        }
        $packages = self::buildPackages($data['packages'], $returnShipment);
        $requestedShipment = [
            'shipper' => $this->formatAddress($data['shipper']),
            'recipients' => [$this->formatAddress($formattedAddress)],
            'pickupType' => 'USE_SCHEDULED_PICKUP',
            'serviceType' => $this->mapServiceType($formattedAddress['service_type']),
            'packagingType' => 'YOUR_PACKAGING',
            'totalPackageCount' => count($packages),
            'requestedPackageLineItems' => $packages,
            'labelSpecification' => [
                'imageType' => 'PDF',
                'labelStockType' => 'PAPER_4X6'
            ],
            'shippingChargesPayment' => self::buildPaymentDetail($data, $data['shipper']),
        ];

        //創建退貨標籤
        if ($returnShipment) {
            $requestedShipment = self::returnShipment($requestedShipment, $data);
            $requestedShipment['blockInsightVisibility'] = false;
        }

        // 額外資訊 (notes, reference, signature, notify, 海關文件...) 
        $requestedShipment = self::applyExtraOptions($requestedShipment, $data, $formattedAddress);

        return [
            'requestedShipment' => $requestedShipment,
            'accountNumber' => ['value' => $data['account_number']],
            'labelResponseOptions' => 'URL_ONLY',
        ];
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
    private function buildPaymentDetail(array $data, array $shipper, string $defaultType = 'SENDER'): array
    {
        $paymentType = $data['third_party_account'] ?? false
            ? 'THIRD_PARTY'   // 如果使用第三方帳戶，請新增第三方帳戶資訊
            : ($data['duties_payment_type'] ?? $defaultType);

        $accountNumber = $paymentType === 'THIRD_PARTY'
            ? $data['third_party_account']
            : $data['account_number'];

        $payor = [
            'responsibleParty' => [
                'accountNumber' => ['value' => $accountNumber]
            ]
        ];

        // RECIPIENT / THIRD_PARTY / COLLECT 都必須帶 address
        if (in_array($paymentType, ['RECIPIENT', 'THIRD_PARTY', 'COLLECT'])) {
            $payor['responsibleParty']['address'] = $shipper['address'];
        } else {
            $payor['address'] = $shipper['address'];
        }

        return [
            'paymentType' => $paymentType,
            'payor' => $payor
        ];
    }
    private function applyExtraOptions(array $shipment, array $data, array $recipient): array
    {
        // Department notes
        // if (!empty($data['DepartmentNotes'])) {
        //     $shipment['departmentNotes'] = $data['DepartmentNotes'];
        // }

        // Reference
        if (!empty($data['Reference'])) {
            // $shipment['shipmentSpecialServices']['specialServiceTypes'][] = 'REFERENCE';
            $shipment['shipmentSpecialServices']['reference'] = [
                'referenceType' => 'CUSTOMER_REFERENCE',
                'value' => $data['Reference']
            ];
        }

        // Signature
        if (!empty($data['signature_required'])) {
            foreach ($shipment['requestedPackageLineItems'] as $index => $package) {
                $shipment['requestedPackageLineItems'][$index]['packageSpecialServices'] = [
                    'specialServiceTypes' => ['SIGNATURE_OPTION'],
                    'signatureOptionType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                ];
            }
        }
        // Notification
        if (!empty($data['ship_notify'])) {

            foreach ($data['ship_notify'] as $notify) {
                if (empty($notify['ship_notify_email']))
                    continue;

                $shipment['emailNotificationDetail']['aggregationType'] = "PER_PACKAGE";
                $shipment['emailNotificationDetail']['emailNotificationRecipients'][] = [
                    "name" => $recipient['contact']['personName'],
                    "emailNotificationRecipientType" => "SHIPPER",
                    "emailAddress" => $notify["ship_notify_email"],
                    "notificationFormatType" => "TEXT",
                    "notificationType" => "EMAIL",
                    "locale" => "en_US",
                    "notificationEventType" => [
                        "ON_PICKUP_DRIVER_ARRIVED",
                        "ON_SHIPMENT"
                    ]
                ];
            }

        }

        // 國際訂單邏輯
        if (
            !empty($data['shipper']['address']['countryCode']) &&
            $this->isInternationalShipment($data['shipper']['address']['countryCode'], $recipient['address']['countryCode'])
        ) {
            $shipment['customsClearanceDetail'] = [
                'dutiesPayment' => [
                    'paymentType' => 'RECIPIENT' // 默认收货方支付清关税
                ],
                'commodities' => $this->prepareCommodities($data),
            ];

            // 如果特别指定清关税支付方
            if (!empty($data['duties_payment_type'])) {
                switch ($data['duties_payment_type']) {
                    case 'SHIPPER':
                        $shipment['customsClearanceDetail']['dutiesPayment']['paymentType'] = 'SENDER';
                        break;
                    case 'THIRD_PARTY':
                        if (!empty($data['third_party_account'])) {
                            $shipment['customsClearanceDetail']['dutiesPayment'] = [
                                'paymentType' => 'THIRD_PARTY',
                                'payor' => [
                                    'responsibleParty' => [
                                        'accountNumber' => [
                                            'value' => $data['third_party_account']
                                        ],
                                        'address' => [
                                            'streetLines' => $shipment['shipper']['address']['streetLines'],
                                            'city' => $shipment['shipper']['address']['city'],
                                            'stateOrProvinceCode' => $shipment['shipper']['address']['stateOrProvinceCode'],
                                            'postalCode' => $shipment['shipper']['address']['postalCode'],
                                            'countryCode' => $shipment['shipper']['address']['countryCode']
                                        ]
                                    ]
                                ]
                            ];
                        }
                        break;
                }
            }

            // 添加商业发票和形式发票文档
            $shipment['shippingDocumentSpecification'] = [
                'shippingDocumentTypes' => ['COMMERCIAL_INVOICE', 'PRO_FORMA_INVOICE'],
                'commercialInvoiceDetail' => [
                    'documentFormat' => [
                        'dispositions' => [
                            [
                                'dispositionType' => 'EMAILED',
                                'emailDetail' => [
                                    'emailRecipients' => [
                                        [
                                            'emailAddress' => $data['shipper']['contact']['emailAddress'] ?? '',
                                            'emailAddressType' => 'SHIPPER'
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'dispositionType' => 'PRINTED', // 添加打印选项
                                'emailDetail' => [
                                    'emailRecipients' => [
                                        [
                                            'emailAddress' => $data['shipper']['contact']['emailAddress'] ?? '',
                                            'emailAddressType' => 'SHIPPER'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // 如果进口商信息与收货人不同，添加进口商信息
            if (!empty($data['importer']) && $this->isImporterDifferent($data['importer'], $recipient)) {
                $shipment['customsClearanceDetail']['importerOfRecord'] = [
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

        return $shipment;
    }
    private function returnShipment(array $shipment, array $data): array
    {
        $shipment['shipmentSpecialServices'] = [
            'specialServiceTypes' => [
                'RETURN_SHIPMENT'
            ],
            'returnShipmentDetail' => [
                'returnEmailDetail' => [
                    'merchantPhoneNumber' => $data['return_address']['contact']['phoneNumber'],
                ],
                'rma' => [
                    'reason' => $data['return_reason']
                ],
                'returnType' => 'PENDING',
            ],
            'pendingShipmentDetail' => [
                'pendingShipmentType' => 'EMAIL',
                'emailLabelDetail' => [
                    'recipients' => [
                        [
                            'emailAddress' => $data['return_address']['contact']['emailAddress'],
                            'role' => 'SHIPMENT_COMPLETOR',
                        ]
                    ]
                ],
                'expirationTimeStamp' => $data['expiration_time']
            ]

        ];

        return $shipment;
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