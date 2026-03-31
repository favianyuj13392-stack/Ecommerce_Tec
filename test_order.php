<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lead = \App\Models\Lead::first() ?? \App\Models\Lead::create(['whatsapp_id' => '59112345678', 'name' => 'Tester Test']);
$products = \App\Models\Product::inRandomOrder()->take(2)->get();
$slugs = $products->pluck('slug')->toArray();
$total = $products->sum('precio');

$order = \App\Models\Order::create([
    'lead_id' => $lead->id,
    'items' => $slugs,
    'total' => $total,
    'status' => 'pending'
]);

echo "\n\n====> ABRE ESTE LINK PARA PROBAR EL CARRITO Y EL QR:\n";
echo "http://localhost:8000/checkout/" . $order->uuid . "\n\n";

