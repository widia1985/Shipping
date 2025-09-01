<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AddressFormatter
{
    private $client;
    private $endpoint;

    public function __construct(Client $client, string $endpoint)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
    }

    public function formatAddress(array $address): array
    {
        try {
            // 打印请求数据
            dump('Address Validation Request:', [
                'url' => $this->client->getConfig('base_uri') . $this->endpoint,
                'headers' => $this->getHeaders(),
                'data' => [
                    'addressesToValidate' => [
                        [
                            'address' => [
                                'streetLines' => [$address['address_line1']],
                                'city' => $address['city'],
                                'stateOrProvinceCode' => $address['state'],
                                'postalCode' => $address['postal_code'],
                                'countryCode' => $address['country']
                            ]
                        ]
                    ]
                ]
            ]);

            $response = $this->client->post($this->endpoint, [
                'json' => [
                    'addressesToValidate' => [
                        [
                            'address' => [
                                'streetLines' => [$address['address_line1']],
                                'city' => $address['city'],
                                'stateOrProvinceCode' => $address['state'],
                                'postalCode' => $address['postal_code'],
                                'countryCode' => $address['country']
                            ]
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // 打印响应数据
            dump('Address Validation Response:', $result);

            if (isset($result['output'][0]['resolvedAddresses'][0])) {
                $resolvedAddress = $result['output'][0]['resolvedAddresses'][0];
                return [
                    'address_line1' => $resolvedAddress['streetLines'][0] ?? $address['address_line1'],
                    'city' => $resolvedAddress['city'] ?? $address['city'],
                    'state' => $resolvedAddress['stateOrProvinceCode'] ?? $address['state'],
                    'postal_code' => $resolvedAddress['postalCode'] ?? $address['postal_code'],
                    'country' => $resolvedAddress['countryCode'] ?? $address['country']
                ];
            }

            return $address;
        } catch (GuzzleException $e) {
            // 打印错误响应
            if ($e->hasResponse()) {
                dump('Address Validation Error Response:', [
                    'status' => $e->getResponse()->getStatusCode(),
                    'body' => json_decode($e->getResponse()->getBody()->getContents(), true)
                ]);
            }
            throw new \Exception("Address validation failed: " . $e->getMessage());
        }
    }

    private function getHeaders()
    {
        // Implement the logic to retrieve headers based on the client configuration
        // This is a placeholder and should be replaced with the actual implementation
        return [];
    }
} 