<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/checkout/{uuid}', [\App\Http\Controllers\Web\CheckoutController::class, 'show'])->name('checkout.show');