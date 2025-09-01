<?php

namespace Widia\Shipping\Carriers;

use Widia\Shipping\Address\AddressFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Carbon\Carbon;

class UPS extends AbstractCarrier
{
    protected $test_url = 'https://wwwcie.ups.com/api';
    protected $live_url = 'https://onlinetools.ups.com/api';
    protected $url;
    protected $token = null;
    protected $addressFormatter;
    protected $internationalServices = [
        'WORLDWIDE_EXPRESS',
        'WORLDWIDE_EXPEDITED',
        'STANDARD',
    ];
    protected $accountName;
    protected $carrierAccount;
    protected $markup = 0.0;

    public function __construct()
    {
        //$this->client = new Client();
    }

    /*public function setAccount(string $accountName): void
    {
        $this->accountName = $accountName;
    }*/

    public function setMarkup(float $markup): void
    {
        $this->markup = $markup;
    }  

    public function getMarkup(): float
    {
        return $this->markup;
    }

    public function setCarrierAccount(string $accountNumber): void
    {
        $this->carrierAccount = $accountNumber;
    }

    public function getName(): string
    {
        return $this->accountName ?? 'ups';
    }

    public function getCarrierName(): string
    {
        return 'ups';
    }

    public function setAccount(string $accountName)
    {
        $tokenModel = config('shipping.database.models.token');
        $applicationModel = config('shipping.database.models.application');
        $this->accountName = $accountName;
        $this->token = $tokenModel::where('accountname', $accountName)
            ->whereHas('application', function ($query) {
                $query->where('type', 'ups');
            })
            ->first();

        if (!$this->token) {
            throw new \Exception("UPS account not found: {$accountName}");
        }

        $application = $this->token->application;
        $this->carrierAccount = $this->token->accountnumber;
        if (!$application) {
            throw new \Exception("UPS application not found for account: {$accountName}");
        }

        $this->url = str_contains(strtolower($application->application_name), 'sandbox') ? $this->test_url : $this->live_url;
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => $this->getHeaders(),
        ]);

        // 初始化地址格式化器
        $this->addressFormatter = new AddressFormatter(
            $this,
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
            if(!$this->client){
                $this->client = new Client([
                    'base_uri' => $this->url,
                    'timeout'  => 30,
                ]);
            }

            $application = $this->token->application;

            $response = $this->client->post('/security/v1/oauth/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'x-merchant-id' => $application->application_id,
                ],
                'auth' => [$application->application_id, $application->shared_secret],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

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
//print_r($this->prepareLabelData($data));exit;
        $response = $this->client->post('/api/shipments/v2409/ship', [
            'json' => $this->prepareLabelData($data)
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
//print_r($result);
        // 保存标签信息
        $data = $this->saveLabelInfo($data, $result);

        if(count($data)>0){
            return $data;
        }

        return $result;
    }

    public function validateAddress(array $address): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $query = array(
            "regionalrequestindicator" => "string",
            "maximumcandidatelistsize" => "1"
        );
        $response = $this->client->post('/api/addressvalidation/v2/3?' . http_build_query($query), [
            'json' => [
                'XAVRequest'=>[
                    'AddressKeyFormat'=>[
                        'AddressLine' => $address['address']['streetLines'],
                        'PoliticalDivision2' => $address['address']['city'],
                        'PoliticalDivision1' => $address['address']['stateOrProvinceCode'],
                        'PostcodePrimaryLow' => $address['address']['postalCode'],
                        'CountryCode' => $address['address']['countryCode'],
                    ]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        // 检查地址验证结果
        if (!isset($result['XAVResponse']['Candidate'][0])) {
            throw new \Exception('Address validation failed');
        }

        // 如果地址有效，使用验证后的地址
        $resolvedAddress = $result['XAVResponse']['Candidate'][0];
        $classification = $resolvedAddress['AddressClassification']['Code'] ?? "0";

        // 检查是否是住宅地址
        $address['address']['isResidential'] = (int)$classification === 2;
        $address['address']['AddressClassification'] = $classification;

        //$this->isResidential = $address['address']['isResidential'];

        if($classification == 0) { //UNKNOW
            //throw new \Exception('Address validation returned UNKNOWN classification');
        }

        return $address;
    }

    public function validateAddresstoResponse(array $address)
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $query = array(
            "regionalrequestindicator" => "string",
            "maximumcandidatelistsize" => "1"
        );
        $response = $this->client->post('/api/addressvalidation/v2/3?' . http_build_query($query), [
            'json' => [
                'XAVRequest'=>[
                    'AddressKeyFormat'=>[
                        'AddressLine' => $address['address']['streetLines'],
                        'PoliticalDivision2' => $address['address']['city'],
                        'PoliticalDivision1' => $address['address']['stateOrProvinceCode'],
                        'PostcodePrimaryLow' => $address['address']['postalCode'],
                        'CountryCode' => $address['address']['countryCode'],
                    ]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        return $result;
    }

    public function getRates(array $data): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $query = array(
                "additionalinfo" => ""
        );
        $response = $this->client->post('/api/rating/v2409/Rate'. '?' . http_build_query($query), [
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

    public function cancelLabel(string $trackingNumber): bool
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        try {
            $response = $this->client->delete("/api/shipments/v2409/void/cancel/{$trackingNumber}");
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            if(method_exists($e, 'getResponse') && $e->getResponse()){
                $response = json_decode($e->getResponse()->getBody()->getContents(), true);
                if(isset($response['response']['errors'])){
                    $errors = $response['response']['errors'];
                    if(is_array($errors) && count($errors) > 0){
                        $errorMessage = implode(', ', array_column($errors, 'message'));
                        throw new \Exception($errorMessage);
                    }
                }
                throw new \Exception( $e->getResponse()->getBody()->getContents());
            }
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 创建退货标签
     */
    public function createReturnLabel(array $data): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $response = $this->client->post('/api/shipments/v1/returns', [
            'json' => $this->prepareReturnLabelData($data)
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        // 保存标签信息
        $this->saveLabelInfo($data, $result);

        return $result;
    }

    /**
     * 准备退货标签数据
     */
    private function prepareReturnLabelData(array $data): array
    {
        $formattedAddress = $this->addressFormatter->format($data, $data['service_type']);

        // 如果没有提供退货地址，使用默认退货地址
        if (empty($formattedAddress['return_address'])) {
            $formattedAddress['return_address'] = config('shipping.default_return_address');
        }

        $requestedShipment = [
            'shipper' => $this->formatAddress($formattedAddress['shipper']), // 客户地址
            'shipTo' => $this->formatAddress($formattedAddress['return_address']), // 退货地址
            'description' => $data['return_reason'] ?? 'Return Shipment',
            'paymentInformation' => [
                'shipmentCharge' => [
                    'type' => '01', // 01 = 预付
                    'billShipper' => [
                        'accountNumber' => $this->token->accountname
                    ]
                ]
            ],
            'service' => [
                'code' => $this->mapServiceType($formattedAddress['service_type']),
                'description' => $this->normalizeServiceType($formattedAddress['service_type'])
            ],
            'package' => [
                'packagingType' => [
                    'code' => '02', // 02 = 客户包装
                    'description' => 'Customer Packaging'
                ],
                'dimensions' => [
                    'unitOfMeasurement' => [
                        'code' => 'IN',
                        'description' => 'Inches'
                    ],
                    'length' => $data['package']['length'],
                    'width' => $data['package']['width'],
                    'height' => $data['package']['height']
                ],
                'packageWeight' => [
                    'unitOfMeasurement' => [
                        'code' => 'LBS',
                        'description' => 'Pounds'
                    ],
                    'weight' => $data['package']['weight']
                ]
            ],
            'returnService' => [
                'code' => '9', // 9 = 退货服务
                'description' => 'Return Service'
            ],
            'referenceNumber' => [
                'value' => $data['rma_number'] ?? $data['return_authorization_number'] ?? '',
                'barCodeIndicator' => '1'
            ],
            'originalTrackingNumber' => $data['original_tracking_number']
        ];

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            $requestedShipment['package']['signatureRequired'] = [
                'code' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT'),
                'description' => 'Signature Required'
            ];
        }

        // 添加发货通知
        if (!empty($data['ship_notify']) && !empty($data['ship_notify_email'])) {
            $requestedShipment['shipmentNotification'] = [
                'emailAddress' => $data['ship_notify_email'],
                'notificationType' => 'SHIPMENT',
                'message' => $data['ship_notify_message'] ?? 'Your return shipment has been processed.'
            ];
        }

        return [
            'requestedShipment' => $requestedShipment
        ];
    }

    private function prepareLabelData(array $data): array
    {
        $formattedAddress = $this->addressFormatter->format($data['recipient'], $data['service_type']);

        //如果UPS API地址验证结果不能识别是住宅地址，使用用户输入的地址类型
        if($formattedAddress['address']['AddressClassification'] == 0){
            $formattedAddress['address']['isResidential'] = $data['recipient']['address']['isResidential'] ?? false;
        }
        unset($formattedAddress['address']['AddressClassification']);

        $formattedAddress['contact'] = $data['recipient']['contact'] ?? [];
        // 处理包裹数据
        $packages = [];
        $data['reference_number'] = [];

        if(isset($data['invoice_number']) && !empty($data['invoice_number'])){
            $data['reference_number'][] = [
                'Code' => 'IK', // Invoice Number
                'Value' => $data['invoice_number']
            ];
        }

        if(isset($data['customer_po_number']) && !empty($data['customer_po_number'])){
            $data['reference_number'][] = [
                'Code' => 'PO', // Customer PO Number
                'Value' => $data['customer_po_number']
            ];
        }

        if(isset($data['market_order_id']) && !empty($data['market_order_id'])){
            $data['reference_number'][] = [
                'Code' => 'MO', // Market Order ID
                'Value' => $data['market_order_id']
            ];
        }

        $InvoiceLineTotal = 0;
        if (isset($data['packages']) && is_array($data['packages'])) {
            
            // 多个包裹的情况
            foreach ($data['packages'] as $index => $package) {
                $InvoiceLineTotal += $package['value']  ?? 0; // 累加包裹的价值
                $packages[] = [
                    'Packaging' => [
                        'Code' => $data['package_type'] ?? '02'
                    ],
                    'Dimensions' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'IN'
                        ],
                        'Length' => strval(max(1, (int)$package['length'])),
                        'Width' => strval(max(1, (int)$package['width'])),
                        'Height' => strval(max(1, (int)$package['height']))
                    ],
                    'PackageWeight' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'LBS'
                        ],
                        'Weight' => strval(number_format($package['weight'], 1, '.', ''))
                    ],
                    'ReferenceNumber' => array_merge($data['reference_number']??[],[
                        [
                            //'BarCodeIndicator' => "true", // 1 = 条形码指示符
                            'Code' => 'EI', // 02 = 参考号码
                            'Value' => (string)$package['box_id'] ?? '00'
                        ]
                    ]),
                    'PackageServiceOptions' => [
                        'DeliveryConfirmation' => (!empty($data['signature_required']) && $data['signature_required'] && $formattedAddress['address']['countryCode'] == 'US')  ? [
                            'DCISType' => $this->mapSignatureType($data['signature_type'] ?? '')
                        ] : null
                    ]
                ];
            }
        } else {
            // 单个包裹的情况
            $packages[] = [
                'Packaging' => [
                    'Code' => $data['package_type'] ?? '02'
                ],
                'Dimensions' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'IN'
                    ],
                    'Length' => strval(max(1, (int)$data['package']['length'])),
                    'Width' => strval(max(1, (int)$data['package']['width'])),
                    'Height' => strval(max(1, (int)$data['package']['height'])),
                ],
                'PackageWeight' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'LBS'
                    ],
                    'Weight' => strval(number_format($data['package']['weight'], 1, '.', ''))
                ],
                'ReferenceNumber' => array_merge($data['reference_number']??[],[
                    [
                        //'BarCodeIndicator' => true, // 1 = 条形码指示符
                        'Code' => 'EI', // 02 = 参考号码
                        'Value' => $data['box_id'] ?? '00'
                    ]
                ]),
            ];
        }

        $shipper = $this->getShipper($data);
        $recipient = $this->formatAddress($formattedAddress);
        $shipment = [
            'Description' => 'Package',
            'Shipper' => $shipper,
            'ShipTo' => $recipient,
            'PaymentInformation' => [
                'ShipmentCharge' => [
                    [
                        'Type' => '01', // Transportation
                        'BillShipper' => [
                            'AccountNumber' => [
                                'Value' => $this->carrierAccount
                            ]
                        ]
                    ],
                   /* [
                        'Type' => '02', // Duties and Taxes
                        'BillReceiver' => [] // 默认收货方支付清关税
                    ]*/
                ]
            ],
            'ShipmentRatingOptions' => [
                'NegotiatedRatesIndicator' => 'Y'
            ],
            'Service' => [
                'Code' => $this->mapServiceType($formattedAddress['service_type'])
            ],
            'InvoiceLineTotal'=>($formattedAddress['address']['countryCode'] == 'CA' || $formattedAddress['address']['stateOrProvinceCode'] == 'PR')?
            [
                'CurrencyCode' => 'USD',
                'MonetaryValue' => strval(number_format(($InvoiceLineTotal ?: 1), 2, '.', ''))
            ] : null,
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
                            'AddressLine' => $data['third_party']['address']['streetLines'] ??  '',
                            'City' => $data['third_party']['address']['city']  ??  '',
                            'StateProvinceCode' => $data['third_party']['address']['stateOrProvinceCode'] ??  '',
                            'PostalCode' => $data['third_party']['address']['postalCode'] ??  '',
                            'CountryCode' => $data['third_party']['address']['countryCode'] ??  ''
                        ]
                    ]
                ]
                /*[
                    'Type' => '02', // Duties and Taxes
                    'BillReceiver' => [] // 默认收货方支付清关税
                ]*/
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
                                'Value' => $this->carrierAccount
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
                                    'AddressLine' => $data['third_party']['address']['streetLines'] ??  '',
                                    'City' => $data['third_party']['address']['city']  ??  '',
                                    'StateProvinceCode' => $data['third_party']['address']['stateOrProvinceCode'] ??  '',
                                    'PostalCode' => $data['third_party']['address']['postalCode'] ??  '',
                                    'CountryCode' => $data['third_party']['address']['countryCode'] ??  ''
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
        if ($formattedAddress['address']['isResidential']) {
            //$shipment['Service']['Code'] = '03'; // GROUND
            // 在ShipTo中添加ResidentialIndicator字段
            $shipment['ShipTo']['ResidentialIndicator'] = 'Y';
        }

        // 添加签名要求. 签名服务有问题。。。
        /*if (!empty($data['signature_required'])) {
            $shipment['ShipmentServiceOptions'] = array_merge(
                $shipment['ShipmentServiceOptions'] ?? [],
                [
                    'DeliveryConfirmation' => [
                        'DCISType' => 0//$this->mapSignatureType($data['signature_type'] ?? 1)
                    ]
                ]
            );
        }*/

        // 通知设置
        /*
        5 - QV In-transit Notification
        6 - QV Ship Notification
        7 - QV Exception Notification
        8 - QV Delivery Notification
        2 - Return Notification or Label Creation Notification
        012 - Alternate Delivery Location Notification
        013 - UAP Shipper Notification.
        */
        if(!empty($data['ship_notify']) && is_array($data['ship_notify']) && count($data['ship_notify']) > 0){
            foreach($data['ship_notify'] as $key => $notify) {
                if(!empty($notify)){
                    $shipment['ShipmentServiceOptions']['Notification'][] = [
                        'NotificationCode' => $notify['ship_notify_code'] ?? "6", // 默认使用6 - QV Ship Notification
                        'EMail'=>[
                            'EMailAddress' => $notify['ship_notify_email'] ?? '',
                            'UndeliverableEMailAddress' => $notify['ship_notify_failed_email'] ?? 'downrag_hktkwan@hotmail.com',
                            'FromEMailAddress' => $notify['ship_notify_from_email'] ?? '',
                            'FromName' => $notify['ship_notify_from_name'] ?? '',
                            'Subject' => $notify['ship_notify_subject'] ?? '',
                            'Message' => $notify['ship_notify_message'] ?? ''
                        ],
                        'VoiceMessage' => [
                            'PhoneNumber' => $notify['ship_notify_phone'] ?? ''
                        ],
                        'TextMessage' => [
                            'PhoneNumber' => $notify['ship_notify_phone'] ?? ''
                        ],
                        'Locale' => [
                            'Language' => $notify['ship_notify_language'] ?? 'en',
                            'Dialect' => $notify['ship_notify_dialect'] ?? 'US'
                        ]
                    ];
                }
            }
        }

        // 国际订单处理：添加商业发票、形式发票、清关费由客户支付
        if ($this->isInternationalShipment($shipper, $recipient)) {
            $shipment['ShipmentServiceOptions']['InternationalForms'] = [
                'FormType' => '01', // COMMERCIAL_INVOICE
                'InvoiceNumber' => $data['invoice_number'] ?? '',
                'InvoiceDate' => $data['invoice_date'] ?? date('Ymd'),
                'ReasonForExport' => $this->mapReasonForExport($data['InvoiceReasonForExport'] ?? 'SALE'),
                'CurrencyCode' => $this->mapCurrencyCode($data['InvoiceCurrencyCode'] ?? 'USD'),
                'Contacts'=>['SoldTo'=>$recipient],
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
                'PaperlessDocumentIndicator' => 'Y', // 电子文档
                'PrintCopyOfPaperlessDocumentsIndicator' => 'Y' // 打印纸质文档
            ];

            // 如果进口商信息与收货人不同，添加进口商信息
            if (!empty($data['importer']) && $this->isImporterDifferent($data['importer'], $formattedAddress['recipient'])) {
                $shipment['ShipmentServiceOptions']['InternationalForms']['ImporterOfRecord'] = [
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
            $shipment['ShipmentServiceOptions'] = array_merge($shipment['ShipmentServiceOptions'],[
                'InternationalDetail' => [
                    'BrokerageOption' => '02', // 02: Recipient pays duties
                ]
            ]);
        }
//print_r($shipment);exit;
        return [
            'ShipmentRequest' => [
                'Request' => [
                    'RequestOption' => 'validate'
                ],
                'Shipment' => $shipment,
                'LabelSpecification' => [
                    'LabelImageFormat' => [
                        'Code' => 'GIF'
                    ],
                    'LabelStockSize' => [
                        'Height' => '6',
                        'Width' => '4'
                    ]
                ]
            ]
        ];
    }

    private function getShipper($data){
        // 获取默认发件人信息
        $defaultShipper = config('shipping.default_shipper');
        
        // 合并默认发件人信息和传入的数据
        $shipper = array_merge($defaultShipper, $data['shipper'] ?? []);
        
        $shipper = $this->formatAddressToUps($shipper);
        $shipper['ShipperNumber'] = $this->carrierAccount; // 添加账户号码
        return $shipper;
    }

    private function prepareRateData(array $data): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $data['service_type'] = isset($data['service_type'])?:null;

        $formattedAddress = $this->addressFormatter->format($data['recipient'], $data['service_type']);
        //$serviceType = $this->mapServiceType($formattedAddress['service_type']);

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
                        'Length' => strval(max(1, (int)$package['length'])),
                        'Width' => strval(max(1, (int)$package['width'])),
                        'Height' => strval(max(1, (int)$package['height']))
                    ],
                    'PackageWeight' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'LBS'
                        ],
                        'Weight' => strval(number_format($package['weight'], 1, '.', ''))
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
                    'Length' => strval(max(1, (int)$data['package']['length'])),
                    'Width' => strval(max(1, (int)$data['package']['width'])),
                    'Height' => strval(max(1, (int)$data['package']['height'])),
                ],
                'PackageWeight' => [
                    'UnitOfMeasurement' => [
                        'Code' => 'LBS'
                    ],
                    'Weight' => strval(number_format($data['package']['weight'], 1, '.', ''))
                ]
            ];
        }

        // 添加签名要求
        if (!empty($data['signature_required'])) {
            /*$shipment['ShipmentServiceOptions'] = [
                'DeliveryConfirmation' => [
                    'DCISType' => $this->mapSignatureType($data['signature_type'] ?? 'DIRECT')
                ]
            ];*/
            $DCISType = $this->mapSignatureType($data['signature_type'] ?? "1");
            foreach($packages as $key=>$p){
                $packages[$key]['PackageServiceOptions'] = [
                    'DeliveryConfirmation' => [
                        'DCISType' => $DCISType
                    ]
                ];
            }
        }

        $shipper = $this->getShipper($data);
        $recipient = $this->formatAddress($formattedAddress);
        $shipment = [
            'Shipper' => $shipper,
            'ShipTo' => $recipient,
            'ShipFrom' => $shipper,
            'Service' => [
                'Code' => $this->mapServiceType($formattedAddress['service_type'])
            ],
            'Package' => $packages,
            'ShipmentRatingOptions' => [
                'NegotiatedRatesIndicator' => 'Y'
            ],
        ];

        // 如果收件地址是住宅地址，添加住宅地址标识
        if ($formattedAddress['address']['isResidential']) {
            $shipment['ShipTo']['address']['ResidentialAddressIndicator'] = 'Y';
        }

        // 国际订单处理
        if ($this->isInternationalShipment($shipper, $recipient)) {
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

        $rateRequest = [
            'RateRequest' => [
                "Request" => [
                    "TransactionReference" => [
                        "CustomerContext" => "CustomerContext",
                        "TransactionIdentifier" => "TransactionIdentifier"
                    ]
                ],
                'Shipment' => $shipment
            ]
        ];

        // 添加账户信息
        $rateRequest['RateRequest']['Shipment']['Shipper']['ShipperNumber'] = $this->carrierAccount;

        // 如果配置了使用协商费率，添加相关设置
        if (config('shipping.carriers.ups.use_negotiated_rates', true)) {
            $rateRequest['RateRequest']['Shipment']['ShipmentRatingOptions'] = [
                'NegotiatedRatesIndicator' => 'Y'
            ];
        }
//print_r($rateRequest);exit;
        return $rateRequest;
    }

    private function formatAddressToUps($address){
        $upsAddress = [
            'Name' => $address['contact']['personName'] ?? '',
            'AttentionName' => $address['contact']['attentionName'] ?? 'ShipDept',
            'CompanyDisplayableName' => $address['contact']['companyName'] ?? '',
            'Phone' => ['Number'=>$address['contact']['phoneNumber'] ?? ''],
            'EMailAddress' => $address['contact']['emailAddress'] ?? '',
            'Address' => [
                'AddressLine' => $address['address']['streetLines'],
                'City' => $address['address']['city'],
                'StateProvinceCode' => $address['address']['stateOrProvinceCode'],
                'PostalCode' => $address['address']['postalCode'],
                'CountryCode' => $address['address']['countryCode']
            ],
        ];

        return $upsAddress;
    }

    private function formatAddress(array $address): array
    {
        $phoneDigitArray = $this->formatPhoneForUpsApi(($address['contact']['phoneNumber'] ?? ''), $address['address']['countryCode'] ?? 'US');
        $phone['Number'] = $phoneDigitArray['ups_api_format']['PhoneNumber'];
        // 如果有分机号，UPS API 不支持分机号，暂时忽略
        if(!empty($phoneDigitArray['ups_api_format']['Extension'])){
            $phone['Extension'] = substr($phoneDigitArray['ups_api_format']['Extension'],0,4);
            /*if(strlen($phone['Extension']) > 4){
                if(strlen($phone['Number']) + strlen($phone['Extension']) <= 14){
                    $phone['Number'] .= 'x'.$phone['Extension'];
                }
                unset($phone['Extension']);
            }*/
        }

        return [
            'Name' => $address['contact']['companyName']??$address['contact']['personName'],
            'AttentionName' => ($address['address']['countryCode']!='US' && !$address['address']['isResidential']) ? $address['contact']['personName']:'',
            'Address' => [
                'AddressLine' => $address['address']['streetLines'],
                'City' => $address['address']['city'],
                'StateProvinceCode' => (in_array($address['address']['countryCode'],['US','CA'])) ? $address['address']['stateOrProvinceCode'] : ($address['address']['countryCode'] == 'IE' ? 'IE' : null),
                'PostalCode' => $address['address']['postalCode'],
                'CountryCode' => $address['address']['countryCode']
            ],
            'Phone' => $phone,
            'EMailAddress' => $address['contact']['emailAddress'],
            'ResidentialIndicator' => ($address['address']['isResidential']??false) ? 'Y' : 'N'
        ];
    }

    /**
     * 格式化电话号码以支持UPS API，支持多种分机号格式
     * @param string $phone 原始电话号码字符串
     * @return array 格式化后的电话号码信息
     */
    function formatPhoneForUpsApi($phone, $countryCode = 'US') {
        // ISO 国家代码映射到国际区号
        $countryDialCodes = [
            'US' => '+1',
            'CA' => '+1',
            'CN' => '+86',
            'HK' => '+852',
            'TW' => '+886',
            'KR' => '+82',
            'JP' => '+81',
            'GB' => '+44',
            'DE' => '+49',
            'FR' => '+33',
            'ES' => '+34',
            'IT' => '+39',
            'IN' => '+91',
            'AU' => '+61',
            'MY' => '+60',
            'SG' => '+65',
            'BR' => '+55',
            // ... 可根据需要扩展
        ];

        // 确定目标国家的区号
        $targetDialCode = $countryDialCodes[strtoupper($countryCode)] ?? '+1';

        // 提取分机号
        $extension = null;
        $extensionPatterns = [
            '/ext\.?\s*(\d+)/i',
            '/x\.?\s*(\d+)/i',
            '/#\s*(\d+)/',
            '/extension\s*(\d+)/i',
            '/extn\.?\s*(\d+)/i',
            '/分机\s*(\d+)/u',
            '/分機\s*(\d+)/u',
            '/ext\s*(\d+)/i',
            '/x\s*(\d+)/i',
            '/#(\d+)/',
        ];
        foreach ($extensionPatterns as $pattern) {
            if (preg_match($pattern, $phone, $matches)) {
                $extension = $matches[1];
                $phone = preg_replace($pattern, '', $phone);
                break;
            }
        }

        // 去掉空格和特殊字符
        $phone = preg_replace('/[^\d\+]/', '', $phone);

        // 如果号码带了+开头，去掉并验证是否匹配目标区号
        if (strpos($phone, '+') === 0) {
            foreach ($countryDialCodes as $iso => $dial) {
                if (strpos($phone, $dial) === 0) {
                    $phone = substr($phone, strlen($dial)); // 去掉已有区号
                    break;
                }
            }
        }

        // 最终号码（仅数字）
        $phoneNumber = preg_replace('/\D/', '', $phone);

        // 美国/加拿大特殊处理：10位数或11位以1开头
        if (in_array($countryCode, ['US','CA'])) {
            if (strlen($phoneNumber) == 10) {
                $formattedPhone = '(' . substr($phoneNumber, 0, 3) . ') ' .
                                substr($phoneNumber, 3, 3) . '-' .
                                substr($phoneNumber, 6, 4);
            } elseif (strlen($phoneNumber) == 11 && $phoneNumber[0] == '1') {
                $formattedPhone = '1-' . substr($phoneNumber, 1, 3) . '-' .
                                substr($phoneNumber, 4, 3) . '-' .
                                substr($phoneNumber, 7, 4);
            } else {
                $formattedPhone = $phoneNumber;
            }
        } else {
            $formattedPhone = $phoneNumber;
        }

        return [
            'country_code' => $targetDialCode,
            'phone_number' => $formattedPhone,
            'extension' => $extension,
            'raw_input' => $phone,
            'ups_api_format' => [
                'PhoneNumber' => $formattedPhone,
                'Extension' => $extension ?: null
            ]
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

        /*if (($serviceType === 'STANDARD' || $serviceType === '' || $serviceType === 'GROUND' || $serviceType === 'WORLDWIDE_SAVER') && 
            ($recipientCountry != 'CA' && $recipientCountry != 'MX' && $recipientCountry != 'US')) {
            return '08'; // Worldwide Expedited
        }*/

        if (($serviceType === 'STANDARD' || $serviceType === '' || $serviceType === 'GROUND' || $serviceType === 'WORLDWIDE_SAVER') && 
            ($recipientCountry != 'CA' && $recipientCountry != 'MX' && $recipientCountry != 'US')) {
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

        /*01 = Next Day Air
        02 = 2nd Day Air
        03 = Ground
        07 = Express
        08 = Expedited
        11 = UPS Standard
        12 = 3 Day Select
        13 = Next Day Air Saver
        14 = UPS Next Day Air® Early
        17 = UPS Worldwide Economy DDU
        54 = Express Plus
        59 = 2nd Day Air A.M.
        65 = UPS Saver
        M2 = First Class Mail
        M3 = Priority Mail
        M4 = Expedited MaiI Innovations
        M5 = Priority Mail Innovations
        M6 = Economy Mail Innovations
        M7 = MaiI Innovations (MI) Returns
        70 = UPS Access Point™ Economy
        71 = UPS Worldwide Express Freight Midday
        72 = UPS Worldwide Economy DDP
        74 = UPS Express®12:00
        75 = UPS Heavy Goods
        82 = UPS Today Standard
        83 = UPS Today Dedicated Courier
        84 = UPS Today Intercity
        85 = UPS Today Express
        86 = UPS Today Express Saver
        93 = Ground Saver
        96 = UPS Worldwide Express Freight.
        C6 = Roadie XD AM (Morning delivery)
        C7 = Roadie XD PM (Afternoon delivery)
        C8 = Roadie XD (Anytime delivery)
        T0 = Master
        T1 = LTL*/

        return $mapping[$serviceType] ?? '03'; // 默认返回 Ground
    }

    public function mapUPSServiceType(string $serviceCode){
        $services = [
            '01' => 'UPS Next Day Air',
            '02' => 'UPS 2nd Day Air',
            '03' => 'UPS Ground',
            '07' => 'UPS Worldwide Express',
            '08' => 'UPS Worldwide Expedited',
            '11' => 'UPS Standard',
            '12' => 'UPS 3 Day Select',
            '13' => 'UPS Next Day Air Saver',
            '14' => 'UPS Next Day Air Early',
            '54' => 'UPS Worldwide Express Plus',
            '59' => 'UPS 2nd Day Air A.M.',
            '65' => 'UPS Worldwide Saver',
        ];

        return $services[$serviceCode] ?? 'Unknown Service';
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

    private function isInternationalShipment($shipper, $recipient): bool
    {
        return $shipper['Address']['CountryCode'] !== $recipient['Address']['CountryCode'];
    }

    private function prepareUPSProducts(array $data): array
    {
        $products = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $products[] = [
                    'Description' => $item['description'] ?: 'Auto Parts',
                    'CommodityCode' => $item['harmonizedCode'] ?: '',
                    'OriginCountryCode' => $item['countryOfManufacture'] ?: 'CN',
                    'Unit' => [
                        'Number' => $item['quantity'] ?? "1",
                        'Value' => $item['unitPrice'] ?? "1",
                        'UnitOfMeasurement' => [
                            'Code' => $item['UnitOfMeasurement'] ?: "PCS",
                        ]
                    ],
                    /*'ProductWeight' => [
                        'UnitOfMeasurement' => [
                            'Code' => 'LBS'
                        ],
                        'Weight' => $item['weight'] ?? 0
                    ]*/
                ];
            }
        }
        return $products;
    }

    private function mapSignatureType(string $type): string
    {
        $mapping = [
            'DIRECT' => 2, // 直接签名
            'INDIRECT' => 2, // 间接签名
            'ADULT' => 3, // 成人签名
            'SIGNATURE_REQUIRED' => 2, // 需要签名
            'SIGNATURE_NOT_REQUIRED' => 1, // 不需要签名
            'NO_SIGNATURE_REQUIRED' => 1, // 不需要签名
            'SIGNATURE_REQUIRED_INDIRECT' => 2, // 间接签名
            'SIGNATURE_REQUIRED_ADULT' => 3, // 成人签名
        ];

        return $mapping[$type] ?? 1; // 默认不需要直接签名
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

    private function saveLabelInfo(array $data, array $response)
    {
        // 从响应中提取跟踪号码
        $trackingNumber = $response['ShipmentResponse']['ShipmentResults']['ShipmentIdentificationNumber'] ?? null;
        
        if (!$trackingNumber) {
            return [];
        }

        // 从响应中提取标签URL
        //$labelUrl = $response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'] ?? null;

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
                'height' => $data['height'] ?? null,
                'box_id' => $data['box_id'] ?? null
            ];
        }

        $labelModel = config('shipping.database.models.shipping_label');

        if(isset($response['ShipmentResponse']['ShipmentResults']['Form'])) {
            $formdata = $response['ShipmentResponse']['ShipmentResults']['Form'];
        }

        //获取运费
                $shippingCost = isset($response['ShipmentResponse']['ShipmentResults']['NegotiatedRateCharges'])?
                    ($response['ShipmentResponse']['ShipmentResults']['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'] ?? 0)
                    :($response['ShipmentResponse']['ShipmentResults']['ShipmentCharges']['TotalCharges']['MonetaryValue'] ?? 0);

                
                $shipmentItemizedCharges = isset($response['ShipmentResponse']['ShipmentResults']['NegotiatedRateCharges']['ItemizedCharges'])?
                                            $response['ShipmentResponse']['ShipmentResults']['NegotiatedRateCharges']['ItemizedCharges']
                                            :$response['ShipmentResponse']['ShipmentResults']['ShipmentCharges']['ItemizedCharges'] ?? [];

                $shipmentfees = [];
                foreach($shipmentItemizedCharges as $itemizedcharge) {
                    $shipmentfee = $itemizedcharge['MonetaryValue'] ?? 0;
                    $shipmentfeeCode = $itemizedcharge['Code'] ?? '';
                    $shipmentfees[$shipmentfeeCode] = $shipmentfee;
                }

        $rs = [];
        foreach($packageInfo as $i=>$package) {
            $labelUrl = $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['ShippingLabel']['GraphicImage'] ?? '';
            $imageFormat = $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['ShippingLabel']['ImageFormat']['Code'] ?? '';
            $trackingNumber = $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['TrackingNumber'] ?? '';

            if($i>0){
                $shippingCost = 0; // 如果有多个包裹，使用第一个包裹的运费
                $shipmentfees = [];
            }

            $packageItemizedCharges = isset($response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['NegotiatedCharges']['ItemizedCharges'])?
                                            $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['NegotiatedCharges']['ItemizedCharges']
                                            :$response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['ItemizedCharges'] ?? [];

            $packagefees = [];
            $package_ahs = 0;
            foreach($packageItemizedCharges as $itemizedcharge) {
                $packagefee = $itemizedcharge['MonetaryValue'] ?? 0;
                $packagefeeCode = $itemizedcharge['Code'] ?? '';
                $packagefees[$packagefeeCode] = $packagefee;

                if($packagefeeCode == '100'){
                    $package_ahs = $packagefee;
                }
            }


            /*$shippingCost = $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['BaseServiceCharge']['MonetaryValue'] ?? 0;
            $shippingCost += $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['ServiceOptionsCharges']['MonetaryValue'] ?? 0;

            $itemizedcharges = $response['ShipmentResponse']['ShipmentResults']['PackageResults'][$i]['ItemizedCharges'] ?? [];
            foreach($itemizedcharges as $itemizedcharge) {
                $shippingCost += $itemizedcharge['MonetaryValue'] ?? 0;
            }

            if($shippingCost == 0){
                $shippingCost = isset($response['ShipmentResponse']['ShipmentResults']['NegotiatedRateCharges'])?
                ($response['ShipmentResponse']['ShipmentResults']['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'] ?? 0)
                :($response['ShipmentResponse']['ShipmentResults']['ShipmentCharges']['TotalCharges']['MonetaryValue'] ?? 0);

                if($i>0)$shippingCost = 0; // 如果有多个包裹，使用第一个包裹的运费
            }*/

            $markup = 1.0 + $this->markup;

            $rs[] = [
                'carrier' => 'ups',
                'account_name' => $this->accountName,
                'account_number' => $this->carrierAccount,
                'tracking_number' => $trackingNumber,
                'invoice_number' => $data['invoice_number'] ?? null,
                'market_order_id' => $data['market_order_id'] ?? null,
                "customer_po_number" => $data['customer_po_number'] ?? null,
                "box_id" => $package['box_id'] ?? 0,
                'service_type' => $data['service_type'] ?? null,
                'shipping_cost' => $shippingCost * $markup,
                'shipping_cost_base' => $shippingCost,
                'label_url' => $labelUrl,
                'image_format' => $imageFormat,
                'label_data' => '',//$response,
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
                'package_info' => $package,
                'formdata' => isset($formdata) ? $formdata : [],
                'status' => 'ACTIVE',
                'shipmentfees' => $shipmentfees,
                'packagefees' => $packagefees,
                'package_ahs' => $package_ahs
            ];
       
            // 创建标签记录
            $labelModel::create($rs[$i]);
        }

        //return $response;
        return $rs;
    }
} 