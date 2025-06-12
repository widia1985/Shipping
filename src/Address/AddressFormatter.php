<?php

namespace Widia\Shipping\Address;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AddressFormatter
{
    protected array $countryCodeMap = [
        'UNITED STATES' => 'US',
        'USA' => 'US',
        'U.S.A.' => 'US',
        'U.S.' => 'US',
        'CANADA' => 'CA',
        'MEXICO' => 'MX',
        'UNITED KINGDOM' => 'GB',
        'UK' => 'GB',
        'GREAT BRITAIN' => 'GB',
        'ENGLAND' => 'GB',
        'SCOTLAND' => 'GB',
        'WALES' => 'GB',
        'NORTHERN IRELAND' => 'GB',
        'FRANCE' => 'FR',
        'GERMANY' => 'DE',
        'ITALY' => 'IT',
        'SPAIN' => 'ES',
        'PORTUGAL' => 'PT',
        'NETHERLANDS' => 'NL',
        'BELGIUM' => 'BE',
        'SWITZERLAND' => 'CH',
        'AUSTRIA' => 'AT',
        'SWEDEN' => 'SE',
        'NORWAY' => 'NO',
        'DENMARK' => 'DK',
        'FINLAND' => 'FI',
        'POLAND' => 'PL',
        'CZECH REPUBLIC' => 'CZ',
        'SLOVAKIA' => 'SK',
        'HUNGARY' => 'HU',
        'ROMANIA' => 'RO',
        'BULGARIA' => 'BG',
        'GREECE' => 'GR',
        'TURKEY' => 'TR',
        'RUSSIA' => 'RU',
        'CHINA' => 'CN',
        'JAPAN' => 'JP',
        'KOREA' => 'KR',
        'SOUTH KOREA' => 'KR',
        'TAIWAN' => 'TW',
        'HONG KONG' => 'HK',
        'SINGAPORE' => 'SG',
        'AUSTRALIA' => 'AU',
        'NEW ZEALAND' => 'NZ',
        'BRAZIL' => 'BR',
        'ARGENTINA' => 'AR',
        'CHILE' => 'CL',
        'COLOMBIA' => 'CO',
        'PERU' => 'PE',
        'VENEZUELA' => 'VE',
        'SOUTH AFRICA' => 'ZA',
        'EGYPT' => 'EG',
        'ISRAEL' => 'IL',
        'SAUDI ARABIA' => 'SA',
        'UNITED ARAB EMIRATES' => 'AE',
        'UAE' => 'AE',
        'QATAR' => 'QA',
        'KUWAIT' => 'KW',
        'BAHRAIN' => 'BH',
        'OMAN' => 'OM',
        'INDIA' => 'IN',
        'PAKISTAN' => 'PK',
        'BANGLADESH' => 'BD',
        'SRI LANKA' => 'LK',
        'MALAYSIA' => 'MY',
        'INDONESIA' => 'ID',
        'PHILIPPINES' => 'PH',
        'THAILAND' => 'TH',
        'VIETNAM' => 'VN',
        'CAMBODIA' => 'KH',
        'LAOS' => 'LA',
        'MYANMAR' => 'MM',
        'BURMA' => 'MM',
    ];

