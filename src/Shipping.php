<?php

namespace Widia\Shipping;

use Widia\Shipping\Carriers\CarrierInterface;
use Widia\Shipping\Exceptions\CarrierNotFoundException;
use Widia\Shipping\Exceptions\InvalidCarrierException;
use Widia\Shipping\RateComparison;
use Widia\Shipping\Carriers\FedEx;
use Widia\Shipping\Carriers\UPS;

class Shipping
{
    /** @var CarrierInterface|null */
    protected $carrier = null;
    
    /** @var array */
    protected $carriers = [
        'fedex' => FedEx::class,
        'ups' => UPS::class
    ];
    protected $carrierName = null;

    /**
     * @param string $carrier
     * @return self
     * @throws CarrierNotFoundException
     */
    public function setCarrier(array $carrierconfig): self
    {
        
        if (!is_array($carrierconfig)) {
            throw new CarrierNotFoundException("Carrier '{$carrier}' not found");
        }

        foreach($carrierconfig as $carrierName => $carrierInfo) {
            $carrierClassName = $this->carriers[strtolower($carrierName)] ?? null;
            $this->carrierName = $carrierName;
            if (!class_exists($carrierClassName)) {
                throw new CarrierNotFoundException("Carrier class '{$carrierClassName}' does not exist");
            }
            /*if (!is_subclass_of($carrierClassName, CarrierInterface::class)) {
                throw new InvalidCarrierException("Carrier '{$carrierClassName}' does not implement CarrierInterface");
            }*/

            $this->carrier = new $carrierClassName();
            if(is_array($carrierInfo)){
                foreach($carrierInfo as $key => $value) {
                    $carrierAccountName = $key;
                    $carrierAccountNumber = $value;
                }
            }
            else{
                $carrierAccountName = $carrierInfo;
                $carrierAccountNumber = null;
            }

            if (method_exists($this->carrier, 'setAccount')) {
                $this->carrier->setAccount($carrierAccountName);
            }

            if ($carrierAccountNumber && method_exists($this->carrier, 'setCarrierAccount')) {
                $this->carrier->setCarrierAccount($carrierAccountNumber);
            }
            $account = config('shipping.'.$carrierAccountName.'.account_number') ?? null;
            $markup = config('shipping.'.$carrierAccountName.'.markup') ?? null;
            if($markup != null && method_exists($this->carrier, 'setMarkup')){
                $this->carrier->setMarkup($markup);
            }
        }

        return $this;
    }

    /**
     * @return CarrierInterface|null
     */
    public function getCarrier()
    {
        return $this->carrier;
    }

    /**
     * @param array $data
     * @param array $carriers
     * @return array
     */
    public function compareRates(array $data, array $carriers = []): array
    {
        $comparison = new RateComparison();
        return $comparison->compareRates($data, $carriers);
    }

    /**
     * @param array $data
     * @return array
     * @throws InvalidCarrierException
     */
    public function getRates(array $data): array
    {
        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        return $this->carrier->getRates($data);
    }

    /**
     * @param array $data
     * @return array
     * @throws InvalidCarrierException
     */
    public function createLabel(array $data): array
    {
        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        return $this->carrier->createLabel($data);
    }

    /**
     * @param string $trackingNumber
     * @return bool
     * @throws InvalidCarrierException
     */
    public function cancelLabel(string $trackingNumber,$cancelReason =''): bool
    {
        if (!$trackingNumber) {
            throw new InvalidCarrierException('Tracking number is required');
        }

        $labelModel = config('shipping.database.models.shipping_label');
        $label = $labelModel::where('tracking_number', $trackingNumber)->first();
        if ($label && $label->status === 'CANCELLED') {
            return true; // Already cancelled
        }

        if(!$this->carrier && $label){
            self::setCarrier([$label->carrier=>[$label->account_name=>$label->account_number]]);
        }

        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        $result = $this->carrier->cancelLabel($trackingNumber);

        if($result){
            // Update label status to cancelled
            if ($label) {
                $label->cancel($cancelReason, '');
            }
        }

        return $result;
    }

    public function cancelLabelByLabelModel($labelModel,$cancelReason =''): bool
    {
        if (!$labelModel || !$labelModel->tracking_number) {
            throw new InvalidCarrierException('Label Model is required');
        }

        $label = $labelModel;
        $trackingNumber = $label->tracking_number;

        if ($label && $label->status === 'CANCELLED') {
            return true; // Already cancelled
        }

        if(!$this->carrier && $label){
            self::setCarrier([$label->carrier=>[$label->account_name=>$label->account_number]]);
        }

        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        $result = $this->carrier->cancelLabel($trackingNumber);

        if($result){
            // Update label status to cancelled
            if ($label) {
                $label->cancel($cancelReason, '');
            }
        }

        return $result;
    }

    /**
     * @param string $trackingNumber
     * @return array
     * @throws InvalidCarrierException
     */
    public function trackShipment(string $trackingNumber): array
    {
        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        return $this->carrier->trackShipment($trackingNumber);
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

    public function validateAddress(array $data)
    {
        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        return $this->carrier->validateAddresstoResponse($data);
    }
    public function createReturnLabel(array $data)
    {
        if (!$this->carrier) {
            throw new InvalidCarrierException('No carrier selected');
        }

        return $this->carrier->createReturnLabel($data);
    }
    public function createTag(array $data)
    {
        if (empty($this->carrierName) && $this->carrierName != 'fedex') {
            throw new CarrierNotFoundException('This carrier is not available for this method');
        }
        return $this->carrier->createTag($data);
    }
} 