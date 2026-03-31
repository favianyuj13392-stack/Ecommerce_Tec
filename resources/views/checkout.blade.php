<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasarela de Pago | DARKOSYNC.AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen font-sans antialiased selection:bg-indigo-500 selection:text-white">

    <div class="max-w-md mx-auto pt-6 pb-12 px-4 shadow-xl min-h-screen flex flex-col justify-center">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-500 tracking-tight">
                DARKOSYNC<span class="text-white">.AI</span>
            </h1>
            <p class="text-gray-400 text-sm mt-1">Cierre de Venta Seguro</p>
        </div>

        <!-- Tarjeta Principal -->
        <div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 overflow-hidden transform transition-all duration-300 hover:shadow-indigo-500/10">
            
            <!-- Estado del Pedido -->
            <div class="bg-gray-800/50 p-4 border-b border-gray-700 flex justify-between items-center">
                <span class="text-sm font-medium text-gray-400">Orden #{{ substr($order->uuid, 0, 8) }}</span>
                @if($order->status === 'paid')
                    <span class="px-3 py-1 bg-green-500/20 text-green-400 text-xs font-bold rounded-full border border-green-500/30 flex items-center gap-1"><i class="fas fa-check-circle"></i> PAGADO</span>
                @elseif($order->status === 'cancelled')
                    <span class="px-3 py-1 bg-red-500/20 text-red-400 text-xs font-bold rounded-full border border-red-500/30 flex items-center gap-1"><i class="fas fa-times-circle"></i> CANCELADO</span>
                @else
                    <span class="px-3 py-1 bg-yellow-500/20 text-yellow-400 text-xs font-bold rounded-full border border-yellow-500/30 flex items-center gap-1"><i class="fas fa-clock"></i> PENDIENTE</span>
                @endif
            </div>

            <!-- Total y QR -->
            <div class="p-6 text-center bg-gradient-to-b from-gray-800 to-gray-900">
                <p class="text-gray-400 text-sm mb-1 uppercase tracking-wider font-semibold">Total a Pagar</p>
                <div class="text-5xl font-black text-white mb-6 drop-shadow-md">
                    Bs. {{ number_format($order->total, 2) }}
                </div>

                @if($order->status !== 'paid' && $order->status !== 'cancelled')
                    <div class="bg-white p-3 rounded-2xl inline-block shadow-lg mx-auto border-4 border-gray-100 ring-4 ring-indigo-500/20 transition-transform hover:scale-105">
                        <img src="{{ $qrImage }}" alt="QR de Pago" class="w-48 h-48 object-contain rounded-xl">
                    </div>
                    <p class="text-gray-400 text-xs mt-4 flex items-center justify-center gap-2">
                        <i class="fas fa-lock text-gray-500"></i> Escanea con la app de tu banco (BNB Sandbox activo)
                    </p>
                @endif
            </div>

            <!-- Resumen de Productos -->
            <div class="p-6 border-t border-gray-700 bg-gray-800/80">
                <h3 class="text-sm font-bold text-gray-200 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <i class="fas fa-shopping-bag text-indigo-400"></i> Resumen del Pedido
                </h3>
                
                <div class="space-y-4">
                    @forelse($products as $product)
                    <div class="flex justify-between items-center p-3 rounded-xl bg-gray-700/30 border border-gray-600/50 hover:bg-gray-700/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gray-900 flex items-center justify-center border border-gray-800 text-indigo-400 shadow-inner">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-200 leading-tight">{{ $product->nombre }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $product->marca }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-gray-300">Bs. {{ number_format($product->precio, 2) }}</span>
                    </div>
                    @empty
                    <p class="text-gray-500 text-sm italic text-center py-2">Sin productos reconstruidos en BD.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-xs text-gray-600 font-medium">PROTEGIDO POR DARKOSYNC.AI SECURITY</p>
        </div>
    </div>

</body>
</html>
