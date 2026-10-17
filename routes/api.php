<?php

use App\Http\Controllers\Api\InventoryAdjustmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/inventories/{inventory}/adjustments', InventoryAdjustmentController::class)
    ->middleware('auth:sanctum');