<?php

namespace Widia\Shipping\Controllers;

use Widia\Shipping\Shipping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Widia\Shipping\Models\ShippingLabel;

class ShippingController
{
    protected $shipping;

    public function __construct()
    {
        $this->shipping = new Shipping();
    }

    /**
     * 获取运费报价
     */
    public function getRates(Request $request): JsonResponse
    {
        try {
            // 验证用户是否已认证
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'carrier' => 'required|string|in:ups,fedex',
                'account_number' => 'required|string',
                'shipper' => 'required|array',
                'shipper.contact.personName' => 'required|string',
                'shipper.contact.phoneNumber' => 'required|string',
                'shipper.contact.emailAddress' => 'required|email',
                'shipper.address.streetLines' => 'required|array',
                'shipper.address.city' => 'required|string',
                'shipper.address.stateOrProvinceCode' => 'required|string',
                'shipper.address.postalCode' => 'required|string',
                'shipper.address.countryCode' => 'required|string',
                'recipient' => 'required|array',
                'recipient.contact.personName' => 'required|string',
                'recipient.contact.phoneNumber' => 'required|string',
                'recipient.contact.emailAddress' => 'required|email',
                'recipient.address.streetLines' => 'required|array',
                'recipient.address.city' => 'required|string',
                'recipient.address.stateOrProvinceCode' => 'required|string',
                'recipient.address.postalCode' => 'required|string',
                'recipient.address.countryCode' => 'required|string',
                'package' => 'required|array',
                'package.weight' => 'required|numeric',
                'package.length' => 'required|numeric',
                'package.width' => 'required|numeric',
                'package.height' => 'required|numeric',
                'service_type' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $rates = $this->shipping->setCarrier($request->carrier)
                ->setAccount($request->account_number)
                ->getRates($request->all());

            return response()->json([
                'success' => true,
                'data' => $rates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 创建运单
     */
    public function createLabel(Request $request): JsonResponse
    {
        try {
            // 验证用户是否已认证
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'carrier' => 'required|string|in:ups,fedex',
                'account_number' => 'required|string',
                'shipper' => 'required|array',
                'shipper.contact.personName' => 'required|string',
                'shipper.contact.phoneNumber' => 'required|string',
                'shipper.contact.emailAddress' => 'required|email',
                'shipper.address.streetLines' => 'required|array',
                'shipper.address.city' => 'required|string',
                'shipper.address.stateOrProvinceCode' => 'required|string',
                'shipper.address.postalCode' => 'required|string',
                'shipper.address.countryCode' => 'required|string',
                'recipient' => 'required|array',
                'recipient.contact.personName' => 'required|string',
                'recipient.contact.phoneNumber' => 'required|string',
                'recipient.contact.emailAddress' => 'required|email',
                'recipient.address.streetLines' => 'required|array',
                'recipient.address.city' => 'required|string',
                'recipient.address.stateOrProvinceCode' => 'required|string',
                'recipient.address.postalCode' => 'required|string',
                'recipient.address.countryCode' => 'required|string',
                'package' => 'required|array',
                'package.weight' => 'required|numeric',
                'package.length' => 'required|numeric',
                'package.width' => 'required|numeric',
                'package.height' => 'required|numeric',
                'service_type' => 'required|string',
                'signature_required' => 'boolean',
                'signature_type' => 'string|in:DIRECT,INDIRECT,ADULT',
                'ship_notify' => 'boolean',
                'ship_notify_email' => 'email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $label = $this->shipping->setCarrier($request->carrier)
                ->setAccount($request->account_number)
                ->createLabel($request->all());

            return response()->json([
                'success' => true,
                'data' => $label
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 查询包裹状态
     */
    public function trackShipment(Request $request): JsonResponse
    {
        try {
            // 验证用户是否已认证
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'carrier' => 'required|string|in:ups,fedex',
                'account_number' => 'required|string',
                'tracking_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tracking = $this->shipping->setCarrier($request->carrier)
                ->setAccount($request->account_number)
                ->trackShipment($request->tracking_number);

            return response()->json([
                'success' => true,
                'data' => $tracking
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 比较多个承运商的运费
     */
    public function compareRates(Request $request): JsonResponse
    {
        try {
            // 验证用户是否已认证
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'carriers' => 'required|array',
                'carriers.*.carrier' => 'required|string|in:ups,fedex',
                'carriers.*.account_number' => 'required|string',
                'shipper' => 'required|array',
                'shipper.contact.personName' => 'required|string',
                'shipper.contact.phoneNumber' => 'required|string',
                'shipper.contact.emailAddress' => 'required|email',
                'shipper.address.streetLines' => 'required|array',
                'shipper.address.city' => 'required|string',
                'shipper.address.stateOrProvinceCode' => 'required|string',
                'shipper.address.postalCode' => 'required|string',
                'shipper.address.countryCode' => 'required|string',
                'recipient' => 'required|array',
                'recipient.contact.personName' => 'required|string',
                'recipient.contact.phoneNumber' => 'required|string',
                'recipient.contact.emailAddress' => 'required|email',
                'recipient.address.streetLines' => 'required|array',
                'recipient.address.city' => 'required|string',
                'recipient.address.stateOrProvinceCode' => 'required|string',
                'recipient.address.postalCode' => 'required|string',
                'recipient.address.countryCode' => 'required|string',
                'package' => 'required|array',
                'package.weight' => 'required|numeric',
                'package.length' => 'required|numeric',
                'package.width' => 'required|numeric',
                'package.height' => 'required|numeric',
                'service_type' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $comparison = [];
            foreach ($request->carriers as $carrier) {
                $rates = $this->shipping->setCarrier($carrier['carrier'])
                    ->setAccount($carrier['account_number'])
                    ->getRates($request->all());
                $comparison[$carrier['carrier']] = $rates;
            }

            return response()->json([
                'success' => true,
                'data' => $comparison
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取最便宜的运费
     */
    public function getCheapestRate(Request $request): JsonResponse
    {
        try {
            // 验证用户是否已认证
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'carriers' => 'required|array',
                'carriers.*.carrier' => 'required|string|in:ups,fedex',
                'carriers.*.account_number' => 'required|string',
                'shipper' => 'required|array',
                'shipper.contact.personName' => 'required|string',
                'shipper.contact.phoneNumber' => 'required|string',
                'shipper.contact.emailAddress' => 'required|email',
                'shipper.address.streetLines' => 'required|array',
                'shipper.address.city' => 'required|string',
                'shipper.address.stateOrProvinceCode' => 'required|string',
                'shipper.address.postalCode' => 'required|string',
                'shipper.address.countryCode' => 'required|string',
                'recipient' => 'required|array',
                'recipient.contact.personName' => 'required|string',
                'recipient.contact.phoneNumber' => 'required|string',
                'recipient.contact.emailAddress' => 'required|email',
                'recipient.address.streetLines' => 'required|array',
                'recipient.address.city' => 'required|string',
                'recipient.address.stateOrProvinceCode' => 'required|string',
                'recipient.address.postalCode' => 'required|string',
                'recipient.address.countryCode' => 'required|string',
                'package' => 'required|array',
                'package.weight' => 'required|numeric',
                'package.length' => 'required|numeric',
                'package.width' => 'required|numeric',
                'package.height' => 'required|numeric',
                'service_type' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cheapestRate = null;
            $cheapestCarrier = null;

            foreach ($request->carriers as $carrier) {
                $rates = $this->shipping->setCarrier($carrier['carrier'])
                    ->setAccount($carrier['account_number'])
                    ->getRates($request->all());

                if (empty($cheapestRate) || $rates['totalCharge'] < $cheapestRate['totalCharge']) {
                    $cheapestRate = $rates;
                    $cheapestCarrier = $carrier['carrier'];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'carrier' => $cheapestCarrier,
                    'rate' => $cheapestRate
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 创建退货标签
     */
    public function createReturnLabel(Request $request): JsonResponse
    {
        try {
            // 验证用户是否已认证
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'carrier' => 'required|string|in:ups,fedex',
                'account_number' => 'required|string',
                'original_tracking_number' => 'required|string',
                'return_reason' => 'required|string',
                'return_instructions' => 'string',
                'shipper' => 'required|array',
                'shipper.contact.personName' => 'required|string',
                'shipper.contact.phoneNumber' => 'required|string',
                'shipper.contact.emailAddress' => 'required|email',
                'shipper.address.streetLines' => 'required|array',
                'shipper.address.city' => 'required|string',
                'shipper.address.stateOrProvinceCode' => 'required|string',
                'shipper.address.postalCode' => 'required|string',
                'shipper.address.countryCode' => 'required|string',
                'return_address' => 'array', // 可选，如果不提供则使用默认退货地址
                'return_address.contact.personName' => 'string',
                'return_address.contact.phoneNumber' => 'string',
                'return_address.contact.emailAddress' => 'email',
                'return_address.address.streetLines' => 'array',
                'return_address.address.city' => 'string',
                'return_address.address.stateOrProvinceCode' => 'string',
                'return_address.address.postalCode' => 'string',
                'return_address.address.countryCode' => 'string',
                'package' => 'required|array',
                'package.weight' => 'required|numeric',
                'package.length' => 'required|numeric',
                'package.width' => 'required|numeric',
                'package.height' => 'required|numeric',
                'service_type' => 'required|string',
                'signature_required' => 'boolean',
                'signature_type' => 'string|in:DIRECT,INDIRECT,ADULT',
                'rma_number' => 'string',
                'return_authorization_number' => 'string',
                'ship_notify' => 'boolean',
                'ship_notify_email' => 'email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $returnLabel = $this->shipping->setCarrier($request->carrier)
                ->setAccount($request->account_number)
                ->createReturnLabel($request->all());

            return response()->json([
                'success' => true,
                'data' => $returnLabel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取运单列表
     */
    public function getLabels(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'carrier' => 'string|in:ups,fedex',
                'status' => 'string|in:ACTIVE,CANCELLED,COMPLETED',
                'date_from' => 'date',
                'date_to' => 'date|after_or_equal:date_from',
                'tracking_number' => 'string',
                'account_number' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = ShippingLabel::query();

            // 应用过滤条件
            if ($request->has('carrier')) {
                $query->where('carrier', $request->carrier);
            }
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            if ($request->has('tracking_number')) {
                $query->where('tracking_number', 'like', '%' . $request->tracking_number . '%');
            }
            if ($request->has('account_number')) {
                $query->where('account_number', $request->account_number);
            }

            // 分页
            $perPage = $request->input('per_page', 15);
            $labels = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $labels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取运单详情
     */
    public function getLabel(Request $request, string $id): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $label = ShippingLabel::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $label
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 取消运单
     */
    public function cancelLabel(Request $request, string $id): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $label = ShippingLabel::findOrFail($id);

            // 检查运单状态
            if ($label->status !== 'ACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active labels can be cancelled'
                ], 400);
            }

            // 调用承运商API取消运单
            $result = $this->shipping->setCarrier($label->carrier)
                ->setAccount($label->account_number)
                ->cancelLabel($label->tracking_number);

            // 更新运单状态
            $label->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancellation_data' => $result
            ]);

            return response()->json([
                'success' => true,
                'data' => $label
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 重新打印运单标签
     */
    public function reprintLabel(Request $request, string $id): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $label = ShippingLabel::findOrFail($id);

            // 检查运单状态
            if ($label->status !== 'ACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active labels can be reprinted'
                ], 400);
            }

            // 调用承运商API重新打印标签
            $result = $this->shipping->setCarrier($label->carrier)
                ->setAccount($label->account_number)
                ->reprintLabel($label->tracking_number);

            // 更新标签URL
            $label->update([
                'label_url' => $result['label_url'] ?? $label->label_url,
                'label_data' => array_merge($label->label_data ?? [], $result)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'label' => $label,
                    'label_url' => $result['label_url'] ?? $label->label_url
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 重新打印商业发票
     */
    public function reprintInvoice(Request $request, string $id): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $label = ShippingLabel::findOrFail($id);

            // 检查是否是国际运单
            if (!$this->isInternationalShipment($label->shipper_info['address']['countryCode'], $label->recipient_info['address']['countryCode'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commercial invoice is only available for international shipments'
                ], 400);
            }

            // 调用承运商API重新打印商业发票
            $result = $this->shipping->setCarrier($label->carrier)
                ->setAccount($label->account_number)
                ->reprintInvoice($label->tracking_number);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_url' => $result['invoice_url']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 批量取消运单
     */
    public function bulkCancelLabels(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'label_ids' => 'required|array',
                'label_ids.*' => 'required|exists:shipping_labels,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            foreach ($request->label_ids as $labelId) {
                try {
                    $label = ShippingLabel::findOrFail($labelId);
                    
                    if ($label->status !== 'ACTIVE') {
                        $results['failed'][] = [
                            'id' => $labelId,
                            'reason' => 'Label is not active'
                        ];
                        continue;
                    }

                    // 调用承运商API取消运单
                    $result = $this->shipping->setCarrier($label->carrier)
                        ->setAccount($label->account_number)
                        ->cancelLabel($label->tracking_number);

                    // 更新运单状态
                    $label->update([
                        'status' => 'CANCELLED',
                        'cancelled_at' => now(),
                        'cancellation_data' => $result
                    ]);

                    $results['success'][] = $labelId;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'id' => $labelId,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 批量重新打印运单标签
     */
    public function bulkReprintLabels(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'label_ids' => 'required|array',
                'label_ids.*' => 'required|exists:shipping_labels,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            foreach ($request->label_ids as $labelId) {
                try {
                    $label = ShippingLabel::findOrFail($labelId);
                    
                    if ($label->status !== 'ACTIVE') {
                        $results['failed'][] = [
                            'id' => $labelId,
                            'reason' => 'Label is not active'
                        ];
                        continue;
                    }

                    // 调用承运商API重新打印标签
                    $result = $this->shipping->setCarrier($label->carrier)
                        ->setAccount($label->account_number)
                        ->reprintLabel($label->tracking_number);

                    // 更新标签URL
                    $label->update([
                        'label_url' => $result['label_url'] ?? $label->label_url,
                        'label_data' => array_merge($label->label_data ?? [], $result)
                    ]);

                    $results['success'][] = [
                        'id' => $labelId,
                        'label_url' => $result['label_url'] ?? $label->label_url
                    ];
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'id' => $labelId,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    private function isInternationalShipment(string $shipperCountry, string $recipientCountry): bool
    {
        return $shipperCountry !== $recipientCountry;
    }
} 