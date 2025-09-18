<?php
namespace Widia\Shipping\Payloads\FedEx\Traits;
trait Common
{
    private function initAddress(array $data): array
    {

        $defaultShipper = config('shipping.default_shipper');
        $data['shipper'] = array_merge($defaultShipper, $data['shipper'] ?? []);

        $recipient = $data['recipient'] ?? $data['return_address'];
        $formattedAddress = $this->addressFormatter->format($recipient, $data['service_type']);

        if (isset($recipient['contact'])) {
            $formattedAddress['contact'] = $recipient['contact'];
        }

        return [$data['shipper'], $formattedAddress];
    }

    private function getLabelSpecification(): array
    {
        return [
            'imageType' => 'PDF',
            'labelStockType' => 'STOCK_4X6'
            /*Enum: "PAPER_4X6" "STOCK_4X675" "PAPER_4X675" "PAPER_4X8" "PAPER_4X9" "PAPER_7X475" "PAPER_85X11_BOTTOM_HALF_LABEL" "PAPER_85X11_TOP_HALF_LABEL" "PAPER_LETTER" "STOCK_4X675_LEADING_DOC_TAB" "STOCK_4X8" "STOCK_4X9_LEADING_DOC_TAB" "STOCK_4X6" "STOCK_4X675_TRAILING_DOC_TAB" "STOCK_4X9_TRAILING_DOC_TAB" "STOCK_4X9" "STOCK_4X85_TRAILING_DOC_TAB" "STOCK_4X105_TRAILING_DOC_TAB"
            */
        ];
    }


