<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    public function generateResponse(
        string $userMessage,
        array $chatHistory,
        string $ragContext,
        bool $abuseFlag,
        int $maxDiscount = 15,
        array $cartRedis = [],
        array $mysqlInterests = []
    ): array
    {
        $apiKey = config('services.deepseek.api_key');

        $fallback = [
            'response' => '¡Hola casero! Estamos reabasteciendo la tienda un momento. ¿En qué te ayudo?',
            'discount' => 0,
            'items' => [],
            'tokens' => 0
        ];

        if (!$apiKey) {
            Log::error('DeepSeekService Error: Missing DEEPSEEK_API_KEY');
            return $fallback;
        }

        $cartStr = empty($cartRedis) ? 'Vacío' : json_encode($cartRedis);
        $interestsStr = empty($mysqlInterests) ? 'Ninguno' : implode(', ', $mysqlInterests);

        $systemPrompt = "Eres vendedor de DARKOSYNC.AI. Usa el CONTEXTO ESTRUCTURADO para recordar qué vendes. "
            . "Responde conciso, estilo boliviano ('casero'). Prioriza cerrar la venta. "
            . "Nunca inventes stock, colores, tallas ni productos. "
            . "Si el cliente pide cerrar pedido, pagar o confirma compra, retorna \"intent\": \"buy\". Si no, \"chat\". "
            . "Responde OBLIGATORIAMENTE en JSON válido: {\"response\": \"texto\", \"discount\": 0, \"items\": [\"slug1\", \"slug2\"], \"intent\": \"buy\"|\"chat\"}. "
            . "### CONTEXTO DE VENTA ### Carrito temporal: {$cartStr} | Intereses previos: {$interestsStr} | Inventario Real: {$ragContext}";

        $formattedHistory = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? 'user') === 'model' ? 'assistant' : 'user';
            $formattedHistory[] = [
                'role' => $role,
                'content' => $msg['content'] ?? ''
            ];
        }

        $formattedHistory[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model' => 'deepseek-chat',
            'messages' => $formattedHistory,
            'temperature' => 0.7,
            'max_tokens' => 300,
            'response_format' => ['type' => 'json_object']
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json'
                ])
                ->post('https://api.deepseek.com/v1/chat/completions', $payload)
                ->throw();

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                $parsed = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['response'])) {
                    return [
                        'response' => $parsed['response'],
                        'discount' => (int) ($parsed['discount'] ?? 0),
                        'items' => is_array($parsed['items'] ?? null) ? $parsed['items'] : [],
                        'intent' => $parsed['intent'] ?? 'chat',
                        'tokens' => $data['usage']['total_tokens'] ?? 0
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('DeepSeekService Exception: ' . $e->getMessage());
        }

        return $fallback;
    }
}
