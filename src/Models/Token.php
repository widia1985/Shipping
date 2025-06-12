<?php

namespace Widia\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Token extends Model
{
    protected $table = 'token';
    protected $primaryKey = 'token_id';

    protected $fillable = [
        'app_id',
        'access_token',
        'expires_time',
        'refresh_token',
        'accountname'
    ];

    protected $casts = [
        'expires_time' => 'datetime'
    ];

    public function application()
    {
        return $this->belongsTo(Application::class, 'app_id');
    }

    public function isValid(): bool
    {
        return $this->expires_time && $this->expires_time->isFuture();
    }
} 