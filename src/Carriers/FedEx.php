<?php

namespace Widia\Shipping\Carriers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Widia\Shipping\Address\AddressFormatter;
use Widia\Shipping\Models\ShippingLabel;
use Widia\Shipping\Payloads\FedEx\CancelPayloads;
use Widia\Shipping\Payloads\FedEx\RatePayloads;
use Widia\Shipping\Payloads\FedEx\ShipmentPayloads;
use Widia\Shipping\Payloads\FedEx\TagPayloads;
use Widia\Shipping\Services\LabelService;
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
        $this->validateToken();

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
        $data['is_return'] = 1;
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

        try {
            $result = $this->sendApiRequest('put', '/ship/v1/shipments/cancel', $payload);
            if (isset($result['output']['cancelledShipment']) && $result['output']['cancelledShipment'] === true) {
                return $result['output']['cancelledShipment'];
            }
            throw new \Exception('Cancel Shipment FedEx API Request Failed: ' . $result['output']['message']);
        } catch (\Exception $e) {
            throw $e;
        }
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
                default:
                    throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
            }
            $result = json_decode($response->getBody()->getContents(), true);
            return $result;
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            if ($response) {
                $body = (string) $response->getBody();
                $decoded = json_decode($body, true);

                $message = $decoded['errors'][0]['message'] ?? $e->getMessage();
                $code = $response->getStatusCode();
                throw new \Exception("FedEx API Error ({$code}): {$message}", $code);
            }
            throw new \Exception('FedEx API Request Failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
    public function validateAddress(array $address): array
    {
        $this->validateToken();
        $result = $this->sendApiRequest('post', '/address/v1/addresses/resolve', [
            'addressesToValidate' => [$address],
        ]);
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


        //預留一個 unknown 結果，未來要呼叫其他工具來解決
        if ($classification == 'UNKNOWN') {
            //throw new \Exception('Address validation returned UNKNOWN classification');
        }
        return $address;
    }
    public function trackShipment(string $trackingNumber): array
    {
        $this->validateToken();
        $result = $this->sendApiRequest('post', '/track/v1/trackingnumbers', [
            'includeDetailedScans' => true,
            'trackingInfo' => [
                [
                    'trackingNumberInfo' => [
                        "trackingNumber" => $trackingNumber
                    ],
                ],
            ],
        ]);
        if (isset($result['output']['completeTrackResults']['trackResults'][0]['error'])) {
            throw new \Exception('Cancel Shipment FedEx API Request Failed: ' . $result['output']['completeTrackResults']['trackResults'][0]['error']['message']);
        }

        return $result;
    }
    private function saveLabelInfo(array $data, array $response): array
    {
        $labelModel = config('shipping.database.models.shipping_label');

        $transactionShipment = $response['output']['transactionShipments'][0] ?? [];
        if (empty($transactionShipment)) {
            return [];
        }

        $completedShipmentDetail = $transactionShipment['completedShipmentDetail'] ?? [];
        $shipmentRateDetail = $completedShipmentDetail['shipmentRating']['shipmentRateDetails'][0] ?? [];

        // 整票的運費
        $shippingCost = $shipmentRateDetail['totalNetCharge'] ?? 0;

        // 拆解整票費用 (shipmentfees)
        $shipmentfees = [];
        if (!empty($shipmentRateDetail['surcharges'])) {
            foreach ($shipmentRateDetail['surcharges'] as $surcharge) {
                $code = $surcharge['surchargeType'] ?? '';
                $amount = $surcharge['amount'] ?? 0;
                $shipmentfees[$code] = $amount;
            }
        }

        // 準備 packageInfo
        $packageInfo = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            $packageInfo = $data['packages'];
        } else {
            $packageInfo[] = [
                'weight' => $data['weight'] ?? null,
                'length' => $data['length'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'box_id' => $data['box_id'] ?? null,
            ];
        }

        $rs = [];
        $completedPackageDetails = $completedShipmentDetail['completedPackageDetails'] ?? [];
        foreach ($completedPackageDetails as $i => $packageDetail) {
            $trackingNumber = $packageDetail['trackingIds'][0]['trackingNumber'] ?? '';

            $packageDoc = $transactionShipment['pieceResponses'][$i]['packageDocuments'][0] ?? [];
            $contentType = $packageDoc['contentType'] ?? '';
            $labelUrlFromResponse = $packageDoc['url'] ?? null;
            $encodedLabel = $packageDoc['encodedLabel'] ?? null;
            $imageFormat = $packageDoc['docType'] ?? 'PDF';
            if (!empty($labelUrlFromResponse)) {
                $labelUrl = $labelUrlFromResponse;
                $imageFormat = 'url';
            } elseif ($contentType === 'LABEL' && !empty($encodedLabel)) {
                $labelUrl = LabelService::saveLabel($imageFormat, $encodedLabel, $trackingNumber);
            } else {
                // 非預期回應
                $labelUrl = 'missing label data';
            }

            if ($labelUrlFromResponse != '') {
                $labelUrl = $labelUrlFromResponse;
                $imageFormat = 'url';
            }
            // 包裹費用
            $packagefees = [];
            $package_ahs = 0;
            $packageRateDetail = $packageDetail['packageRating']['packageRateDetails'][0] ?? [];
            if (!empty($packageRateDetail['surcharges'])) {
                foreach ($packageRateDetail['surcharges'] as $surcharge) {
                    $code = $surcharge['surchargeType'] ?? '';
                    $amount = $surcharge['amount'] ?? 0;
                    $packagefees[$code] = $amount;

                    if ($code === 'ADDITIONAL_HANDLING') {
                        $package_ahs = $amount;
                    }
                }
            }


            // 如果有多個包裹，第二個以後不重複紀錄整票運費
            $packageShippingCost = $i === 0 ? $shippingCost : 0;
            $packageShipmentFees = $i === 0 ? $shipmentfees : [];

            $markup = 1.0 + $this->markup;

            $rs[] = [
                'carrier' => 'fedex',
                'account_name' => $this->accountName,
                'account_number' => $data['account_number'] ?? $this->carrierAccount,
                'tracking_number' => $trackingNumber,
                'invoice_number' => $data['invoice_number'] ?? null,
                'market_order_id' => $data['market_order_id'] ?? null,
                'customer_po_number' => $data['customer_po_number'] ?? null,
                'box_id' => $packageInfo[$i]['box_id'] ?? 0,
                'service_type' => $transactionShipment['serviceType'] ?? $data['service_type'] ?? null,
                'shipping_cost' => $packageShippingCost * $markup,
                'shipping_cost_base' => $packageShippingCost,
                'label_url' => $labelUrl,
                'image_format' => $imageFormat,
                'label_data' => $response, //$response,
                'shipper_info' => [
                    'name' => $data['shipper']['contact']['personName'] ?? null,
                    'address' => $data['shipper']['address'] ?? null,
                    'phone' => $data['shipper']['contact']['phoneNumber'] ?? null,
                    'email' => $data['shipper']['contact']['emailAddress'] ?? null,
                ],
                'recipient_info' => [
                    'name' => $data['recipient']['contact']['personName'] ?? null,
                    'address' => $data['recipient']['address'] ?? null,
                    'phone' => $data['recipient']['contact']['phoneNumber'] ?? null,
                    'email' => $data['recipient']['contact']['emailAddress'] ?? null,
                ],
                'package_info' => $packageInfo[$i] ?? [],
                'status' => 'ACTIVE',
                'shipmentfees' => $packageShipmentFees,
                'packagefees' => $packagefees,
                'package_ahs' => $package_ahs,
                'is_return' => $data['is_return'] ?? '0',
                'rma_number' => $data['rma_number'] ?? '',
            ];

            $labelModel::create($rs[$i]);
        }

        return $rs;
    }
    /**
     * Fedex: Create Tag API & Cancel Tag
     * 已經串接完成，但參數限制可能導致功能異常
     * 暫時不用。
     */
    public function createTag($data): array
    {
        $this->validateToken();
        $payload = $this->tagPayloads->build($data);
        $result = $this->sendApiRequest('post', '/ship/v1/shipments/tag', $payload);
        return $result;
    }
    public function cancelTag($data): bool
    {
        $this->validateToken();
        $payload = $this->tagPayloads->cancel($data);
        $result = $this->sendApiRequest('put', '/ship/v1/shipments/tag/cancel', $payload);
        return $result['output']['cancelledTag'];
    }
}