    private function isImporterDifferent(array $importer, array $recipient): bool
    {
        return $importer['contact']['personName'] !== $recipient['contact']['personName']
            || $importer['address']['streetLines'] !== $recipient['address']['streetLines']
            || $importer['address']['city'] !== $recipient['address']['city']
            || $importer['address']['stateOrProvinceCode'] !== $recipient['address']['stateOrProvinceCode']
            || $importer['address']['postalCode'] !== $recipient['address']['postalCode']
            || $importer['address']['countryCode'] !== $recipient['address']['countryCode'];
    }
    private function isInternationalShipment(string $shipperCountry, string $recipientCountry): bool
    {
        return $shipperCountry !== $recipientCountry;
    }
    private function mapSignatureType(string $type): string
    {
        $mapping = [
            'DIRECT' => 'DIRECT', // 直接签名
            'INDIRECT' => 'INDIRECT', // 间接签名
            'ADULT' => 'ADULT', // 成人签名
            'SIGNATURE_REQUIRED' => 'DIRECT', // 需要签名
            'SIGNATURE_NOT_REQUIRED' => 'NO_SIGNATURE_REQUIRED', // 不需要签名
            'NO_SIGNATURE_REQUIRED' => 'NO_SIGNATURE_REQUIRED', // 不需要签名
            'SIGNATURE_REQUIRED_INDIRECT' => 'INDIRECT', // 间接签名
            'SIGNATURE_REQUIRED_ADULT' => 'ADULT', // 成人签名
        ];

        return $mapping[$type] ?? 'DIRECT'; // 默认需要直接签名
    }
    private function mapServiceType(string $serviceType, array $recipient): string
    {
        return $serviceType;
        if (empty($serviceType) || !is_string($serviceType)) {
            $serviceType = 'GROUND SERVICE';
        }
        // 标准化服务类型名称
        $serviceType = $this->normalizeServiceType($serviceType);

        // 获取收件人国家代码和住宅地址标志
        $recipientCountry = $recipient['address']['countryCode'] ?? '';
        $isResidential = (isset($recipient['address']['residential'])) ? $recipient['address']['residential'] : false;
        $isInternational = $recipientCountry !== 'US';

        // 国际运输服务类型映射
        if ($isInternational) {
            $internationalMapping = [
                'INTERNATIONAL_PRIORITY',
                'INTERNATIONAL_PRIORITY_FREIGHT',
                'INTERNATIONAL_ECONOMY_FREIGHT',
                'INTERNATIONAL_ECONOMY'
            ];
            return in_array($serviceType, $internationalMapping) ? $serviceType : 'INTERNATIONAL_ECONOMY';
        }
        // 国内运输服务类型映射
        $domesticMapping = [
            'FIRST_OVERNIGHT',
            'PRIORITY_OVERNIGHT',
            'STANDARD_OVERNIGHT',
            'FEDEX_2_DAY_AM',
            'FEDEX_2_DAY',
            'FEDEX_EXPRESS_SAVER',
            'FEDEX_FIRST_FREIGHT',
            'FEDEX_1_DAY_FREIGHT',
            'FEDEX_2_DAY_FREIGHT',
            'FEDEX_3_DAY_FREIGHT',
            'SMART_POST',
            'THIRD_PARTY_CONSIGNEE',
            'GROUND_HOME_DELIVERY',
        ];
        // 如果找到匹配的国内服务类型，返回它
        if (in_array($serviceType, $domesticMapping)) {
            return $serviceType;
        }

        // 如果没有匹配的服务类型，根据住宅地址标志返回默认服务
        return $isResidential ? 'GROUND_HOME_DELIVERY' : 'FEDEX_GROUND';
    }
    private function normalizeServiceType(string $serviceType): string
    {
        // 转换为大写并移除多余的空格
        $serviceType = trim(strtoupper($serviceType));

        // 服务类型名称映射
        $normalizedTypes = config('serviceTypes.fedex');
        foreach ($normalizedTypes as $standard => $aliases) {
            if ($serviceType === $standard || in_array($serviceType, array_map('strtoupper', $aliases))) {
                return $standard;
            }
        }

        // 如果没有找到匹配，尝试移除所有空格和特殊字符后匹配
        $cleanType = preg_replace('/[^A-Z0-9]/', '', $serviceType);
        foreach ($normalizedTypes as $key => $value) {
            if (preg_replace('/[^A-Z0-9]/', '', $key) === $cleanType) {
                return $value;
            }
        }

        // 如果仍然没有匹配，返回原始输入
        return $serviceType;
    }
    private function mapReasonForExport(string $reason): string
    {
        $mapping = [
            'SALE' => 'SALE',
            'GIFT' => 'GIFT',
            'SAMPLE' => 'SAMPLE',
            'RETURN' => 'RETURN',
            'REPAIR' => 'REPAIR',
            'INTERCOMPANY' => 'INTERCOMPANY',
            'PERSONAL_EFFECTS' => 'PERSONAL_EFFECTS',
            'OTHER' => 'OTHER',
            // 添加更多映射...
        ];

        return $mapping[strtoupper($reason)] ?? 'SALE'; // 默认返回 SALE
    }
    private function mapCurrencyCode(string $currency): string
    {
        $mapping = [
            'USD' => 'USD', // 美元
            'EUR' => 'EUR', // 欧元
            'GBP' => 'GBP', // 英镑
            'CAD' => 'CAD', // 加拿大元
            'AUD' => 'AUD', // 澳大利亚元
            'JPY' => 'JPY', // 日元
            'CNY' => 'CNY', // 人民币
            'HKD' => 'HKD', // 港币
            'SGD' => 'SGD', // 新加坡元
            'MXN' => 'MXN', // 墨西哥比索
            'BRL' => 'BRL', // 巴西雷亚尔
            'INR' => 'INR', // 印度卢比
            'RUB' => 'RUB', // 俄罗斯卢布
            'KRW' => 'KRW', // 韩元
            'TWD' => 'TWD', // 新台币
            'THB' => 'THB', // 泰铢
            'MYR' => 'MYR', // 马来西亚林吉特
            'IDR' => 'IDR', // 印尼盾
            'PHP' => 'PHP', // 菲律宾比索
            'VND' => 'VND', // 越南盾
            // 添加更多货币代码...
        ];

        return $mapping[strtoupper($currency)] ?? 'USD'; // 默认返回 USD
    }
    private function returnShipment(array $shipment, array $data): array
    {
        if (isset($data['recipient'])) {
            $recipient = $data['recipient'];
        } else {
            $recipient = $data['return_address'];
        }
        if ($data['return_type'] == 'PRINT_RETURN_LABEL') {
            $shipment['shipmentSpecialServices'] = [
                'specialServiceTypes' => [
                    'RETURN_SHIPMENT'
                ],
                'returnShipmentDetail' => [
                    'returnType' => 'PRINT_RETURN_LABEL',
                ],
            ];
        } else {
            $shipment['shipmentSpecialServices'] = [
                'specialServiceTypes' => [
                    'RETURN_SHIPMENT'
                ],
                'returnShipmentDetail' => [
                    'returnEmailDetail' => [
                        'merchantPhoneNumber' => $recipient['contact']['phoneNumber'],
                    ],
                    'rma' => [
                        'reason' => $data['return_reason'] ?? 'none'
                    ],
                    'returnAssociationDetail' => [
                        'trackingNumber' => $data['original_tracking_number'],
                        'shipDatestamp' => $data['ship_datestamp'],
                    ],
                    'returnType' => 'PENDING',
                ],
                'pendingShipmentDetail' => [
                    'pendingShipmentType' => 'EMAIL',
                    'emailLabelDetail' => [
                        'recipients' => [
                            [
                                'emailAddress' => $recipient['contact']['emailAddress'],
                                'role' => 'SHIPMENT_COMPLETOR',
                                //有效值：SHIPMENT_COMPLETOR，SHIPMENT_INITIATOR
                                //SHIPMENT_COMPLETOR → 完成貨件的人（通常是實際會 列印標籤並寄出 的收件人）。
                                //SHIPMENT_INITIATOR → 建立貨件的人（通常是 建立寄件請求 的人）。
                            ]
                        ]
                    ],
                    'expirationTimeStamp' => $data['expiration_time'],
                ]
            ];
        }

        return $shipment;
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
            // $payor['address'] = $shipper['address'];
        }

        if ($data['service_type'] == 'FEDEX_GROUND') {
            return [
                'paymentType' => $paymentType
            ];
        } else {
            return [
                'paymentType' => $paymentType,
                'payor' => $payor
            ];
        }
    }
}