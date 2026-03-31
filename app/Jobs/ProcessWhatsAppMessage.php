<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public array $payload;
    public $tries = 1;
    public $timeout = 30;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Helper privado para el Job. Escáner de seguridad de borde.
     */
    private function containsProhibitedWords(string $text): bool
    {
        $prohibited = [
            'gay', 'maricón', 'maricon', 'puto', 'joto', 'piter gay', 
            'idiota', 'estúpido', 'estupido', 'imbécil', 'imbecil', 
            'mierda', 'carajo', 'pendejo', 'pinga', 'verga', 'cojudo'
        ];

        $lowerText = strtolower(trim($text));
        
        foreach ($prohibited as $word) {
            // Coincidencia de la palabra o "piter gay"
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $lowerText) || str_contains($lowerText, 'piter gay')) {
                return true;
            }
        }
        return false;
    }

    public function handle(): void
    {
        try {
            $entry = $this->payload['entry'][0] ?? null;
            $changes = $entry['changes'][0] ?? null;
            $value = $changes['value'] ?? null;

            $messages = $value['messages'] ?? null;
            if (!$messages || !isset($messages[0])) {
                return;
            }

            $message = $messages[0];
            if (($message['type'] ?? '') !== 'text') {
                return;
            }

            $phone = $message['from'];
            $wamid = $message['id'] ?? null;
            $text = $message['text']['body'];

            Log::info("Iniciando procesamiento de Mensaje para: " . $phone);

            // 1. Registro Lead y Mensaje Inbound
            $lead = \App\Models\Lead::firstOrCreate(
                ['whatsapp_id' => $phone],
                ['name' => $value['contacts'][0]['profile']['name'] ?? 'Cliente', 'is_ai_enabled' => true]
            );
            $lead->increment('interaction_count');

            $lead->whatsappMessages()->create([
                'message_id' => $wamid,
                'body' => $text,
                'direction' => 'inbound',
                'source' => 'user',
            ]);

            if ($lead->is_ai_enabled === false) {
                Log::info("Intervención humana activa para {$phone}. IA silenciada.");
                return;
            }

            // 2. Memoria Corta (Redis)
            $redisChatKey = 'chat:' . $phone;
            $cartRedisKey = 'cart:' . $phone;

            $userMsgObj = [
                'role' => 'user',
                'content' => $text,
                'timestamp' => now()->toIso8601String(),
            ];

            Redis::rpush($redisChatKey, json_encode($userMsgObj));
            Redis::ltrim($redisChatKey, -5, -1);
            Redis::expire($redisChatKey, 86400);

            $rawHistory = Redis::lrange($redisChatKey, 0, -1);
            $history = array_map(fn($item) => json_decode($item, true), $rawHistory);

            $cartRedisRaw = Redis::get($cartRedisKey);
            $cartRedis = $cartRedisRaw ? json_decode($cartRedisRaw, true) : [];

            // 3. Memoria Larga (MySQL)
            $clientContext = \App\Models\ClientContext::firstOrCreate(
                ['phone' => $phone],
                ['interested_products' => []]
            );
            $clientContext->last_interaction = now();
            $clientContext->save();

            // 4. Anti-Abuso y Reglas
            $client = \App\Models\Client::firstOrCreate(['phone' => $phone]);
            $abuseFlag = count($client->abandonment_history ?? []) >= 3;

            $activeRule = \App\Models\DiscountRule::where('active', true)->first();
            $maxDiscount = $activeRule ? $activeRule->max_percent : 0;

            // 5. RAG SQL (Refinamiento Crítico: Conservar el Historial y Matching Completo)
            $recentUserMsgs = collect($history)->where('role', 'user')->pluck('content')->implode(' ');
            $searchText = strtolower(trim($recentUserMsgs));
            
            $query = \App\Models\Product::with(['variants' => fn($q) => $q->where('stock', '>', 0)])
                ->whereHas('variants', fn($q) => $q->where('stock', '>', 0));

            if (strlen($searchText) > 3) {
                // MATCH AGAINST exige exactamente las mismas columnas indexadas en el FULLTEXT
                $query->whereRaw("MATCH(nombre, descripcion, marca, categoria) AGAINST(? IN BOOLEAN MODE)", [$searchText]);
            }

            $products = $query->limit(3)->get();

            if ($products->isEmpty()) {
                $products = \App\Models\Product::with(['variants' => fn($q) => $q->where('stock', '>', 0)])
                    ->whereHas('variants', fn($q) => $q->where('stock', '>', 0))
                    ->limit(3)
                    ->get();
            }

            $ragContext = $products->isEmpty()
                ? 'IMPORTANTE: No tenemos NINGÚN producto con stock físico.'
                : $products->map(fn($p) => "Producto: {$p->nombre} | Slug: {$p->slug} | Precio: {$p->precio} Bs")->implode("\n");

            // 6. Llamada DeepSeek
            $aiService = app(\App\Services\DeepSeekService::class);
            $aiResult = $aiService->generateResponse(
                $text,
                $history,
                $ragContext,
                $abuseFlag,
                $maxDiscount,
                $cartRedis,
                $clientContext->interested_products ?? []
            );

            // 7. Interceptor Anti-Troll y Mapeo de Variables
            $intent = $aiResult['intent'] ?? 'chat';
            $finalMessage = $aiResult['message'] ?? 'Estamos procesando tu solicitud casero...';

            if ($this->containsProhibitedWords($finalMessage) || $this->containsProhibitedWords($text)) {
                $finalMessage = "¡Jajaja casero! Casi me haces decir una locura 😂 Mejor volvamos a los accesorios. ¿Qué buscabas?";
                $intent = 'troll';
                $aiResult['intent'] = 'troll'; // Forcefully overwrite AI's given intent
                $aiResult['items'] = [];
            }

            $rawItems = $aiResult['items'] ?? [];
            $items = [];
            
            // Sanitización y Compatibilidad Carritos Antiguos/Malformados
            if (is_array($rawItems)) {
                foreach ($rawItems as $itm) {
                    if (is_string($itm)) { 
                        $items[] = ['slug' => $itm, 'qty' => 1]; 
                    } elseif (is_array($itm) && isset($itm['slug'])) { 
                        $items[] = [
                            'slug' => (string) trim($itm['slug']), 
                            'qty' => max(1, (int) ($itm['qty'] ?? 1))
                        ];
                    }
                }
            }

            // Validamos que los ítems realmente existan en BD (stock > 0)
            $pureSlugs = array_values(array_unique(array_column($items, 'slug')));
            $discount = 0; // Fixeado a 0 como solicitaron

            if (!empty($pureSlugs) && $intent !== 'troll') {
                $validCount = \App\Models\Product::whereIn('slug', $pureSlugs)
                    ->whereHas('variants', fn($q) => $q->where('stock', '>', 0))
                    ->count();

                if ($validCount !== count($pureSlugs)) {
                    $finalMessage = 'Estoy revisando el inventario exacto. ¿Qué modelo o color buscas amigo?';
                    $items = [];
                }
            }

            // 8. Envío, Intent de Cierre de Venta y Memoria Final
            $compraItems = !empty($items) ? $items : $cartRedis; 

            // Verificamos "buy", que haya items a comprar y que el texto no sea troll
            if ($intent === 'buy' && !empty($compraItems) && $this->containsProhibitedWords($finalMessage) === false) {
                
                $pureSlugsCompra = array_unique(array_column($compraItems, 'slug'));
                
                // Calculamos Total recorriendo la BD y cantidades
                $totalBruto = 0;
                $dbProducts = \App\Models\Product::whereIn('slug', $pureSlugsCompra)->get()->keyBy('slug');
                                
                foreach ($compraItems as $cItem) {
                    if ($dbProduct = $dbProducts->get($cItem['slug'])) {
                        $totalBruto += $dbProduct->precio * $cItem['qty'];
                    }
                }

                $totalFinal = $totalBruto - ($totalBruto * ($discount / 100));

                $order = \App\Models\Order::create([
                    'lead_id' => $lead->id,
                    'items' => $compraItems,
                    'total' => $totalFinal,
                    'status' => 'pending',
                ]);

                Redis::del($cartRedisKey); // Vaciamos para no cobrar dobles
                $items = []; // Reseteamos items para que no reescriba el carrito en la línea inferior

                $finalMessage = "¡Ya está tu pedido, casero! Aquí tienes el resumen exacto y tu código seguro (BNB) para pagarlo al instante:\n\n" . config('app.url') . "/checkout/" . $order->uuid;
            }

            // Registramos el mensaje emitido
            $lead->whatsappMessages()->create([
                'body' => $finalMessage,
                'direction' => 'outbound',
                'source' => 'ai',
                'tokens_used' => $aiResult['tokens'] ?? 0,
            ]);

            $whatsappService = app(\App\Services\WhatsAppService::class);
            $whatsappService->sendMessage($phone, $finalMessage);

            // Actualizar Redis
            $aiMsgObj = [
                'role' => 'model',
                'content' => $finalMessage,
                'timestamp' => now()->toIso8601String(),
                'metadata' => [
                    'discount_offered' => $discount,
                    'items_recommended' => $items
                ]
            ];
            Redis::rpush($redisChatKey, json_encode($aiMsgObj));
            Redis::ltrim($redisChatKey, -5, -1);
            Redis::expire($redisChatKey, 86400);

            if (!empty($items)) {
                Redis::setex($cartRedisKey, 86400, json_encode($items));
            }

            // Actualizar MySQL (memoria persistente) guardando sólo slugs para intereses
            if (!empty($pureSlugs)) {
                $current = $clientContext->interested_products ?? [];
                $merged = array_unique(array_merge($current, $pureSlugs));
                $clientContext->interested_products = array_values($merged);
                $clientContext->save();
            }

        } catch (\Exception $e) {
            Log::error("ProcessWhatsAppMessage Error Crítico: " . $e->getMessage());
            $phone = $message['from'] ?? null;
            if ($phone) {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                $whatsappService->sendMessage($phone, "¡Hola casero! Estamos reabasteciendo la tienda un momento. ¿En qué te ayudo?");
            }
            return;
        }
    }
}
