<?php

namespace Widia\Shipping\Carriers;

use Widia\Shipping\Address\AddressFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Carbon\Carbon;
use Widia\Shipping\Models\ShippingLabel;
use Widia\Shipping\Payloads\FedEx\ShipmentPayloads;
use Widia\Shipping\Payloads\FedEx\RatePayloads;
use Widia\Shipping\Payloads\FedEx\CancelPayloads;
use Widia\Shipping\Payloads\FedEx\TagPayloads;

class FedEx extends AbstractCarrier
{
    protected $test_url = 'https://apis-sandbox.fedex.com';
    protected $live_url = 'https://apis.fedex.com';
    protected $url;
    protected $token = null;
    protected $addressFormatter;
    protected $internationalServices = [
        'INTERNATIONAL_ECONOMY',
        'INTERNATIONAL_PRIORITY',
        'INTERNATIONAL_FIRST',
        'INTERNATIONAL_GROUND',
    ];
    protected $accountName;
    protected $carrierAccount;
    protected $markup = 0;
    protected $shipmentPayloads;
    protected $ratePayloads;
    protected $cancelPayloads;
    protected $tagPayloads;
    public function __construct()
    {

    }
    /*public function setAccount(string $accountName): void
    {
        $this->accountName = $accountName;
    }*/
    public function setCarrierAccount(string $accountNumber): void
    {
        $this->carrierAccount = $accountNumber;
    }
    public function setMarkup(float $markup): void
    {
        $this->markup = $markup;
    }
    public function getMarkup(): float
    {
        return $this->markup;
    }
    public function getName(): string
    {
        return $this->accountName ?? 'fedex';
    }
    public function getCarrierName(): string
    {
        return 'fedex';
    }
    public function setAccount(string $accountName)
    {
        $tokenModel = config('shipping.database.models.token');
        $applicationModel = config('shipping.database.models.application');
        $this->accountName = $accountName;
        $this->token = $tokenModel::where('accountname', $accountName)
            ->whereHas('application', function ($query) {
                $query->where('type', 'fedex');
            })
            ->first();

        if (!$this->token) {
            throw new \Exception("FedEx account not found: {$accountName}");
        }

        $application = $this->token->application;
        $this->carrierAccount = $this->token->accountnumber;
        if (!$application) {
            throw new \Exception("FedEx application not found for account: {$accountName}");
        }

        $this->url = str_contains(strtolower($application->application_name), 'sandbox') ? $this->test_url : $this->live_url;

        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => $this->getHeaders(),
        ]);

        // 初始化地址格式化器
        $this->addressFormatter = new AddressFormatter(
            $this,
            '/address/v1/addresses/resolve',
            $this->internationalServices,
            'FedEx'
        );

        $this->shipmentPayloads = new ShipmentPayloads(
            $this->addressFormatter
        );
        $this->ratePayloads = new RatePayloads(
            $this->addressFormatter
        );
        $this->tagPayloads = new TagPayloads(
            $this->addressFormatter
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
            // 'X-locale' => 'en_US',
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

            if (!$this->client) {
                $this->client = new Client([
                    'base_uri' => $this->url,
                    'timeout' => 30,
                ]);
            }

            $response = $this->client->post('/oauth/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $application->application_id,
                    'client_secret' => $application->shared_secret,
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
            throw new \Exception('Failed to get FedEx access token: ' . $e->getMessage());
        }
    }
    private function validateToken(): void
    {
        if (!$this->token) {
            throw new \Exception('FedEx account not set');
        }
    }
    public function createLabel(array $data): array
    {
        $this->validateToken();
        $payload = $this->shipmentPayloads->build($data);
        $result = $this->sendApiRequest('post', '/ship/v1/shipments', $payload);
        $this->saveLabelInfo($data, $result);
        return $result;
    }
    public function createReturnLabel(array $data): array
    {
        $this->validateToken();
        $payload = $this->shipmentPayloads->build($data, true);
        $result = $this->sendApiRequest('post', '/ship/v1/shipments', $payload);
        $this->saveLabelInfo($data, $result);
        return $result;
    }
    public function cancelLabel(string $trackingNumber): bool
    {
        $this->cancelPayloads = new CancelPayloads();
        $this->validateToken();
        $payload = $this->cancelPayloads->build($this->carrierAccount, $trackingNumber);
        $result = $this->sendApiRequest('put', '/ship/v1/shipments/cancel', $payload);
        return $result['output']['cancelledShipment'];
    }
    public function createTag($data): array
    {
        $this->validateToken();
        $payload = $this->tagPayloads->build($data);
        $result = $this->sendApiRequest('post', '/ship/v1/shipments/tag', $payload);
        return $result;
    }
    public function getRates(array $data): array
    {
        $this->validateToken();
        $payload = $this->ratePayloads->build($data);
        return $this->sendApiRequest('post', '/rate/v1/rates/quotes', $payload);
    }
    private function sendApiRequest(string $method, string $endpoint, array $payload): array
    {
        try {
            switch ($method) {
                case 'post':
                    $response = $this->client->post($endpoint, ['json' => $payload]);
                    break;
                case 'put':
                    $response = $this->client->put($endpoint, ['json' => $payload]);
                    break;
            }
            $result = json_decode($response->getBody()->getContents(), true);
            return $result;
        } catch (GuzzleException $e) {
            $body = $e->getResponse()->getBody();
            $errorResponse = json_decode($body, true);
            dd($errorResponse);
            throw new \Exception('Failed to get FedEx rates: ' . $e->getMessage());
        }
        return false;
    }
    public function validateAddress(array $address): array
    {
        if (!$this->token) {
            throw new \Exception('UPS account not set');
        }

        $response = $this->client->post('/address/v1/addresses/resolve', [
            'json' => [
                'addressesToValidate' => [$address],
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        // 检查地址验证结果
        if (!isset($result['output']['resolvedAddresses'][0])) {
            throw new \Exception('Address validation failed');
        }

        // 如果地址有效，使用验证后的地址
        $resolvedAddress = $result['output']['resolvedAddresses'][0];
        $classification = $resolvedAddress['classification'] ?? '';

        // 检查是否是住宅地址
        $address['address']['isResidential'] = $classification === 'RESIDENTIAL';

        //$this->isResidential = $address['address']['isResidential'];

        if ($classification == 'UNKNOWN') {
            //throw new \Exception('Address validation returned UNKNOWN classification');
        }
        return $address;
    }
    public function trackShipment(string $trackingNumber): array
    {
        if (!$this->token) {
            throw new \Exception('FedEx account not set');
        }

        $response = $this->client->post('/track/v1/trackingnumbers', [
            'json' => [
                'includeDetailedScans' => true,
                'trackingInfo' => [
                    [
                        'trackingNumber' => $trackingNumber,
                    ],
                ],
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
    private function saveLabelInfo(array $data, array $response): void
    {
        // 从响应中提取跟踪号码
        $trackingNumber = $response['output']['transactionShipments'][0]['pieceResponses'][0]['trackingNumber'] ?? null;

        if (!$trackingNumber) {
            return;
        }

        // 从响应中提取标签URL
        $labelUrl = $response['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['url'] ?? null;

        // 从响应中提取运费
        $shippingCost = $response['output']['transactionShipments'][0]['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['totalNetCharge'] ?? null;

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
            'account_number' => $data['account_number'],
            'tracking_number' => $trackingNumber,
            'invoice_number' => $data['invoice_number'] ?? null,
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