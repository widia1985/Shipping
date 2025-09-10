<?php
namespace Widia\Shipping\Payloads\FedEx\Traits;
trait Common
{
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
    private function mapServiceType(string $serviceType): string
    {
        if (empty($serviceType) || !is_string($serviceType)) {
            $serviceType = 'GROUND SERVICE';
        }
        // 标准化服务类型名称
        $serviceType = $this->normalizeServiceType($serviceType);

        // 获取收件人国家代码和住宅地址标志
        $recipientCountry = $this->addressFormatter->getCountryCode() ?? '';
        $isResidential = $this->addressFormatter->isResidential() ?? false;
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
            'SMART_POST'
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
}