    protected array $stateCodeMap = [
        // US States
        'ALABAMA' => 'AL',
        'ALASKA' => 'AK',
        'ARIZONA' => 'AZ',
        'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA',
        'COLORADO' => 'CO',
        'CONNECTICUT' => 'CT',
        'DELAWARE' => 'DE',
        'FLORIDA' => 'FL',
        'GEORGIA' => 'GA',
        'HAWAII' => 'HI',
        'IDAHO' => 'ID',
        'ILLINOIS' => 'IL',
        'INDIANA' => 'IN',
        'IOWA' => 'IA',
        'KANSAS' => 'KS',
        'KENTUCKY' => 'KY',
        'LOUISIANA' => 'LA',
        'MAINE' => 'ME',
        'MARYLAND' => 'MD',
        'MASSACHUSETTS' => 'MA',
        'MICHIGAN' => 'MI',
        'MINNESOTA' => 'MN',
        'MISSISSIPPI' => 'MS',
        'MISSOURI' => 'MO',
        'MONTANA' => 'MT',
        'NEBRASKA' => 'NE',
        'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH',
        'NEW JERSEY' => 'NJ',
        'NEW MEXICO' => 'NM',
        'NEW YORK' => 'NY',
        'NORTH CAROLINA' => 'NC',
        'NORTH DAKOTA' => 'ND',
        'OHIO' => 'OH',
        'OKLAHOMA' => 'OK',
        'OREGON' => 'OR',
        'PENNSYLVANIA' => 'PA',
        'RHODE ISLAND' => 'RI',
        'SOUTH CAROLINA' => 'SC',
        'SOUTH DAKOTA' => 'SD',
        'TENNESSEE' => 'TN',
        'TEXAS' => 'TX',
        'UTAH' => 'UT',
        'VERMONT' => 'VT',
        'VIRGINIA' => 'VA',
        'WASHINGTON' => 'WA',
        'WEST VIRGINIA' => 'WV',
        'WISCONSIN' => 'WI',
        'WYOMING' => 'WY',
        // Canadian Provinces
        'ALBERTA' => 'AB',
        'BRITISH COLUMBIA' => 'BC',
        'MANITOBA' => 'MB',
        'NEW BRUNSWICK' => 'NB',
        'NEWFOUNDLAND AND LABRADOR' => 'NL',
        'NORTHWEST TERRITORIES' => 'NT',
        'NOVA SCOTIA' => 'NS',
        'NUNAVUT' => 'NU',
        'ONTARIO' => 'ON',
        'PRINCE EDWARD ISLAND' => 'PE',
        'QUEBEC' => 'QC',
        'SASKATCHEWAN' => 'SK',
        'YUKON' => 'YT',
        // Canadian Provinces (French)
        'ALBERTA' => 'AB',
        'COLOMBIE-BRITANNIQUE' => 'BC',
        'MANITOBA' => 'MB',
        'NOUVEAU-BRUNSWICK' => 'NB',
        'TERRE-NEUVE-ET-LABRADOR' => 'NL',
        'TERRITOIRES DU NORD-OUEST' => 'NT',
        'NOUVELLE-ÉCOSSE' => 'NS',
        'NUNAVUT' => 'NU',
        'ONTARIO' => 'ON',
        'ÎLE-DU-PRINCE-ÉDOUARD' => 'PE',
        'QUÉBEC' => 'QC',
        'SASKATCHEWAN' => 'SK',
        'YUKON' => 'YT',
    ];

    protected array $serviceTypeMap = [
        'UPS' => [
            'domestic' => [
                'GROUND' => '03',
                'NEXT_DAY_AIR' => '01',
                'SECOND_DAY_AIR' => '02',
                'THREE_DAY_SELECT' => '12',
                'NEXT_DAY_AIR_SAVER' => '13',
                'NEXT_DAY_AIR_EARLY' => '14',
                'SECOND_DAY_AIR_AM' => '59',
            ],
            'international' => [
                'WORLDWIDE_EXPRESS' => '07',
                'WORLDWIDE_EXPEDITED' => '08',
                'STANDARD' => '11',
            ],
            'domestic_to_international' => [
                'GROUND' => 'WORLDWIDE_EXPEDITED',
                'NEXT_DAY_AIR' => 'WORLDWIDE_EXPRESS',
                'SECOND_DAY_AIR' => 'WORLDWIDE_EXPEDITED',
                'THREE_DAY_SELECT' => 'WORLDWIDE_EXPEDITED',
                'NEXT_DAY_AIR_SAVER' => 'WORLDWIDE_EXPRESS',
                'NEXT_DAY_AIR_EARLY' => 'WORLDWIDE_EXPRESS',
                'SECOND_DAY_AIR_AM' => 'WORLDWIDE_EXPRESS',
            ],
            'international_to_domestic' => [
                'WORLDWIDE_EXPRESS' => 'NEXT_DAY_AIR',
                'WORLDWIDE_EXPEDITED' => 'GROUND',
                'STANDARD' => 'GROUND',
            ],
        ],
        'FEDEX' => [
            'domestic' => [
                'GROUND' => 'GROUND',
                'EXPRESS' => 'EXPRESS',
                'EXPRESS_SAVER' => 'EXPRESS_SAVER',
                'FIRST_OVERNIGHT' => 'FIRST_OVERNIGHT',
                'PRIORITY_OVERNIGHT' => 'PRIORITY_OVERNIGHT',
                'STANDARD_OVERNIGHT' => 'STANDARD_OVERNIGHT',
            ],
            'international' => [
                'INTERNATIONAL_ECONOMY' => 'INTERNATIONAL_ECONOMY',
                'INTERNATIONAL_PRIORITY' => 'INTERNATIONAL_PRIORITY',
                'INTERNATIONAL_FIRST' => 'INTERNATIONAL_FIRST',
                'INTERNATIONAL_GROUND' => 'INTERNATIONAL_GROUND',
            ],
            'domestic_to_international' => [
                'GROUND' => 'INTERNATIONAL_GROUND',
                'EXPRESS' => 'INTERNATIONAL_PRIORITY',
                'EXPRESS_SAVER' => 'INTERNATIONAL_ECONOMY',
                'FIRST_OVERNIGHT' => 'INTERNATIONAL_FIRST',
                'PRIORITY_OVERNIGHT' => 'INTERNATIONAL_PRIORITY',
                'STANDARD_OVERNIGHT' => 'INTERNATIONAL_PRIORITY',
            ],
            'international_to_domestic' => [
                'INTERNATIONAL_ECONOMY' => 'EXPRESS_SAVER',
                'INTERNATIONAL_PRIORITY' => 'EXPRESS',
                'INTERNATIONAL_FIRST' => 'FIRST_OVERNIGHT',
                'INTERNATIONAL_GROUND' => 'GROUND',
            ],
        ],
    ];

