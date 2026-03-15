<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Models\Qr;
use App\Services\BnbDonationService;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function store(Request $request, BnbDonationService $bnbService)
    {
        $validated = $request->validate([
            'guest_data'           => 'required|array',
            'guest_data.name'      => 'required|string',
            'guest_data.email'     => 'required|email',
            'session_uuid'         => 'required|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.variant_id'   => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'payment_method'       => 'required|in:manual_qr,gateway_stripe,gateway_mercadopago,bnb_qr',
        ]);

        $productIds = collect($validated['items'])->pluck('product_id')->unique();
        $variantIds = collect($validated['items'])->pluck('variant_id')->filter()->unique();

        $order = null;
        $qrEntry = null;

        // --- TRANSACCIÓN 1: RESERVA DE INVENTARIO Y CREACIÓN DE ÓRDEN ---
        // Se aísla el commit a la base de datos de la llamada a la red (API BNB) para evitar bloqueos pesados.
        try {
            DB::transaction(function () use ($validated, $productIds, $variantIds, &$order, &$qrEntry) {
                // Atomic Locking (Evita Race Conditions)
                $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
                $variants = ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id');

                $total_amount = 0;

                foreach ($validated['items'] as $item) {
                    $prod = $products->get($item['product_id']);
                    if (! $prod) throw new \Exception("Producto no encontrado.");

                    $unitPrice = $prod->precio;
                    if (! empty($item['variant_id'])) {
                        $variant = $variants->get($item['variant_id']);
                        if (! $variant || $variant->stock < $item['quantity']) {
                            throw new \Exception("Stock insuficiente para variante.");
                        }
                        $variant->decrement('stock', $item['quantity']);
                        $unitPrice = $variant->price ?? $prod->precio;
                    } else {
                        if ($prod->has_variants) {
                            throw new \Exception("Producto requiere variante.");
                        } 
                        if ($prod->stock < $item['quantity']) throw new \Exception("Stock insuficiente.");
                        $prod->decrement('stock', $item['quantity']);
                    }

                    $total_amount += $unitPrice * $item['quantity'];
                }

                $order = Order::create([
                    'total_amount' => $total_amount,
                    'session_uuid' => $validated['session_uuid'],
                    'status'       => 'pending_payment',
                    'type'         => 'formal',
                    'payment_method' => $validated['payment_method'],
                    'guest_data'   => $validated['guest_data'],
                ]);

                foreach ($validated['items'] as $item) {
                    $prod = $products->get($item['product_id']);
                    $variant = !empty($item['variant_id']) ? $variants->get($item['variant_id']) : null;
                    $unitPrice = $variant ? ($variant->price ?? $prod->precio) : $prod->precio;

                    $order->items()->create([
                        'product_id'    => $prod->id,
                        'variant_id'    => $variant->id ?? null,
                        'quantity'      => $item['quantity'],
                        'unit_price'    => $unitPrice,
                        'snapshot_data' => [
                            'product' => $prod->only(['id','nombre','precio','slug']),
                            'variant' => $variant ? $variant->only(['id','sku','price']) : null,
                        ],
                    ]);
                }

                $qrEntry = $order->qrs()->create([
                    'code'            => 'pending',
                    'external_qr_id'  => 'pending_' . uniqid(),
                    'amount'          => $total_amount,
                    'status'          => 'new',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        // --- LLAMADA A SERVICIO EXTERNO (Fuera de DB Transaction) ---
        $gloss = 'Pago Orden #' . $order->id;
        $qrData = $bnbService->generateFixedQR($order->total_amount, $gloss, $order->session_uuid);

        if (!$qrData || !isset($qrData['qrId'])) {
            // Compensación: Rollback Manual si falla la API
            DB::transaction(function () use ($order, $qrEntry) {
                foreach ($order->items as $item) {
                    if ($item->variant) {
                        $item->variant->increment('stock', $item->quantity);
                    } elseif ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }
                $order->update(['status' => 'cancelled']);
                $qrEntry->update(['status' => 'expired', 'code' => 'failed', 'external_qr_id' => 'failed']);
            });

            return response()->json(['status' => 'error', 'message' => 'Fallo la generación del QR. Orden cancelada.'], 502);
        }

        // --- CONFIRMAR QR EN BASE DE DATOS ---
        $qrEntry->update([
            'code'            => $qrData['qrId'],
            'external_qr_id'  => $qrData['qrId'],
            'qr'              => $qrData['qr_image'] ?? null,
            'bnb_blob'        => json_encode($qrData),
            'expiration_date' => now()->addDay(),
        ]);

        Cache::forget('cart_' . $validated['session_uuid']);

        return response()->json([
            'status'     => 'success',
            'order_id'   => $order->id,
            'qr_image'   => $qrEntry->qr,
            'qr_id'      => $qrEntry->external_qr_id,
            'expiration' => $qrEntry->expiration_date->toDateTimeString(),
        ], 201);
    }
}
