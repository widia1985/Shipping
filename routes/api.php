<?php

use Illuminate\Support\Facades\Route;
use Widia\Shipping\Controllers\ShippingController;

Route::prefix('shipping')->group(function () {
    // 获取运费报价
    Route::post('/rates', [ShippingController::class, 'getRates']);
    
    // 创建运单
    Route::post('/labels', [ShippingController::class, 'createLabel']);

    Route::get('/getLabel/{id}', [ShippingController::class, 'getLabel']);

    Route::get('/getLabelByTrackingNumber/{trackingnumber}', [ShippingController::class, 'getLabelByTrackingNumber']);
    
    Route::post('/voidlabel/{id}', [ShippingController::class, 'voidLabel']);
    
    // 查询包裹状态
    Route::get('/tracking/{tracking_number}', [ShippingController::class, 'trackShipment']);
    
    // 比较多个承运商的运费
    Route::post('/compare-rates', [ShippingController::class, 'compareRates']);
    
    // 获取最便宜的运费
    Route::post('/cheapest-rate', [ShippingController::class, 'getCheapestRate']);
    
    // 创建退货标签
    Route::post('/createReturnLabel', [ShippingController::class, 'createReturnLabel']);
}); 