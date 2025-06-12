<?php

namespace Widia\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ShippingLabel extends Model
{
    protected $table;

    protected $fillable = [
        'carrier',
        'account_number',
        'tracking_number',
        'invoice_number',
        'service_type',
        'shipping_cost',
        'label_url',
        'label_data',
        'shipper_info',
        'recipient_info',
        'package_info',
        'created_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'status'
    ];

    protected $casts = [
        'shipper_info' => 'array',
        'recipient_info' => 'array',
        'package_info' => 'array',
        'label_data' => 'array',
        'created_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('shipping.database.tables.shipping_labels');
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