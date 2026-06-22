<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PelionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/shipping/wolt-drive/check-zone', [\App\Http\Controllers\Api\WoltDriveController::class, 'checkZone'])
    ->name('api.wolt_drive.check_zone');

Route::post('/shipping/boxnow/webhook', \App\Http\Controllers\Api\BoxNowWebhookController::class)
    ->name('api.shipping.boxnow.webhook');

Route::post('/order/send/wolt', [\App\Http\Controllers\Back\OrderController::class, 'api_send_wolt'])
    ->name('api.order.send.wolt');

Route::middleware('pelion.api')
    ->prefix('pelion/v1')
    ->name('api.pelion.')
    ->group(function () {
        Route::get('/orders', [PelionController::class, 'orders'])->name('orders.index');
        Route::get('/orders/{order}', [PelionController::class, 'order'])->name('orders.show');
        Route::post('/orders/{order}/status', [PelionController::class, 'updateOrderStatus'])->name('orders.status');
        Route::get('/articles', [PelionController::class, 'articles'])->name('articles.index');
        Route::get('/publishers', [PelionController::class, 'publishers'])->name('publishers.index');
    });
