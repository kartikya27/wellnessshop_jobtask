<?php

use App\Http\Controllers\AiAssistantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.marketing');
});

Route::get('/marketing', function () {
    return view('dashboard', [
        'department' => 'marketing',
        'title' => 'Marketing Dashboard',
        'subtitle' => 'Spend, revenue, ROAS, CAC, platform efficiency, and campaign performance.',
    ]);
})->name('dashboard.marketing');

Route::get('/operations', function () {
    return view('dashboard', [
        'department' => 'operations',
        'title' => 'Operations Dashboard',
        'subtitle' => 'Orders, delivery health, courier performance, RTO reasons, and lost cases.',
    ]);
})->name('dashboard.operations');

Route::get('/orders', function () {
    return view('orders');
})->name('orders.index');

Route::get('/campaigns', function () {
    return view('detail-pages', [
        'page' => 'campaigns',
        'title' => 'Campaign Details',
        'eyebrow' => 'Marketing',
        'subtitle' => 'Inspect campaign spend, ROAS, status, and platform mix for the selected period.',
    ]);
})->name('campaigns.index');

Route::get('/shipments', function () {
    return view('detail-pages', [
        'page' => 'shipments',
        'title' => 'Shipment Details',
        'eyebrow' => 'Operations',
        'subtitle' => 'Track courier, delivery dates, shipment status, and RTO reason labels.',
    ]);
})->name('shipments.index');

Route::get('/rto-reasons', function () {
    return view('detail-pages', [
        'page' => 'rto-reasons',
        'title' => 'RTO Reason Library',
        'eyebrow' => 'Operations',
        'subtitle' => 'Maintain controllable and courier-driven RTO reasons used in reporting.',
    ]);
})->name('rto-reasons.index');

Route::get('/lost-cases', function () {
    return view('detail-pages', [
        'page' => 'lost-cases',
        'title' => 'Lost Case Details',
        'eyebrow' => 'Operations',
        'subtitle' => 'Review shipment loss cases, claim filing, claim amount, and recovery.',
    ]);
})->name('lost-cases.index');

Route::get('/assistant', function () {
    return view('assistant');
})->name('assistant.index');

Route::post('/assistant/query', AiAssistantController::class)->name('assistant.query');
Route::get('/assistant/sessions', [AiAssistantController::class, 'sessions'])->name('assistant.sessions');
Route::post('/assistant/sessions', [AiAssistantController::class, 'createSession'])->name('assistant.sessions.create');
Route::get('/assistant/sessions/{sessionKey}/messages', [AiAssistantController::class, 'messages'])->name('assistant.messages');
