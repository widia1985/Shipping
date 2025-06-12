<?php

namespace Widia\Shipping;

use Widia\Shipping\Carriers\FedEx;
use Widia\Shipping\Carriers\UPS;
use Exception;

class RateComparison
{
    protected array $carriers = [];
    protected array $rates = [];
    protected array $cheapestRates = [];

    public function __construct(array $carriers = [])
    {
        $this->initializeCarriers($carriers);
    }

    protected function initializeCarriers(array $carriers): void
    {
        // 如果没有指定承运商，使用所有可用的承运商
        if (empty($carriers)) {
            $this->carriers = [
                'fedex' => new FedEx(),
                'ups' => new UPS()
            ];
            return;
        }

        // 初始化指定的承运商
        foreach ($carriers as $carrier) {
            switch (strtolower($carrier)) {
                case 'fedex':
                    $this->carriers['fedex'] = new FedEx();
                    break;
                case 'ups':
                    $this->carriers['ups'] = new UPS();
                    break;
                default:
                    throw new Exception("Unsupported carrier: {$carrier}");
            }
        }
    }

    public function compareRates(array $data, array $carriers = []): array
    {
        // 如果指定了新的承运商，重新初始化
        if (!empty($carriers)) {
            $this->initializeCarriers($carriers);
        }

        $this->rates = [];
        $this->cheapestRates = [];

        // 获取每个承运商的费率
        foreach ($this->carriers as $name => $carrier) {
            try {
                // 设置账户
                if (isset($data['account_number'])) {
                    $carrier->setAccount($data['account_number']);
                }

                // 获取费率
                $rates = $carrier->getRates($data);
                $this->processCarrierRates($name, $rates);
            } catch (Exception $e) {
                // 记录错误但继续处理其他承运商
                $this->rates[$name] = [
                    'error' => $e->getMessage()
                ];
            }
        }

        // 找出每个服务类型的最便宜选项
        $this->findCheapestRates();

        return [
            'all_rates' => $this->rates,
            'cheapest_rates' => $this->cheapestRates
        ];
    }

    protected function processCarrierRates(string $carrier, array $rates): void
    {
        $processedRates = [];

        switch ($carrier) {
            case 'fedex':
                $processedRates = $this->processFedExRates($rates);
                break;
            case 'ups':
                $processedRates = $this->processUPSRates($rates);
                break;
        }

        $this->rates[$carrier] = $processedRates;
    }

    protected function processFedExRates(array $rates): array
    {
        $processedRates = [];

        if (isset($rates['output']['rateReplyDetails'])) {
            foreach ($rates['output']['rateReplyDetails'] as $rate) {
                $serviceType = $rate['serviceType'] ?? 'UNKNOWN';
                $totalCharge = $rate['ratedShipmentDetails'][0]['totalNetCharge'] ?? 0;
                $currency = $rate['ratedShipmentDetails'][0]['currency'] ?? 'USD';

                $processedRates[$serviceType] = [
                    'carrier' => 'fedex',
                    'service_type' => $serviceType,
                    'total_charge' => $totalCharge,
                    'currency' => $currency,
                    'delivery_time' => $rate['deliveryDate'] ?? null,
                    'transit_time' => $rate['transitTime'] ?? null
                ];
            }
        }

        return $processedRates;
    }

    private function processUPSRates(array $rates): array
    {
        $processedRates = [];
        foreach ($rates as $rate) {
            $serviceType = $rate['Service']['Code'] ?? '';
            $serviceName = $this->mapUPSServiceType($serviceType);
            
            // 检查是否有协商费率
            $hasNegotiatedRate = isset($rate['NegotiatedRates']) && !empty($rate['NegotiatedRates']);
            
            // 获取费率信息
            if ($hasNegotiatedRate) {
                $totalCharge = $rate['NegotiatedRates'][0]['TotalCharge']['MonetaryValue'] ?? 0;
                $currency = $rate['NegotiatedRates'][0]['TotalCharge']['CurrencyCode'] ?? 'USD';
            } else {
                $totalCharge = $rate['TotalCharge']['MonetaryValue'] ?? 0;
                $currency = $rate['TotalCharge']['CurrencyCode'] ?? 'USD';
            }

            $processedRates[] = [
                'carrier' => 'UPS',
                'service_type' => $serviceName,
                'service_code' => $serviceType,
                'total_charge' => (float) $totalCharge,
                'currency' => $currency,
                'delivery_time' => $rate['GuaranteedDaysToDelivery'] ?? null,
                'transit_time' => $rate['TransitTime'] ?? null,
                'is_negotiated_rate' => $hasNegotiatedRate,
                'rate_details' => [
                    'base_charge' => $rate['BaseCharge']['MonetaryValue'] ?? 0,
                    'service_options_charges' => $rate['ServiceOptionsCharges']['MonetaryValue'] ?? 0,
                    'total_surcharges' => $rate['TotalSurcharges']['MonetaryValue'] ?? 0,
                    'negotiated_rate' => $hasNegotiatedRate ? $totalCharge : null
                ]
            ];
        }
        return $processedRates;
    }

    protected function findCheapestRates(): void
    {
        $serviceTypes = [];

        // 收集所有服务类型
        foreach ($this->rates as $carrierRates) {
            if (isset($carrierRates['error'])) {
                continue;
            }
            foreach ($carrierRates as $serviceType => $rate) {
                $serviceTypes[$serviceType] = true;
            }
        }

        // 对每个服务类型找出最便宜的选项
        foreach ($serviceTypes as $serviceType => $_) {
            $cheapestRate = null;
            $cheapestCarrier = null;

            foreach ($this->rates as $carrier => $carrierRates) {
                if (isset($carrierRates['error']) || !isset($carrierRates[$serviceType])) {
                    continue;
                }

                $rate = $carrierRates[$serviceType];
                if ($cheapestRate === null || $rate['total_charge'] < $cheapestRate['total_charge']) {
                    $cheapestRate = $rate;
                    $cheapestCarrier = $carrier;
                }
            }

            if ($cheapestRate !== null) {
                $this->cheapestRates[$serviceType] = $cheapestRate;
            }
        }

        // 按价格排序
        uasort($this->cheapestRates, function ($a, $b) {
            return $a['total_charge'] <=> $b['total_charge'];
        });
    }

    public function getCheapestOverall(): ?array
    {
        if (empty($this->cheapestRates)) {
            return null;
        }

        // 返回最便宜的第一个选项
        return reset($this->cheapestRates);
    }

    public function getCheapestByServiceType(string $serviceType): ?array
    {
        return $this->cheapestRates[$serviceType] ?? null;
    }

    public function getRatesByCarrier(string $carrier): array
    {
        return $this->rates[$carrier] ?? [];
    }
} 