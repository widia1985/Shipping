<?php

namespace Widia\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ShippingLabel extends Model
{
    protected $table;

    protected $fillable = [
        'carrier',
        'account_name',
        'account_number',
        'tracking_number',
        'invoice_number',
        'market_order_id',
        'customer_po_number',
        'box_id',
        'service_type',
        'shipping_cost',
        'shipping_cost_base',
        'label_url',
        'image_format',
        'label_data',
        'shipper_info',
        'recipient_info',
        'package_info',
        'created_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'status',
        'formdata',
        'shipmentfees',
        'packagefees',
        'package_ahs',
        'rma_number',
        'is_return',
        'invoices'
    ];

    protected $casts = [
        'shipper_info' => 'array',
        'recipient_info' => 'array',
        'package_info' => 'array',
        'label_data' => 'array',
        'formdata' => 'array',
        'created_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'shipmentfees' => 'array',
        'packagefees' => 'array',
        'invoices' => 'array'
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
        /*$this->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason,
        ]);*/
        $this->void($reason,$cancelledBy);
    }

    public function void($reason = null, $cancelledBy = 0){
        if($reason == null) $reason = 'Voided by user';
        if($cancelledBy == 0 && \admin::user()){
            $cancelledBy = \admin::user()->id;
        }
        
        if ($this->isCancelled()) {
            throw new \Exception('Label has already been cancelled.');
        }

        try{
            $success = Shipping::cancelLabelByLabelModel($this);
            if($success){
                if(\admin::user()){
                    $this->cancel($reason, $cancelledBy); // Assuming you have an admin user context
                }
            }
            else {
                throw new \Exception('Failed to cancel label with the shipping carrier.');
            }
        }
        catch(\Exception $e) {
            throw new \Exception('Failed to void shipment: ' . $e->getMessage());
        }
    }

    public function isCancelled()
    {
        return !is_null($this->cancelled_at);
    }
} 
