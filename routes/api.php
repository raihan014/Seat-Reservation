<?php

use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/events/{event}/reserve', [ReservationController::class, 'reserve']);

    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);

    Route::get('/events', function () {
    return \App\Models\Event::all();
})->middleware('auth:sanctum');

});