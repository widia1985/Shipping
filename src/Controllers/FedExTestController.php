<?php

namespace Widia\Shipping\Controllers;

use Widia\Shipping\Shipping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Widia\Shipping\Models\ShippingLabel;
use Illuminate\Routing\Controller;

class FedExTestController extends Controller
{
    protected $shipping;

    public function __construct()
    {
        $this->shipping = new Shipping();
        // $this->middleware('auth:api');
    }
    public function index()
    {
        return view("shipping::fedex.index");
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
        // try {
        $validator = Validator::make($request->all(), [
            'carrier' => 'required|string|in:ups,fedex,UPS,FEDEX,Fedex',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            //'shipper' => 'required|array',
            /*'shipper.contact.personName' => 'required|string',
            'shipper.contact.phoneNumber' => 'required|string',
            'shipper.contact.emailAddress' => 'required|email',
            'shipper.address.streetLines' => 'required|array',
            'shipper.address.city' => 'required|string',
            'shipper.address.stateOrProvinceCode' => 'required|string',
            'shipper.address.postalCode' => 'required|string',
            'shipper.address.countryCode' => 'required|string',*/
            'recipient' => 'required|array',
            'recipient.contact.personName' => 'required|string',
            'recipient.contact.phoneNumber' => 'required|string',
            'recipient.contact.emailAddress' => 'required|email',
            'recipient.address.streetLines' => 'required|array',
            'recipient.address.city' => 'required|string',
            'recipient.address.stateOrProvinceCode' => 'required|string',
            'recipient.address.postalCode' => 'required|string',
            'recipient.address.countryCode' => 'required|string',
            'packages' => 'required|array',
            'packages.*.weight' => 'required|numeric',
            'packages.*.length' => 'required|numeric',
            'packages.*.width' => 'required|numeric',
            'packages.*.height' => 'required|numeric',
            'service_type' => 'required|string',
            'signature_required' => 'boolean',
            'signature_type' => 'string|in:DIRECT,INDIRECT,ADULT,NO_SIGNATURE_REQUIRED',
            'ship_notify' => 'array',
            //'reference_number' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'carrier',
            'account_name',
            'account_number',
            'invoice_number',
            'invoice_date',
            'customer_po_number',
            'market_order_id',
            'shipper',
            'recipient',
            'labelResponseOptions',
            'service_type',
            'signature_required',
            'signature_type',
            'packages',
            'packaging_type',
            'items',
            'pickup_type',
        ]);

        $label = $this->shipping->setCarrier([$request->carrier => [$request->account_name => $request->account_number]])
            ->createLabel($data);

        return response()->json([
            'success' => true,
            'data' => $label
        ]);
        // } catch (\Exception $e) {
        //     if (method_exists($e, 'getResponse')) {
        //         $jsonStr = $e->getResponse()->getBody()->getContents();
        //         $data = json_decode($jsonStr, true);
        //         $message = $data['response']['errors'][0]['message'] ?? $e->getMessage();
        //     } else {
        //         $message = $e->getMessage();
        //     }
        //     return response()->json([
        //         'success' => false,
        //         'message' => $message //$e->getMessage()
        //     ], 400);
        // }
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
        // try {
        // 验证用户是否已认证
        // if (!Auth::check()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized'
        //     ], 401);
        // }

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
            'packages' => 'required|array',
            'packages.*.weight' => 'required|numeric',
            'packages.*.length' => 'required|numeric',
            'packages.*.width' => 'required|numeric',
            'packages.*.height' => 'required|numeric',
            'service_type' => 'required|string',
            'signature_required' => 'boolean',
            'signature_type' => 'string|in:DIRECT,INDIRECT,ADULT',
            'rma_number' => 'string',
            'return_authorization_number' => 'string',
            'ship_notify' => 'boolean',
            'ship_notify_email' => 'email',
            'expiration_time' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('recipient');

        $returnLabel = $this->shipping->setCarrier([$request->carrier => [$request->account_name => $request->account_number]])
            ->createReturnLabel($data);

        return response()->json([
            'success' => true,
            'data' => $returnLabel
        ]);
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => $e->getMessage()
        //     ], 400);
        // }
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
            $labelModel = config('shipping.database.models.shipping_label');
            $id = decrypt($id);
            $label = $labelModel::find($id);
            if (!$label || !$label->label_url) {
                throw new \Exception('Label not found or label URL is missing');
            }

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
     * 获取运单label供打印
     */
    public function getLabelByTrackingNumber(Request $request, string $trackingnumber): JsonResponse
    {
        try {
            $labelModel = config('shipping.database.models.shipping_label');
            $label = $labelModel::where('tracking_number', $trackingnumber)->first();
            if (!$label || !$label->label_url) {
                throw new \Exception('Label not found or label URL is missing');
            }

            return response()->json([
                'success' => true,
                'data' => $label->label_url
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
    public function voidLabel(Request $request, string $id): JsonResponse
    {
        try {
            $labelModel = config('shipping.database.models.shipping_label');
            try {
                $id = decrypt($id);
                $label = $labelModel::find($id);
            } catch (\Exception $e) {
                $label = $labelModel::where('tracking_number', $id)->first();
                if (!$label)
                    throw new \Exception('Invalid label ID');
            }

            if (!$label || !$label->label_url) {
                throw new \Exception('Label not found');
            }

            // 检查运单状态
            if ($label->status !== 'ACTIVE') {
                throw new \Exception('Only active labels can be cancelled');
            }

            $label->void();

            return response()->json([
                'success' => true,
                'message' => 'Label cancelled successfully',
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