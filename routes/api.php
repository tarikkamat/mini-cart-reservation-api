<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('api.health');

Route::get('products', [ProductController::class, 'index'])->name('api.products.index');

Route::post('cart/quote', [CartController::class, 'quote'])->name('api.cart.quote');

Route::post('reservations', [ReservationController::class, 'store'])
    ->middleware('idempotency')
    ->name('api.reservations.store');
Route::post('reservations/{id}/release', [ReservationController::class, 'release'])->name('api.reservations.release');
