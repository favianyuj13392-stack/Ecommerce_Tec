<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\BnbWebhookController;
use App\Http\Controllers\Api\WhatsAppWebhookController;

Route::get('/products', [StorefrontController::class, 'index']);
Route::post('/track/cart', [StorefrontController::class, 'trackCart']);
Route::post('/checkout', [CheckoutController::class, 'store']);

Route::post('/webhooks/bnb', [BnbWebhookController::class, 'handle']);

// WhatsApp Webhooks
Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);
