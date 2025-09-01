<?php

namespace Widia\Shipping\Carriers;

use Widia\Shipping\Contracts\CarrierInterface;
use GuzzleHttp\Client;

abstract class AbstractCarrier implements CarrierInterface
{
    protected $client;
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'base_uri' => $this->getBaseUrl(),
            'headers' => $this->getHeaders(),
        ]);
    }

    abstract protected function getBaseUrl(): string;
    abstract protected function getHeaders(): array;
} 