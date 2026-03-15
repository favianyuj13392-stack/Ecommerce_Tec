<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public array $payload;
    public $tries = 1; // Prevenir bucles infinitos

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Paso A (Parseo): Verificar si es un mensaje de texto válido
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
        $text = $message['text']['body'];

        \Illuminate\Support\Facades\Log::info("Iniciando procesamiento de IA para: " . $phone);

        // Paso B (Memoria de Chat)
        $chat = \App\Models\Chat::firstOrCreate(
            ['phone' => $phone],
            ['history_json' => []]
        );

        $history = $chat->history_json ?? [];
        $history[] = [
            'role' => 'user',
            'content' => $text,
            'timestamp' => now()->toIso8601String(),
        ];
        
        $chat->history_json = $history;
        $chat->timestamp = now();
        $chat->save();

        // Paso C (Registro de Lead)
        $lead = \App\Models\Lead::firstOrCreate(
            ['whatsapp_id' => $phone],
            ['name' => $value['contacts'][0]['profile']['name'] ?? 'Cliente']
        );
        $lead->increment('interaction_count');

        // Paso 1 – Anti-Abuso y Reglas
        $client = \App\Models\Client::firstOrCreate(['phone' => $phone]);
        $historyArray = $client->abandonment_history ?? [];
        $abuseFlag = count($historyArray) >= 3;
        
        $activeRule = \App\Models\DiscountRule::where('active', true)->first();
        $maxDiscount = $activeRule ? $activeRule->max_percent : 0;

        // Paso 2 – RAG SQL (Simple)
        $query = \App\Models\Product::whereHas('variants', fn($q) => $q->where('stock', '>', 0));
        
        if (strlen($text) > 3) {
            $query->whereRaw("MATCH(nombre, descripcion) AGAINST(? IN BOOLEAN MODE)", [$text]);
        }
        
        $relevantProducts = $query->limit(3)->get(['slug', 'nombre', 'precio'])->toJson();
        $ragContext = $relevantProducts !== '[]' ? $relevantProducts : 'IMPORTANTE: Actualmente no tenemos productos con stock en este instante. Informa al cliente cortésmente y pregúntale qué busca para anotarlo en lista de espera.';

        // Paso 3 – Llamada Gemini
        $gemini = app(\App\Services\GeminiService::class);
        $recentHistory = array_slice($chat->history_json?->toArray() ?? [], -5); // Memoria corta
        $geminiResult = $gemini->generateResponse($text, $recentHistory, $ragContext, $abuseFlag, $maxDiscount);

        // Paso 4 – Validación Post-IA (Anti-Alucinaciones)
        $items = $geminiResult['items'] ?? [];
        $discount = $geminiResult['discount'] ?? 0;

        // Validar límite de descuento
        if ($discount > $maxDiscount) {
            $geminiResult['discount'] = $maxDiscount;
        }

        // Validar stock estricto
        if (!empty($items)) {
            $validItemsCount = \App\Models\Product::whereIn('slug', $items)
                ->whereHas('variants', fn($q) => $q->where('stock', '>', 0))
                ->count();

            if ($validItemsCount !== count($items)) {
                $geminiResult['response'] = '¡Genial! Dejame verificar el inventario exacto de ese modelo. ¿Qué talla buscabas?';
                $geminiResult['items'] = [];
                $geminiResult['discount'] = 0;
            }
        }

        // Paso 5 – Envío y Memoria
        $finalMessage = $geminiResult['response'];
        
        $whatsappService = app(\App\Services\WhatsAppService::class);
        $whatsappService->sendMessage($phone, $finalMessage);

        $history[] = [
            'role' => 'model',
            'content' => $finalMessage,
            'timestamp' => now()->toIso8601String(),
            'metadata' => [
                'discount_offered' => $geminiResult['discount'],
                'items_recommended' => $geminiResult['items']
            ]
        ];
        
        $chat->history_json = $history;
        $chat->save();
    }
}
