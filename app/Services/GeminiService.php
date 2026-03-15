<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Generates a conversational response using Google Gemini AI structured as JSON.
     *
     * @param string $userMessage
     * @param array $chatHistory
     * @param string $ragContext
     * @param bool $abuseFlag
     * @param int $maxDiscount
     * @return array
     */
    public function generateResponse(string $userMessage, array $chatHistory, string $ragContext, bool $abuseFlag, int $maxDiscount): array
    {
        $apiKey = env('GEMINI_API_KEY');
        
        $fallback = [
            'response' => '¡Hola! Estamos experimentando alta demanda. ¿En qué te puedo ayudar hoy?',
            'discount' => 0,
            'items' => []
        ];

        if (!$apiKey) {
            Log::error('GeminiService Error: Missing GEMINI_API_KEY in .env');
            return $fallback;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}";
                
        // Formatting chat history into Gemini's "contents" format
        $formattedHistory = [];
        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? '') === 'model' ? 'model' : 'user';
            $content = $msg['content'] ?? '';
            $formattedHistory[] = [
                'role' => $role,
                'parts' => [['text' => $content]]
            ];
        }

        // Add the current user message
        $formattedHistory[] = [
            'role' => 'user',
            'parts' => [['text' => "Este es mi mensaje: {$userMessage}\n\nPor favor, responde aplicando todas las reglas del sistema y devolviendo un JSON válido."]]
        ];

        $discountRule = $abuseFlag 
            ? "TIENES PROHIBIDO ofrecer descuentos (abuseFlag es true)." 
            : "Tu descuento máximo permitido es {$maxDiscount}% (abuseFlag es false).";

        $systemPrompt = "Actúa como un vendedor e-commerce boliviano amigable, persuasivo y conversacional. "
            . "Responde de forma natural y concisa en formato texto plano (las respuestas se usarán en WhatsApp). "
            . "Tienes estrictamente prohibido inventar o alucinar productos.\n\n"
            . "Contexto de Inventario (Solo puedes recomendar esto):\n{$ragContext}\n\n"
            . "Reglas de Descuento:\n{$discountRule}\n\n"
            . "Tu respuesta DEBE ser estrictamente un objeto JSON con esta estructura exacta, y usar lenguaje natural en la key 'response':\n"
            . '{"response": "texto_natural_aquí", "discount": numero_entero_o_cero, "items": ["slug1", "slug2", "etc"]}';

        $payload = [
            'contents' => $formattedHistory,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload)
                ->throw();

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($content) {
                    // Limpiar posibles bloques de código Markdown devueltos por la IA
                    $content = preg_replace('/^```json\s*/i', '', trim($content));
                    $content = preg_replace('/^```\s*/i', '', trim($content));
                    $content = preg_replace('/\s*```$/i', '', trim($content));
                    
                    $parsed = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return [
                            'response' => $parsed['response'] ?? $fallback['response'],
                            'discount' => (int) ($parsed['discount'] ?? 0),
                            'items'    => is_array($parsed['items'] ?? null) ? $parsed['items'] : []
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('GeminiService Exception: ' . $e->getMessage());
        }

        return $fallback;
    }
}
