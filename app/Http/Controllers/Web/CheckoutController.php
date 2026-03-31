<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BnbPaymentService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(string $uuid, BnbPaymentService $bnbService)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        
        // Reconstruimos los productos
        $itemsList = is_array($order->items) ? $order->items : json_decode($order->items, true) ?? [];
        
        $products = \App\Models\Product::whereIn('slug', $itemsList)->get();
        
        // Generar QR Dinámico del BNB Sandbox
        $qrImage = $bnbService->generateQR($order->uuid, (float) $order->total, "Compra DARKOSYNC");
        
        // Fallback placeholder si la API de BNB Sandbox falla
        if (!$qrImage) {
            $qrUrl = urlencode(config('app.url') . "/checkout/{$order->uuid}");
            $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$qrUrl}";
        }

        return view('checkout', compact('order', 'products', 'qrImage'));
    }
}