    protected Client $client;
    protected string $validationUrl;
    protected array $internationalServices;
    protected string $carrier;
    protected bool $isResidential = false;
    protected string $currentCountryCode = '';

    public function __construct(Client $client, string $validationUrl, array $internationalServices = [], string $carrier = 'UPS')
    {
        $this->client = $client;
        $this->validationUrl = $validationUrl;
        $this->internationalServices = $internationalServices;
        $this->carrier = strtoupper($carrier);
    }

    public function format(array $address, string $serviceType = null): array
    {
        // 1. 转换国家代码
        $this->currentCountryCode = $this->normalizeCountryCode($address['address']['countryCode'] ?? $address['address']['country']);
        
        // 2. 转换服务类型
        if ($serviceType) {
            $serviceType = $this->convertServiceType($serviceType, $this->currentCountryCode);
        }

        // 3. 检查服务类型是否支持国际运输
        if ($serviceType && $this->currentCountryCode !== 'US' && !in_array($serviceType, $this->internationalServices)) {
            throw new \Exception("Selected service type does not support international shipping to {$this->currentCountryCode}");
        }

        // 4. 转换州/省代码（如果是美国或加拿大地址）
        $stateCode = in_array($this->currentCountryCode, ['US', 'CA']) ? 
            $this->normalizeStateCode($address['address']['stateOrProvinceCode'] ?? $address['address']['state'], $this->currentCountryCode) : 
            $address['address']['stateOrProvinceCode'];

        // 5. 格式化电话号码
        $phoneNumber = $this->formatPhoneNumber($address['contact']['phoneNumber'], $this->currentCountryCode);

        // 6. 清理和格式化地址行
        $streetLines = $this->cleanAddressLines($address['address']['streetLines']);

        // 7. 验证地址
        $validatedAddress = $this->validateAddress([
            'address' => [
                'streetLines' => $streetLines,
                'city' => $address['address']['city'],
                'stateOrProvinceCode' => $stateCode,
                'postalCode' => $address['address']['postalCode'],
                'countryCode' => $this->currentCountryCode
            ],
            'contact' => [
                'personName' => $address['contact']['personName'],
                'phoneNumber' => $phoneNumber,
                'emailAddress' => $address['contact']['emailAddress']
            ]
        ]);

        // 8. 添加转换后的服务类型
        if ($serviceType) {
            $validatedAddress['service_type'] = $serviceType;
        }

        return $validatedAddress;
    }

    public function isResidential(): bool
    {
        return $this->isResidential;
    }

    public function getCountryCode(): string
    {
        return $this->currentCountryCode;
    }

    protected function normalizeCountryCode(string $country): string
    {
        $country = strtoupper(trim($country));
        return $this->countryCodeMap[$country] ?? $country;
    }

    protected function normalizeStateCode(string $state, string $countryCode = 'US'): string
    {
        $state = strtoupper(trim($state));
        
        // 如果已经是标准代码格式（2个字母），直接返回
        if (strlen($state) === 2 && ctype_alpha($state)) {
            return $state;
        }

        // 根据国家代码使用不同的映射
        if ($countryCode === 'CA') {
            // 加拿大省份代码映射
            $canadianProvinces = [
                'ALBERTA' => 'AB',
                'BRITISH COLUMBIA' => 'BC',
                'MANITOBA' => 'MB',
                'NEW BRUNSWICK' => 'NB',
                'NEWFOUNDLAND AND LABRADOR' => 'NL',
                'NORTHWEST TERRITORIES' => 'NT',
                'NOVA SCOTIA' => 'NS',
                'NUNAVUT' => 'NU',
                'ONTARIO' => 'ON',
                'PRINCE EDWARD ISLAND' => 'PE',
                'QUEBEC' => 'QC',
                'SASKATCHEWAN' => 'SK',
                'YUKON' => 'YT',
                // French names
                'COLOMBIE-BRITANNIQUE' => 'BC',
                'NOUVEAU-BRUNSWICK' => 'NB',
                'TERRE-NEUVE-ET-LABRADOR' => 'NL',
                'TERRITOIRES DU NORD-OUEST' => 'NT',
                'NOUVELLE-ÉCOSSE' => 'NS',
                'ÎLE-DU-PRINCE-ÉDOUARD' => 'PE',
                'QUÉBEC' => 'QC',
            ];
            return $canadianProvinces[$state] ?? $state;
        }

        // 美国州代码映射
        return $this->stateCodeMap[$state] ?? $state;
    }

