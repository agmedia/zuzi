<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::post('/order/send/wolt', [\App\Http\Controllers\Back\OrderController::class, 'api_send_wolt'])
    ->name('api.order.send.wolt');
