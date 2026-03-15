<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorefrontController extends Controller
{
    public function index()
    {
        // For now, returning all products with their variants
        $products = Product::with('variants')->get();
        return response()->json($products);
    }

    public function trackCart(Request $request)
    {
        $request->validate([
            'session_uuid' => 'required|string',
            'cart_items' => 'required|array',
        ]);

        Cache::put('cart_' . $request->session_uuid, $request->cart_items, now()->addDays(2));

        return response()->json(['status' => 'success', 'message' => 'Cart tracked successfully']);
    }
}
