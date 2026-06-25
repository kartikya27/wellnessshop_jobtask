<?php

use App\Http\Controllers\Api\MarketingMetricsController;
use App\Http\Controllers\Api\OperationsMetricsController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketing')->group(function (): void {
    Route::get('overview', [MarketingMetricsController::class, 'overview']);
    Route::get('platform', [MarketingMetricsController::class, 'platform']);
    Route::get('campaigns', [MarketingMetricsController::class, 'campaigns']);
    Route::get('trends', [MarketingMetricsController::class, 'trends']);
});

Route::prefix('ops')->group(function (): void {
    Route::get('overview', [OperationsMetricsController::class, 'overview']);
    Route::get('couriers', [OperationsMetricsController::class, 'couriers']);
    Route::get('rto', [OperationsMetricsController::class, 'rto']);
    Route::get('lost-cases', [OperationsMetricsController::class, 'lostCases']);
    Route::get('trends', [OperationsMetricsController::class, 'trends']);
    Route::get('shipments', [OperationsMetricsController::class, 'shipments']);
    Route::get('orders', [OperationsMetricsController::class, 'orders']);
    Route::patch('orders/{order}', [OperationsMetricsController::class, 'updateOrder']);
    Route::get('rto-reasons', [OperationsMetricsController::class, 'rtoReasons']);
    Route::post('rto-reasons', [OperationsMetricsController::class, 'storeRtoReason']);
    Route::patch('rto-reasons/{reason}', [OperationsMetricsController::class, 'updateRtoReason']);
    Route::delete('rto-reasons/{reason}', [OperationsMetricsController::class, 'deleteRtoReason']);
});
