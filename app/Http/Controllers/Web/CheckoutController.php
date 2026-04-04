<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BnbPaymentService;

class CheckoutController extends Controller
{
    public function show(string $uuid, BnbPaymentService $bnbService)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        
        // Reconstruimos itemsList (formato actual con slug y qty)
        $itemsList = $order->items;
        
        if (is_string($itemsList)) {
            $itemsList = json_decode($itemsList, true) ?? [];
        }
        
        if (!is_array($itemsList)) {
            $itemsList = [];
        }

        // Extraemos solo los slugs
        $slugs = array_column($itemsList, 'slug');
        $slugs = array_unique(array_filter($slugs));

        $products = \App\Models\Product::whereIn('slug', $slugs)
                    ->get()
                    ->keyBy('slug');

        // Generar QR Dinámico del BNB Sandbox
        $qrImage = $bnbService->generateQR($order->uuid, (float) $order->total, "Compra DARKOSYNC");
        
        // Fallback si falla la API de BNB
        if (!$qrImage) {
            $qrUrl = urlencode(config('app.url') . "/checkout/{$order->uuid}");
            $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$qrUrl}";
        }

        return view('checkout', compact('order', 'products', 'qrImage', 'itemsList'));
    }
}
