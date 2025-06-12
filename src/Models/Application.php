<?php

namespace Widia\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'application';
    protected $primaryKey = 'id';

    protected $fillable = [
        'type',
        'application_id',
        'shared_secret',
        'application_name'
    ];

    public function tokens()
    {
        return $this->hasMany(Token::class, 'app_id');
    }

    public function getCarrierAttribute()
    {
        return strtolower($this->type);
    }

    public function getSandboxAttribute()
    {
        return strpos(strtolower($this->type), 'sandbox') !== false;
    }
} 