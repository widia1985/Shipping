<?php

use Illuminate\Support\Facades\Route;
use Widia\Shipping\Controllers\FedExTestController;

Route::prefix('shipping')->group(function () {
    Route::get('/index', [FedExTestController::class, 'index']);
    Route::post('/createLabel', [FedExTestController::class, 'createLabel'])->name('shipping.fedex.create');
    Route::post('/createReturnLabel', [FedExTestController::class, 'createReturnLabel'])->name('shipping.fedex.createReturn');
});