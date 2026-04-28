<?php

use App\Http\Controllers\Api\RfidTapController;
use App\Http\Controllers\Api\Timekeeping\CardValidationController;
use Illuminate\Support\Facades\Route;

Route::middleware('timekeeping.api')->prefix('api')->group(function () {
    Route::get('/timekeeping/cards/{uid}', [CardValidationController::class, 'show']);
});

// RFID gate PC endpoints — Bearer token auth handled inside the controller.
// throttle:120,1 = 120 requests/minute per IP; blocks brute-force token scanning
// while staying far above any legitimate gate volume.
Route::prefix('rfid')->middleware('throttle:120,1')->group(function () {
    Route::post('tap',       [RfidTapController::class, 'tap']);
    Route::post('heartbeat', [RfidTapController::class, 'heartbeat']);
});
