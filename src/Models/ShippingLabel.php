<?php

namespace Widia\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ShippingLabel extends Model
{
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

    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function cancel($reason = null, $cancelledBy = null)
    {
        $this->update([
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => $reason,
            'cancelled_by' => $cancelledBy,
            'status' => 'CANCELLED'
        ]);
    }

    public function isCancelled()
    {
        return !is_null($this->cancelled_at);
    }
} 