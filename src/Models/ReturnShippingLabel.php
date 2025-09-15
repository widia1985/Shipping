<?php

namespace Widia\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ReturnShippingLabel extends Model
{
    protected $table;

    protected $fillable = [
        'carrier',
        'account_name',
        'account_number',
        'tracking_number',
        'original_tracking_number',
        'invoice_number',
        'market_order_id',
        'customer_po_number',
        'box_id',
        'service_type',
        'service_name',
        'ship_datestamp',
        'shipping_cost',
        'shipping_cost_base',
        'label_url',
        'image_format',
        'label_data',
        'shipper_info',
        'recipient_info',
        'package_info',
        'shipment_advisory_details',
        'completed_shipment_detail',
        'completed_package_details',
        'shipment_rating',
        'surcharges',
        'package_ahs',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'ship_datestamp' => 'date',
        'shipper_info' => 'array',
        'recipient_info' => 'array',
        'package_info' => 'array',
        'shipment_advisory_details' => 'array',
        'completed_shipment_detail' => 'array',
        'completed_package_details' => 'array',
        'shipment_rating' => 'array',
        'surcharges' => 'array',
        'label_data' => 'array',
        'shipping_cost' => 'float',
        'shipping_cost_base' => 'float',
        'package_ahs' => 'float',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('shipping.database.tables.return_shipping_labels');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'CANCELLED');
    }

    public function cancel($reason = null, $cancelledBy = null)
    {
        $this->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason,
        ]);
    }

    public function isCancelled()
    {
        return !is_null($this->cancelled_at);
    }
}