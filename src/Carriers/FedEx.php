<?php

namespace Widia\Shipping\Carriers;

use Widia\Shipping\Models\Token;
use Widia\Shipping\Address\AddressFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Carbon\Carbon;
use Widia\Shipping\Models\ShippingLabel;

class FedEx extends AbstractCarrier
{
    protected string $test_url = 'https://apis-sandbox.fedex.com';
    protected string $live_url = 'https://apis.fedex.com';
    protected string $url;
    protected ?Token $token = null;
    protected AddressFormatter $addressFormatter;
    protected array $internationalServices = [
        'INTERNATIONAL_ECONOMY',
        'INTERNATIONAL_PRIORITY',
        'INTERNATIONAL_FIRST',
        'INTERNATIONAL_GROUND',
    ];

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setAccount(string $accountNumber): self
    {
        $this->token = Token::where('accountname', $accountNumber)
            ->whereHas('application', function ($query) {
                $query->where('type', 'fedex');
            })
            ->first();

        if (!$this->token) {
            throw new \Exception("FedEx account not found: {$accountNumber}");
        }

        $application = $this->token->application;
        if (!$application) {
            throw new \Exception("FedEx application not found for account: {$accountNumber}");
        }

        $this->url = str_contains(strtolower($application->application_name), 'sandbox') ? $this->test_url : $this->live_url;
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => $this->getHeaders(),
        ]);

        // 初始化地址格式化器
        $this->addressFormatter = new AddressFormatter(
            $this->client,
            '/address/v1/addresses/resolve',
            $this->internationalServices,
            'FedEx'
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
            'X-locale' => 'en_US',
        ];
    }

    protected function getAccessToken(): string
    {
        if (!$this->token) {
            throw new \Exception('FedEx account not set');
        }

        // 如果 token 有效，直接返回
        if ($this->token->isValid()) {
            return $this->token->access_token;
        }

        try {
            $application = $this->token->application()->first();
            $response = $this->client->post('/oauth/token', [
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
            throw new \Exception('Failed to get FedEx access token: ' . $e->getMessage());
        }
    }

    public function createLabel(array $data): array
    {
        if (!$this->token) {
            throw new \Exception('FedEx account not set');
        }

        $response = $this->client->post('/ship/v1/labels', [
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
            throw new \Exception('FedEx account not set');
        }

        $response = $this->client->post('/rate/v1/rates', [
            'json' => $this->prepareRateData($data)
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function trackShipment(string $trackingNumber): array
    {
        if (!$this->token) {
            throw new \Exception('FedEx account not set');
        }

        $response = $this->client->get("/track/v1/details/{$trackingNumber}");
        
        return json_decode($response->getBody()->getContents(), true);
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
                'residential' => $address['isResidential']
            ]
        ];

        // 如果是住宅地址，确保使用 HOME_DELIVERY 服务
        if ($address['isResidential'] && $address['address']['countryCode'] === 'US') {
            $formattedAddress['serviceType'] = 'HOME_DELIVERY';
        }

        return $formattedAddress;
    }

    private function mapServiceType(string $serviceType): string
    {
        // 标准化服务类型名称
        $serviceType = $this->normalizeServiceType($serviceType);

        // 获取收件人国家代码和住宅地址标志
        $recipientCountry = $this->addressFormatter->getCountryCode() ?? '';
        $isResidential = $this->addressFormatter->isResidential() ?? false;
        $isInternational = $recipientCountry !== 'US';

        // 国际运输服务类型映射
        if ($isInternational) {
            $internationalMapping = [
                'INTERNATIONAL_PRIORITY' => 'INTERNATIONAL_PRIORITY',
                'INTERNATIONAL_PRIORITY_FREIGHT' => 'INTERNATIONAL_PRIORITY_FREIGHT',
                'INTERNATIONAL_ECONOMY_FREIGHT' => 'INTERNATIONAL_ECONOMY_FREIGHT',
                'INTERNATIONAL_ECONOMY' => 'INTERNATIONAL_ECONOMY'
            ];

            return $internationalMapping[$serviceType] ?? 'INTERNATIONAL_ECONOMY';
        }

        // 国内运输服务类型映射
        $domesticMapping = [
            'FIRST_OVERNIGHT' => 'FIRST_OVERNIGHT',
            'PRIORITY_OVERNIGHT' => 'PRIORITY_OVERNIGHT',
            'STANDARD_OVERNIGHT' => 'STANDARD_OVERNIGHT',
            'SECOND_DAY_AM' => 'SECOND_DAY_AM',
            'SECOND_DAY' => 'SECOND_DAY',
            'EXPRESS_SAVER' => 'EXPRESS_SAVER',
            'FIRST_OVERNIGHT_FREIGHT' => 'FIRST_OVERNIGHT_FREIGHT',
            'ONE_DAY_FREIGHT' => 'ONE_DAY_FREIGHT',
            'TWO_DAY_FREIGHT' => 'TWO_DAY_FREIGHT',
            'THREE_DAY_FREIGHT' => 'THREE_DAY_FREIGHT',
            'SMART_POST' => 'SMART_POST'
        ];

        // 如果找到匹配的国内服务类型，返回它
        if (isset($domesticMapping[$serviceType])) {
            return $domesticMapping[$serviceType];
        }

        // 如果没有匹配的服务类型，根据住宅地址标志返回默认服务
        return $isResidential ? 'HOME_DELIVERY' : 'GROUND';
    }

    private function normalizeServiceType(string $serviceType): string
    {
        // 转换为大写并移除多余的空格
        $serviceType = trim(strtoupper($serviceType));

        // 服务类型名称映射
        $normalizedTypes = [
            // 国际服务
            'INTERNATIONAL PRIORITY' => 'INTERNATIONAL_PRIORITY',
            'FEDEX INTERNATIONAL PRIORITY' => 'INTERNATIONAL_PRIORITY',
            'FEDEX INTERNATIONAL PRIORITY FREIGHT' => 'INTERNATIONAL_PRIORITY_FREIGHT',
            'INTERNATIONAL PRIORITY FREIGHT' => 'INTERNATIONAL_PRIORITY_FREIGHT',
            'FEDEX INTERNATIONAL ECONOMY FREIGHT' => 'INTERNATIONAL_ECONOMY_FREIGHT',
            'INTERNATIONAL ECONOMY FREIGHT' => 'INTERNATIONAL_ECONOMY_FREIGHT',
            'INTERNATIONAL ECONOMY' => 'INTERNATIONAL_ECONOMY',
            'FEDEX INTERNATIONAL ECONOMY' => 'INTERNATIONAL_ECONOMY',

            // 国内隔夜服务
            'FEDEX FIRST OVERNIGHT' => 'FIRST_OVERNIGHT',
            'FIRST OVERNIGHT' => 'FIRST_OVERNIGHT',
            'FEDEX PRIORITY OVERNIGHT' => 'PRIORITY_OVERNIGHT',
            'PRIORITY OVERNIGHT' => 'PRIORITY_OVERNIGHT',
            'FEDEX STANDARD OVERNIGHT' => 'STANDARD_OVERNIGHT',
            'STANDARD OVERNIGHT' => 'STANDARD_OVERNIGHT',

            // 2天服务
            'FEDEX 2DAY A.M.' => 'SECOND_DAY_AM',
            'FEDEX 2DAY AM' => 'SECOND_DAY_AM',
            '2DAY A.M.' => 'SECOND_DAY_AM',
            '2DAY AM' => 'SECOND_DAY_AM',
            'FEDEX 2DAY' => 'SECOND_DAY',
            '2DAY' => 'SECOND_DAY',

            // 其他快递服务
            'FEDEX EXPRESS SAVER' => 'EXPRESS_SAVER',
            'EXPRESS SAVER' => 'EXPRESS_SAVER',

            // 货运服务
            'FEDEX FIRST OVERNIGHT FREIGHT' => 'FIRST_OVERNIGHT_FREIGHT',
            'FIRST OVERNIGHT FREIGHT' => 'FIRST_OVERNIGHT_FREIGHT',
            'FEDEX 1 DAY FREIGHT' => 'ONE_DAY_FREIGHT',
            'FEDEX ONE DAY FREIGHT' => 'ONE_DAY_FREIGHT',
            'ONE DAY FREIGHT' => 'ONE_DAY_FREIGHT',
            'FEDEX 2 DAY FREIGHT' => 'TWO_DAY_FREIGHT',
            'FEDEX TWO DAY FREIGHT' => 'TWO_DAY_FREIGHT',
            'TWO DAY FREIGHT' => 'TWO_DAY_FREIGHT',
            'FEDEX 3 DAY FREIGHT' => 'THREE_DAY_FREIGHT',
            'FEDEX THREE DAY FREIGHT' => 'THREE_DAY_FREIGHT',
            'THREE DAY FREIGHT' => 'THREE_DAY_FREIGHT',

            // 特殊服务
            'FEDEX SMART POST' => 'SMART_POST',
            'SMART POST' => 'SMART_POST',
            'SMARTPOST' => 'SMART_POST',

            // 基础服务
            'GROUND SERVICE' => 'GROUND',
            'GROUND DELIVERY' => 'GROUND',
            'HOME DELIVERY' => 'HOME_DELIVERY',
            'RESIDENTIAL DELIVERY' => 'HOME_DELIVERY'
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

    private function prepareLabelData(array $data): array
    {
        // 获取默认发件人信息
        $defaultShipper = config('shipping.default_shipper');
        
        // 合并默认发件人信息和传入的数据
        $data['shipper'] = array_merge($defaultShipper, $data['shipper'] ?? []);

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

        $requestedShipment = [
            'shipper' => $this->formatAddress($formattedAddress['shipper']),
            'recipients' => [$this->formatAddress($formattedAddress['recipient'])],
            'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
            'serviceType' => $this->mapServiceType($formattedAddress['service_type']),
            'packagingType' => 'YOUR_PACKAGING',
            'totalPackageCount' => count($packages),
            'requestedPackageLineItems' => $packages,
            'labelSpecification' => [
                'imageType' => 'PDF',
                'labelStockType' => 'PAPER_4X6'
            ]
        ];

        // 如果使用第三方账户，添加第三方账户信息
        if (!empty($data['third_party_account'])) {
            $requestedShipment['shippingChargesPayment'] = [
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
            // 添加运输费用支付信息
            $requestedShipment['freightChargesPayment'] = [
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
        } else {
            $requestedShipment['shippingChargesPayment'] = [
                'paymentType' => 'SENDER',
                'payor' => [
                    'responsibleParty' => [
                        'accountNumber' => [
                            'value' => $this->token->accountname
                        ]
                    ]
                ]
            ];
            $requestedShipment['freightChargesPayment'] = [
                'paymentType' => 'SENDER',
                'payor' => [
                    'responsibleParty' => [
                        'accountNumber' => [
                            'value' => $this->token->accountname
                        ]
                    ]
                ]
            ];
        }

        // 如果存在DepartmentNotes，添加到请求中
        if (!empty($data['DepartmentNotes'])) {
            $requestedShipment['departmentNotes'] = $data['DepartmentNotes'];
        }

        // 如果存在Reference，添加到请求中
        if (!empty($data['Reference'])) {
            $requestedShipment['shipmentSpecialServices'] = [
                'specialServiceTypes' => ['REFERENCE'],
                'reference' => [
                    'referenceType' => 'CUSTOMER_REFERENCE',
                    'value' => $data['Reference']
                ]
            ];
        }

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            $requestedShipment['shipmentSpecialServices'] = array_merge(
                $requestedShipment['shipmentSpecialServices'] ?? ['specialServiceTypes' => []],
                [
                    'specialServiceTypes' => array_merge(
                        $requestedShipment['shipmentSpecialServices']['specialServiceTypes'] ?? [],
                        ['SIGNATURE_OPTION']
                    ),
                    'signatureOptionDetail' => [
                        'optionType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                    ]
                ]
            );
        }

        // 添加发货通知功能
        if (!empty($data['ship_notify']) && $data['ship_notify'] === true) {
            $requestedShipment['shipmentSpecialServices'] = array_merge(
                $requestedShipment['shipmentSpecialServices'] ?? ['specialServiceTypes' => []],
                [
                    'specialServiceTypes' => array_merge(
                        $requestedShipment['shipmentSpecialServices']['specialServiceTypes'] ?? [],
                        ['EVENT_NOTIFICATION']
                    ),
                    'eventNotificationDetail' => [
                        'aggregationType' => 'PER_SHIPMENT',
                        'personalMessage' => $data['ship_notify_message'] ?? 'Your shipment has been processed.',
                        'eventNotifications' => [
                            [
                                'role' => 'SHIPPER',
                                'events' => ['ON_SHIPMENT'],
                                'notificationDetail' => [
                                    'notificationType' => 'EMAIL',
                                    'emailDetail' => [
                                        'emailAddress' => $data['ship_notify_email'] ?? '',
                                        'name' => $data['ship_notify_contact_name'] ?? '',
                                        'notificationEventType' => ['ON_SHIPMENT'],
                                        'format' => 'HTML',
                                        'localization' => [
                                            'languageCode' => $data['ship_notify_language'] ?? 'en',
                                            'localeCode' => $data['ship_notify_dialect'] ?? 'US'
                                        ]
                                    ]
                                ],
                                'formatSpecification' => [
                                    'type' => 'HTML'
                                ]
                            ]
                        ]
                    ]
                ]
            );
        }

        // 如果是国际订单，添加商业发票和形式发票信息，清关费由客户支付
        if ($this->isInternationalShipment($formattedAddress['shipper']['address']['countryCode'], $formattedAddress['recipient']['address']['countryCode'])) {
            $requestedShipment['customsClearanceDetail'] = [
                'dutiesPayment' => [
                    'paymentType' => 'RECIPIENT' // 默认收货方支付清关税
                ],
                'commodities' => $this->prepareCommodities($data),
                'customsValue' => [
                    'currency' => $this->mapCurrencyCode($data['InvoiceCurrencyCode'] ?? 'USD'),
                    'amount' => $data['customsValue'] ?? 0
                ],
                'reasonForExport' => $this->mapReasonForExport($data['InvoiceReasonForExport'] ?? 'SALE')
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

            // 添加商业发票和形式发票文档
            $requestedShipment['shippingDocumentSpecification'] = [
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

        return [
            'requestedShipment' => $requestedShipment
        ];
    }

    private function isInternationalShipment(string $shipperCountry, string $recipientCountry): bool
    {
        return $shipperCountry !== $recipientCountry;
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

    private function prepareRateData(array $data): array
    {
        $formattedAddress = $this->addressFormatter->format($data, $data['service_type']);
        
        // 获取格式化后的地址
        $shipperAddress = $this->formatAddress($formattedAddress['shipper']);
        $recipientAddress = $this->formatAddress($formattedAddress['recipient']);

        // 确定最终的服务类型
        $serviceType = $this->mapServiceType($formattedAddress['service_type']);

        // 处理包裹数据
        $packageLineItems = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            // 多个包裹的情况
            foreach ($data['packages'] as $index => $package) {
                $packageLineItems[] = [
                    'sequenceNumber' => $index + 1,
                    'weight' => [
                        'units' => 'LB',
                        'value' => $package['weight']
                    ],
                    'dimensions' => [
                        'length' => $package['length'],
                        'width' => $package['width'],
                        'height' => $package['height'],
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
                    'value' => $data['weight']
                ],
                'dimensions' => [
                    'length' => $data['length'],
                    'width' => $data['width'],
                    'height' => $data['height'],
                    'units' => 'IN'
                ]
            ];
        }

        $requestedShipment = [
            'shipper' => $shipperAddress,
            'recipient' => $recipientAddress,
            'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
            'serviceType' => $serviceType,
            'packagingType' => 'YOUR_PACKAGING',
            'requestedPackageLineItems' => $packageLineItems
        ];

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            $requestedShipment['shipmentSpecialServices'] = [
                'specialServiceTypes' => ['SIGNATURE_OPTION'],
                'signatureOptionDetail' => [
                    'optionType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                ]
            ];
        }

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
        if ($this->isInternationalShipment($formattedAddress['shipper']['address']['countryCode'], $formattedAddress['recipient']['address']['countryCode'])) {
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

        return [
            'accountNumber' => [
                'value' => $this->token->accountname
            ],
            'requestedShipment' => $requestedShipment
        ];
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
        $trackingNumber = $response['output']['transactionShipments'][0]['masterTrackingNumber'] ?? null;
        
        if (!$trackingNumber) {
            return;
        }

        // 从响应中提取标签URL
        $labelUrl = $response['output']['transactionShipments'][0]['labelURL'] ?? null;

        // 从响应中提取运费
        $shippingCost = $response['output']['transactionShipments'][0]['rateDetails'][0]['totalNetCharge'] ?? null;

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
            'carrier' => 'fedex',
            'account_number' => $this->token->accountname,
            'tracking_number' => $trackingNumber,
            'invoice_number' => $data['InvoiceNumber'] ?? null,
            'service_type' => $data['service_type'] ?? null,
            'shipping_cost' => $shippingCost,
            'label_url' => $labelUrl,
            'label_data' => $response,
            'shipper_info' => [
                'name' => $data['shipper']['contact']['personName'] ?? null,
                'address' => $data['shipper']['address'] ?? null,
                'phone' => $data['shipper']['contact']['phoneNumber'] ?? null,
                'email' => $data['shipper']['contact']['emailAddress'] ?? null
            ],
            'recipient_info' => [
                'name' => $data['recipient']['contact']['personName'] ?? null,
                'address' => $data['recipient']['address'] ?? null,
                'phone' => $data['recipient']['contact']['phoneNumber'] ?? null,
                'email' => $data['recipient']['contact']['emailAddress'] ?? null
            ],
            'package_info' => $packageInfo,
            'status' => 'ACTIVE'
        ]);
    }
} 