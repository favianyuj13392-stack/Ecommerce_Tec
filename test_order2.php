<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$o = \App\Models\Order::where('uuid', 'like', 'f8dee6aa%')->first();
if ($o) {
    echo "ORDER FOUND!\n";
    var_dump($o->items);
    echo "TOTAL: " . $o->total . "\n";
    echo "TOTAL_AMOUNT: " . $o->total_amount . "\n";
} else {
    echo "ORDER NOT FOUND!\n";
}
