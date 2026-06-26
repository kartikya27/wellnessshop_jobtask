<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.marketing');
});

Route::controller(DashboardController::class)->group(function (): void {
    Route::get('/marketing', 'marketing')->name('dashboard.marketing');
    Route::get('/operations', 'operations')->name('dashboard.operations');
    Route::get('/orders', 'orders')->name('orders.index');
    Route::patch('/orders/{order}/status', 'updateOrderStatus')->name('orders.status.update');
    Route::get('/campaigns', 'campaigns')->name('campaigns.index');
    Route::get('/shipments', 'shipments')->name('shipments.index');
    Route::get('/rto-reasons', 'rtoReasonsPage')->name('rto-reasons.index');
    Route::post('/rto-reasons', 'storeRtoReason')->name('rto-reasons.store');
    Route::patch('/rto-reasons/{reason}', 'updateRtoReason')->name('rto-reasons.update');
    Route::delete('/rto-reasons/{reason}', 'deleteRtoReason')->name('rto-reasons.destroy');
    Route::get('/lost-cases', 'lostCasesPage')->name('lost-cases.index');
    Route::get('/assistant', 'assistant')->name('assistant.index');
});

Route::post('/assistant/query', AiAssistantController::class)->name('assistant.query');
Route::get('/assistant/sessions', [AiAssistantController::class, 'sessions'])->name('assistant.sessions');
Route::post('/assistant/sessions', [AiAssistantController::class, 'createSession'])->name('assistant.sessions.create');
Route::get('/assistant/sessions/{sessionKey}/messages', [AiAssistantController::class, 'messages'])->name('assistant.messages');
