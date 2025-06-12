<?php

namespace Widia\Shipping\Carriers;

use Widia\Shipping\Models\Token;
use Widia\Shipping\Address\AddressFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Carbon\Carbon;
use Widia\Shipping\Models\ShippingLabel;

class UPS extends AbstractCarrier
{
    protected string $test_url = 'https://wwwcie.ups.com/api';
    protected string $live_url = 'https://onlinetools.ups.com/api';
    protected string $url;
    protected ?Token $token = null;
    protected AddressFormatter $addressFormatter;
    protected array $internationalServices = [
        'WORLDWIDE_EXPRESS',
        'WORLDWIDE_EXPEDITED',
        'STANDARD',
    ];

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setAccount(string $accountNumber): self
    {
        $this->token = Token::where('accountname', $accountNumber)
            ->whereHas('application', function ($query) {
                $query->where('carrier', 'ups');
            })
            ->first();

        if (!$this->token) {
            throw new \Exception("UPS account not found: {$accountNumber}");
        }

        $application = $this->token->application;
        if (!$application) {
            throw new \Exception("UPS application not found for account: {$accountNumber}");
        }

        $this->url = $application->sandbox ? $this->test_url : $this->live_url;
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => $this->getHeaders(),
        ]);

        // 初始化地址格式化器
        $this->addressFormatter = new AddressFormatter(
            $this->client,
            '/addressvalidation/v1/validation',
            $this->internationalServices,
            'UPS'
        );

        return $this;
    }

    protected function getBaseUrl(): string
    {
        return $this->url;
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function getAccessToken(): string
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        // 如果 token 有效，直接返回
        if ($this->token->isValid()) {
            return $this->token->access_token;
        }

        try {
            $application = $this->token->application;
            $response = $this->client->post('/security/v1/oauth/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $application->application_id,
                    'client_secret' => $application->shared_secret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // 更新 token 信息
            $this->token->update([
                'access_token' => $data['access_token'],
                'expires_time' => Carbon::now()->addSeconds($data['expires_in'] - 60), // 提前1分钟过期
            ]);

            return $this->token->access_token;
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to get UPS access token: ' . $e->getMessage());
        }
    }

    public function createLabel(array $data): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $response = $this->client->post('/api/shipments/v1/transactions', [
            'json' => $this->prepareLabelData($data)
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        // 保存标签信息
        $this->saveLabelInfo($data, $result);

        return $result;
    }

    public function getRates(array $data): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $response = $this->client->post('/rating/v1/Shop', [
            'json' => $this->prepareRateData($data)
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function trackShipment(string $trackingNumber): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $response = $this->client->get("/track/v1/details/{$trackingNumber}");
        
        return json_decode($response->getBody()->getContents(), true);
    }

    private function prepareLabelData(array $data): array
    {
        $formattedAddress = $this->addressFormatter->format($data, $data['service_type']);

        // 处理包裹数据
        $packages = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            // 多个包裹的情况
            foreach ($data['packages'] as $index => $package) {
                $packages[] = [
                    'PackagingType' => [
                        'Code' => '02'
                    ],
                    'Dimensions' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'IN'
                        ],
                        'Length' => $package['length'],
                        'Width' => $package['width'],
                        'Height' => $package['height']
                    ],
                    'PackageWeight' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'LBS'
                        ],
                        'Weight' => $package['weight']
                    ]
                ];
            }
        } else {
            // 单个包裹的情况
            $packages[] = [
                'PackagingType' => [
                    'Code' => '02'
                ],
                'Dimensions' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'IN'
                    ],
                    'Length' => $data['length'],
                    'Width' => $data['width'],
                    'Height' => $data['height']
                ],
                'PackageWeight' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'LBS'
                    ],
                    'Weight' => $data['weight']
                ]
            ];
        }

        $shipment = [
            'Description' => 'Package',
            'Shipper' => $this->formatAddress($formattedAddress['shipper']),
            'ShipTo' => $this->formatAddress($formattedAddress['recipient']),
            'PaymentInformation' => [
                'ShipmentCharge' => [
                    [
                        'Type' => '01', // Transportation
                        'BillShipper' => [
                            'AccountNumber' => [
                                'Value' => $data['third_party_account'] ?? $this->token->accountname
                            ]
                        ]
                    ],
                    [
                        'Type' => '02', // Duties and Taxes
                        'BillReceiver' => [] // 默认收货方支付清关税
                    ]
                ]
            ],
            'Service' => [
                'Code' => $this->mapServiceType($formattedAddress['service_type'])
            ],
            'Package' => $packages
        ];

        // 如果使用第三方账户，添加第三方账户信息
        if (!empty($data['third_party_account'])) {
            $shipment['PaymentInformation']['ShipmentCharge'] = [
                [
                    'Type' => '01', // Transportation
                    'BillThirdParty' => [
                        'AccountNumber' => [
                            'Value' => $data['third_party_account']
                        ],
                        'Address' => [
                            'AddressLine' => $formattedAddress['shipper']['address']['streetLines'],
                            'City' => $formattedAddress['shipper']['address']['city'],
                            'StateProvinceCode' => $formattedAddress['shipper']['address']['stateOrProvinceCode'],
                            'PostalCode' => $formattedAddress['shipper']['address']['postalCode'],
                            'CountryCode' => $formattedAddress['shipper']['address']['countryCode']
                        ]
                    ]
                ],
                [
                    'Type' => '02', // Duties and Taxes
                    'BillReceiver' => [] // 默认收货方支付清关税
                ]
            ];
        }

        // 如果特别指定清关税支付方
        if (!empty($data['duties_payment_type'])) {
            $dutiesPayment = [];
            switch ($data['duties_payment_type']) {
                case 'SHIPPER':
                    $dutiesPayment = [
                        'BillShipper' => [
                            'AccountNumber' => [
                                'Value' => $this->token->accountname
                            ]
                        ]
                    ];
                    break;
                case 'THIRD_PARTY':
                    if (!empty($data['third_party_account'])) {
                        $dutiesPayment = [
                            'BillThirdParty' => [
                                'AccountNumber' => [
                                    'Value' => $data['third_party_account']
                                ],
                                'Address' => [
                                    'AddressLine' => $formattedAddress['shipper']['address']['streetLines'],
                                    'City' => $formattedAddress['shipper']['address']['city'],
                                    'StateProvinceCode' => $formattedAddress['shipper']['address']['stateOrProvinceCode'],
                                    'PostalCode' => $formattedAddress['shipper']['address']['postalCode'],
                                    'CountryCode' => $formattedAddress['shipper']['address']['countryCode']
                                ]
                            ]
                        ];
                    }
                    break;
                default:
                    $dutiesPayment = ['BillReceiver' => []];
            }
            $shipment['PaymentInformation']['ShipmentCharge'][1] = array_merge(
                ['Type' => '02'],
                $dutiesPayment
            );
        }

        // 如果收件地址是住宅地址，强制使用GROUND服务
        if ($formattedAddress['recipient']['isResidential']) {
            //$shipment['Service']['Code'] = '03'; // GROUND
            // 在ShipTo中添加ResidentialIndicator字段
            $shipment['ShipTo']['ResidentialIndicator'] = 'Y';
        }

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            $shipment['ShipmentServiceOptions'] = array_merge(
                $shipment['ShipmentServiceOptions'] ?? [],
                [
                    'DeliveryConfirmation' => [
                        'DCISType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                    ]
                ]
            );
        }

        // 国际订单处理：添加商业发票、形式发票、清关费由客户支付
        if ($this->isInternationalShipment($formattedAddress['shipper']['address']['countryCode'], $formattedAddress['recipient']['address']['countryCode'])) {
            $shipment['InternationalForms'] = [
                'FormType' => '01', // COMMERCIAL_INVOICE
                'InvoiceNumber' => $data['InvoiceNumber'] ?? '',
                'InvoiceDate' => $data['InvoiceDate'] ?? date('Y-m-d'),
                'ReasonForExport' => $this->mapReasonForExport($data['InvoiceReasonForExport'] ?? 'SALE'),
                'CurrencyCode' => $this->mapCurrencyCode($data['InvoiceCurrencyCode'] ?? 'USD'),
                'Product' => $this->prepareUPSProducts($data),
                'Charges' => [
                    [
                        'Type' => 'DISCOUNT',
                        'MonetaryValue' => '0'
                    ]
                ],
                'DeclarationStatement' => $data['DeclarationStatement'] ?? 'I declare that the information provided is true and correct.',
                'TermsOfShipment' => 'DDU',
                'Comments' => $data['Comments'] ?? '',
                'Purpose' => 'SOLD',
                'DocumentIndicator' => 'Y', // COMMERCIAL_INVOICE
                'ProformaIndicator' => 'Y', // PRO_FORMA_INVOICE
                'PrintCopyOfPaperlessDocumentsIndicator' => 'Y' // 打印纸质文档
            ];

            // 如果进口商信息与收货人不同，添加进口商信息
            if (!empty($data['importer']) && $this->isImporterDifferent($data['importer'], $formattedAddress['recipient'])) {
                $shipment['InternationalForms']['ImporterOfRecord'] = [
                    'Name' => $data['importer']['contact']['personName'],
                    'Address' => [
                        'AddressLine' => $data['importer']['address']['streetLines'],
                        'City' => $data['importer']['address']['city'],
                        'StateProvinceCode' => $data['importer']['address']['stateOrProvinceCode'],
                        'PostalCode' => $data['importer']['address']['postalCode'],
                        'CountryCode' => $data['importer']['address']['countryCode']
                    ],
                    'Phone' => [
                        'Number' => $data['importer']['contact']['phoneNumber']
                    ],
                    'EMailAddress' => $data['importer']['contact']['emailAddress'],
                    'TaxIdentificationNumber' => $data['importer']['tax_id'] ?? '',
                    'VATNumber' => $data['importer']['vat_number'] ?? ''
                ];
            }

            // 清关费由客户支付
            $shipment['ShipmentServiceOptions'] = [
                'InternationalDetail' => [
                    'BrokerageOption' => '02', // 02: Recipient pays duties
                ]
            ];
        }

        // 添加发货通知功能
        if (!empty($data['ship_notify']) && $data['ship_notify'] === true) {
            $shipment['ShipmentServiceOptions']['Notification'] = [
                'NotificationCode' => '6', // 6: Shipment Notification
                'EMail' => [
                    'EMailAddress' => $data['ship_notify_email'] ?? '',
                    'UndeliverableEMailAddress' => $data['ship_notify_failed_email'] ?? 'downrag_hktkwan@hotmail.com',
                    'FromEMailAddress' => $data['ship_notify_from_email'] ?? 'Alpharex-UPS',
                    'FromName' => $data['ship_notify_from_name'] ?? 'Alpharex-UPS',
                    'Subject' => $data['ship_notify_subject'] ?? 'Shipment Notification',
                    'Message' => $data['ship_notify_message'] ?? 'Your shipment has been processed.'
                ],
                'VoiceMessage' => [
                    'PhoneNumber' => $data['ship_notify_phone'] ?? ''
                ],
                'TextMessage' => [
                    'PhoneNumber' => $data['ship_notify_phone'] ?? ''
                ],
                'Locale' => [
                    'Language' => $data['ship_notify_language'] ?? 'en',
                    'Dialect' => $data['ship_notify_dialect'] ?? 'US'
                ]
            ];
        }

        return [
            'ShipmentRequest' => [
                'Shipment' => $shipment,
                'LabelSpecification' => [
                    'LabelImageFormat' => [
                        'Code' => 'PDF'
                    ],
                    'LabelStockSize' => [
                        'Height' => '6',
                        'Width' => '4'
                    ]
                ]
            ]
        ];
    }

    private function prepareRateData(array $data): array
    {
        $formattedAddress = $this->addressFormatter->format($data, $data['service_type']);

        // 处理包裹数据
        $packages = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            // 多个包裹的情况
            foreach ($data['packages'] as $index => $package) {
                $packages[] = [
                    'PackagingType' => [
                        'Code' => '02'
                    ],
                    'Dimensions' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'IN'
                        ],
                        'Length' => $package['length'],
                        'Width' => $package['width'],
                        'Height' => $package['height']
                    ],
                    'PackageWeight' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'LBS'
                        ],
                        'Weight' => $package['weight']
                    ]
                ];
            }
        } else {
            // 单个包裹的情况
            $packages[] = [
                'PackagingType' => [
                    'Code' => '02'
                ],
                'Dimensions' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'IN'
                    ],
                    'Length' => $data['length'],
                    'Width' => $data['width'],
                    'Height' => $data['height']
                ],
                'PackageWeight' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'LBS'
                    ],
                    'Weight' => $data['weight']
                ]
            ];
        }

        $shipment = [
            'Shipper' => $this->formatAddress($formattedAddress['shipper']),
            'ShipTo' => $this->formatAddress($formattedAddress['recipient']),
            'ShipFrom' => $this->formatAddress($formattedAddress['shipper']),
            'Service' => [
                'Code' => $this->mapServiceType($formattedAddress['service_type'])
            ],
            'Package' => $packages
        ];

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            $shipment['ShipmentServiceOptions'] = [
                'DeliveryConfirmation' => [
                    'DCISType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                ]
            ];
        }

        // 如果收件地址是住宅地址，添加住宅地址标识
        if ($formattedAddress['recipient']['isResidential']) {
            $shipment['ShipTo']['ResidentialIndicator'] = 'Y';
        }

        // 国际订单处理
        if ($this->isInternationalShipment($formattedAddress['shipper']['address']['countryCode'], $formattedAddress['recipient']['address']['countryCode'])) {
            $shipment['ShipmentServiceOptions'] = array_merge(
                $shipment['ShipmentServiceOptions'] ?? [],
                [
                    'InternationalDetail' => [
                        'BrokerageOption' => '02' // 02: Recipient pays duties
                    ]
                ]
            );

            // 如果进口商信息与收货人不同，添加进口商信息
            if (!empty($data['importer']) && $this->isImporterDifferent($data['importer'], $formattedAddress['recipient'])) {
                $shipment['ImporterOfRecord'] = [
                    'Name' => $data['importer']['contact']['personName'],
                    'Address' => [
                        'AddressLine' => $data['importer']['address']['streetLines'],
                        'City' => $data['importer']['address']['city'],
                        'StateProvinceCode' => $data['importer']['address']['stateOrProvinceCode'],
                        'PostalCode' => $data['importer']['address']['postalCode'],
                        'CountryCode' => $data['importer']['address']['countryCode']
                    ],
                    'Phone' => [
                        'Number' => $data['importer']['contact']['phoneNumber']
                    ],
                    'EMailAddress' => $data['importer']['contact']['emailAddress'],
                    'TaxIdentificationNumber' => $data['importer']['tax_id'] ?? '',
                    'VATNumber' => $data['importer']['vat_number'] ?? ''
                ];
            }
        }

        return [
            'RateRequest' => [
                'Shipment' => $shipment
            ]
        ];
    }

    private function formatAddress(array $address): array
    {
        return [
            'Name' => $address['contact']['personName'],
            'Address' => [
                'AddressLine' => $address['address']['streetLines'],
                'City' => $address['address']['city'],
                'StateProvinceCode' => $address['address']['stateOrProvinceCode'],
                'PostalCode' => $address['address']['postalCode'],
                'CountryCode' => $address['address']['countryCode']
            ],
            'Phone' => [
                'Number' => $address['contact']['phoneNumber']
            ],
            'EMailAddress' => $address['contact']['emailAddress'],
            'ResidentialIndicator' => $address['isResidential'] ? 'Y' : 'N'
        ];
    }

    private function mapServiceType(string $serviceType): string
    {
        // 获取收件人国家代码
        $recipientCountry = $this->addressFormatter->getCountryCode() ?? '';

        // 标准化服务类型名称
        $serviceType = $this->normalizeServiceType($serviceType);

        // 根据国家代码和服务类型进行特殊处理
        if (($serviceType === '' || $serviceType === 'GROUND' || $serviceType === 'WORLDWIDE_SAVER') && 
            ($recipientCountry === 'CA' || $recipientCountry === 'MX')) {
            return '11'; // Standard
        }

        if (($serviceType === 'STANDARD' || $serviceType === '' || $serviceType === 'GROUND' || $serviceType === 'WORLDWIDE_SAVER') && 
            ($recipientCountry !== 'CA' && $recipientCountry !== 'MX' && $recipientCountry !== 'US')) {
            return '08'; // Worldwide Expedited
        }

        if (($serviceType === '' || $serviceType === 'GROUND' || $serviceType === 'WORLDWIDE_SAVER' || $serviceType === 'STANDARD') && 
            ($recipientCountry === 'US')) {
            return '03'; // Ground
        }

        // 标准服务类型映射
        $mapping = [
            'NEXT_DAY_AIR_EARLY' => '14',    // Next Day Air Early AM
            'NEXT_DAY_AIR' => '01',          // Next Day Air
            'NEXT_DAY_AIR_SAVER' => '13',    // Next Day Air Saver
            'SECOND_DAY_AIR_AM' => '59',     // 2nd Day Air AM
            'SECOND_DAY_AIR' => '02',        // 2nd Day Air
            'THREE_DAY_SELECT' => '12',      // 3 Day Select
            'GROUND' => '03',                // Ground Service
            'WORLDWIDE_EXPRESS' => '07',     // Worldwide Express
            'WORLDWIDE_SAVER' => '08',       // Worldwide Saver
            'WORLDWIDE_EXPEDITED' => '08',   // Worldwide Expedited
            'STANDARD' => '11',              // Standard
            'EXPRESS' => '01',               // Express
            'EXPRESS_PLUS' => '54',          // Express Plus
            'SAVER' => '13'                  // Saver
        ];

        return $mapping[$serviceType] ?? '03'; // 默认返回 Ground
    }

    private function normalizeServiceType(string $serviceType): string
    {
        // 转换为大写并移除多余的空格
        $serviceType = trim(strtoupper($serviceType));

        // 服务类型名称映射
        $normalizedTypes = [
            'NEXT DAY AIR EARLY AM' => 'NEXT_DAY_AIR_EARLY',
            'NEXT DAY AIR' => 'NEXT_DAY_AIR',
            'NEXT DAY AIR SAVER' => 'NEXT_DAY_AIR_SAVER',
            '2ND DAY AIR AM' => 'SECOND_DAY_AIR_AM',
            '2ND DAY AIR' => 'SECOND_DAY_AIR',
            'SECOND DAY AIR AM' => 'SECOND_DAY_AIR_AM',
            'SECOND DAY AIR' => 'SECOND_DAY_AIR',
            '3 DAY SELECT' => 'THREE_DAY_SELECT',
            'THREE DAY SELECT' => 'THREE_DAY_SELECT',
            'GROUND SERVICE' => 'GROUND',
            'WORLDWIDE EXPRESS' => 'WORLDWIDE_EXPRESS',
            'WORLDWIDE SAVER' => 'WORLDWIDE_SAVER',
            'WORLDWIDE EXPEDITED' => 'WORLDWIDE_EXPEDITED',
            'STANDARD SERVICE' => 'STANDARD',
            'EXPRESS SERVICE' => 'EXPRESS',
            'EXPRESS PLUS SERVICE' => 'EXPRESS_PLUS',
            'SAVER SERVICE' => 'SAVER',
            // 添加更多可能的变体...
        ];

        // 如果找到匹配的标准化名称，返回它
        if (isset($normalizedTypes[$serviceType])) {
            return $normalizedTypes[$serviceType];
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

    private function isInternationalShipment(string $shipperCountry, string $recipientCountry): bool
    {
        return $shipperCountry !== $recipientCountry;
    }

    private function prepareUPSProducts(array $data): array
    {
        $products = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $products[] = [
                    'Description' => $item['description'] ?? '',
                    'CommodityCode' => $item['harmonizedCode'] ?? '',
                    'OriginCountryCode' => $item['countryOfManufacture'] ?? $data['shipper']['address']['countryCode'] ?? 'US',
                    'Unit' => [
                        'Number' => $item['quantity'] ?? 1,
                        'Value' => $item['unitPrice'] ?? 0,
                        'UnitOfMeasurement' => [
                            'Code' => 'PCS'
                        ]
                    ],
                    'ProductWeight' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'LBS'
                        ],
                        'Weight' => $item['weight'] ?? 0
                    ]
                ];
            }
        } else {
            $products[] = [
                'Description' => $data['description'] ?? 'General Merchandise',
                'CommodityCode' => $data['harmonizedCode'] ?? '',
                'OriginCountryCode' => $data['countryOfManufacture'] ?? $data['shipper']['address']['countryCode'] ?? 'US',
                'Unit' => [
                    'Number' => 1,
                    'Value' => $data['customsValue'] ?? 0,
                    'UnitOfMeasurement' => [
                        'Code' => 'PCS'
                    ]
                ],
                'ProductWeight' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'LBS'
                    ],
                    'Weight' => $data['weight'] ?? 0
                ]
            ];
        }
        return $products;
    }

    private function mapSignatureType(string $type): string
    {
        $mapping = [
            'DIRECT' => '01', // 直接签名
            'INDIRECT' => '02', // 间接签名
            'ADULT' => '03', // 成人签名
            'SIGNATURE_REQUIRED' => '01', // 需要签名
            'SIGNATURE_NOT_REQUIRED' => '00', // 不需要签名
            'NO_SIGNATURE_REQUIRED' => '00', // 不需要签名
            'SIGNATURE_REQUIRED_INDIRECT' => '02', // 间接签名
            'SIGNATURE_REQUIRED_ADULT' => '03', // 成人签名
        ];

        return $mapping[$type] ?? '01'; // 默认需要直接签名
    }

    private function isImporterDifferent(array $importer, array $recipient): bool
    {
        // 检查进口商和收货人是否相同
        return $importer['contact']['personName'] !== $recipient['contact']['personName'] ||
               $importer['address']['streetLines'] !== $recipient['address']['streetLines'] ||
               $importer['address']['city'] !== $recipient['address']['city'] ||
               $importer['address']['stateOrProvinceCode'] !== $recipient['address']['stateOrProvinceCode'] ||
               $importer['address']['postalCode'] !== $recipient['address']['postalCode'] ||
               $importer['address']['countryCode'] !== $recipient['address']['countryCode'];
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

    private function saveLabelInfo(array $data, array $response): void
    {
        // 从响应中提取跟踪号码
        $trackingNumber = $response['ShipmentResponse']['ShipmentResults']['ShipmentIdentificationNumber'] ?? null;
        
        if (!$trackingNumber) {
            return;
        }

        // 从响应中提取标签URL
        $labelUrl = $response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'] ?? null;

        // 从响应中提取运费
        $shippingCost = $response['ShipmentResponse']['ShipmentResults']['ShipmentCharges']['TotalCharges']['MonetaryValue'] ?? null;

        // 准备包裹信息
        $packageInfo = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            $packageInfo = $data['packages'];
        } else {
            $packageInfo[] = [
                'weight' => $data['weight'] ?? null,
                'length' => $data['length'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null
            ];
        }

        // 创建标签记录
        ShippingLabel::create([
            'carrier' => 'ups',
            'account_number' => $this->token->accountname,
            'tracking_number' => $trackingNumber,
            'invoice_number' => $data['InvoiceNumber'] ?? null,
            'service_type' => $data['service_type'] ?? null,
            'shipping_cost' => $shippingCost,
            'label_url' => $labelUrl,
            'label_data' => $response,
            'shipper_info' => [
                'name' => $data['shipper']['name'] ?? null,
                'address' => $data['shipper']['address'] ?? null,
                'phone' => $data['shipper']['phone'] ?? null,
                'email' => $data['shipper']['email'] ?? null
            ],
            'recipient_info' => [
                'name' => $data['recipient']['name'] ?? null,
                'address' => $data['recipient']['address'] ?? null,
                'phone' => $data['recipient']['phone'] ?? null,
                'email' => $data['recipient']['email'] ?? null
            ],
            'package_info' => $packageInfo,
            'status' => 'ACTIVE'
        ]);
    }
} 