    protected function formatPhoneNumber(string $phone, string $countryCode): string
    {
        // 移除所有非数字字符
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // 根据不同国家格式化电话号码
        switch ($countryCode) {
            case 'US':
            case 'CA':
                // 确保是10位数字
                if (strlen($phone) === 10) {
                    return $phone;
                } elseif (strlen($phone) === 11 && $phone[0] === '1') {
                    return substr($phone, 1);
                }
                break;
            case 'GB':
                // 英国电话号码格式
                if (strlen($phone) === 11 && $phone[0] === '0') {
                    return substr($phone, 1);
                }
                break;
            // 添加其他国家的电话号码格式化规则
        }
        
        return $phone;
    }

    protected function cleanAddressLines($streetLines): array
    {
        if (!is_array($streetLines)) {
            $streetLines = [$streetLines];
        }

        return array_map(function($line) {
            // 移除特殊字符，但保留基本标点
            $line = preg_replace('/[^\p{L}\p{N}\s\-\.,#]/u', '', $line);
            // 标准化空格
            $line = preg_replace('/\s+/', ' ', $line);
            return trim($line);
        }, $streetLines);
    }

    protected function validateAddress(array $address): array
    {
        try {
            $response = $this->client->post($this->validationUrl, [
                'json' => [
                    'AddressValidationRequest' => [
                        'Address' => [
                            'AddressLine' => $address['address']['streetLines'],
                            'City' => $address['address']['city'],
                            'StateProvinceCode' => $address['address']['stateOrProvinceCode'],
                            'PostalCode' => $address['address']['postalCode'],
                            'CountryCode' => $address['address']['countryCode']
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // 检查地址验证结果
            if (!isset($result['AddressValidationResponse']['ValidAddressIndicator'])) {
                throw new \Exception('Address validation failed');
            }

            // 如果地址有效，使用验证后的地址
            if ($result['AddressValidationResponse']['ValidAddressIndicator'] === 'Y') {
                $validatedAddress = $result['AddressValidationResponse']['Address'];
                $address['address'] = array_merge($address['address'], [
                    'streetLines' => $validatedAddress['AddressLine'] ?? $address['address']['streetLines'],
                    'city' => $validatedAddress['City'] ?? $address['address']['city'],
                    'stateOrProvinceCode' => $validatedAddress['StateProvinceCode'] ?? $address['address']['stateOrProvinceCode'],
                    'postalCode' => $validatedAddress['PostalCode'] ?? $address['address']['postalCode'],
                ]);
            }

            // 检查是否是住宅地址
            $this->isResidential = isset($result['AddressValidationResponse']['ResidentialIndicator']) && 
                                  $result['AddressValidationResponse']['ResidentialIndicator'] === 'Y';
            $address['isResidential'] = $this->isResidential;

            return $address;
        } catch (GuzzleException $e) {
            throw new \Exception('Address validation failed: ' . $e->getMessage());
        }
    }

    protected function convertServiceType(string $serviceType, string $countryCode): string
    {
        $carrierMap = $this->serviceTypeMap[$this->carrier] ?? null;
        if (!$carrierMap) {
            return $serviceType;
        }

        // 检查是否是国际服务
        $isInternationalService = in_array($serviceType, array_keys($carrierMap['international']));
        
        // 如果是美国地址且是国际服务，转换为国内服务
        if ($countryCode === 'US' && $isInternationalService) {
            return $carrierMap['international_to_domestic'][$serviceType] ?? $serviceType;
        }
        
        // 如果不是美国地址且是国内服务，转换为国际服务
        if ($countryCode !== 'US' && !$isInternationalService) {
            return $carrierMap['domestic_to_international'][$serviceType] ?? $serviceType;
        }

        return $serviceType;
    }
} 