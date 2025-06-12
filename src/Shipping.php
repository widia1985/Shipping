<?php

/**
 * Compare rates between multiple carriers
 *
 * @param array $data Shipping data
 * @param array $carriers List of carriers to compare (optional)
 * @return array Comparison results
 */
public function compareRates(array $data, array $carriers = []): array
{
    $comparison = new RateComparison($carriers);
    return $comparison->compareRates($data);
}

/**
 * Get the cheapest shipping option across all carriers
 *
 * @param array $data Shipping data
 * @param array $carriers List of carriers to compare (optional)
 * @return array|null Cheapest shipping option
 */
public function getCheapestRate(array $data, array $carriers = []): ?array
{
    $comparison = new RateComparison($carriers);
    $comparison->compareRates($data);
    return $comparison->getCheapestOverall();
}

/**
 * Get the cheapest rate for a specific service type
 *
 * @param array $data Shipping data
 * @param string $serviceType Service type to compare
 * @param array $carriers List of carriers to compare (optional)
 * @return array|null Cheapest rate for the service type
 */
public function getCheapestRateByService(array $data, string $serviceType, array $carriers = []): ?array
{
    $comparison = new RateComparison($carriers);
    $comparison->compareRates($data);
    return $comparison->getCheapestByServiceType($serviceType);
} 