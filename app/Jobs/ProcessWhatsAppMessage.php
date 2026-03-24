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

            // 7. Sanitización Anti-Alucinaciones Fuerte
            $rawItems = $aiResult['items'] ?? [];
            $items = is_array($rawItems) ? array_values(array_unique(Arr::flatten($rawItems))) : [];
            $discount = (int) ($aiResult['discount'] ?? 0);

            if ($discount > $maxDiscount) {
                $discount = $maxDiscount;
            }

            $aiResult['items'] = $items;
            $aiResult['discount'] = $discount;

            // Validación de stock real
            if (!empty($items)) {
                $validCount = \App\Models\Product::whereIn('slug', $items)
                    ->whereHas('variants', fn($q) => $q->where('stock', '>', 0))
                    ->count();

                if ($validCount !== count($items)) {
                    $aiResult['response'] = 'Estoy revisando el inventario exacto. ¿Qué modelo o color buscas amigo?';
                    $aiResult['items'] = [];
                    $aiResult['discount'] = 0;
                }
            }

            // 8. Envío y Memoria Final
            $finalMessage = $aiResult['response'];

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
                    'discount_offered' => $aiResult['discount'],
                    'items_recommended' => $aiResult['items']
                ]
            ];
            Redis::rpush($redisChatKey, json_encode($aiMsgObj));
            Redis::ltrim($redisChatKey, -5, -1);
            Redis::expire($redisChatKey, 86400);

            if (!empty($aiResult['items'])) {
                Redis::setex($cartRedisKey, 86400, json_encode($aiResult['items']));
            }

            // Actualizar MySQL (memoria persistente)
            if (!empty($aiResult['items'])) {
                $current = $clientContext->interested_products ?? [];
                $merged = array_unique(array_merge($current, $aiResult['items']));
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
