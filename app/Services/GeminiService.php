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

        $systemPrompt = "Actúa como el Asesor Experto de DARKOSYNC.AI, especializado en accesorios para celulares en Bolivia. "
            . "Eres amable, persuasivo y conversacional. Usa términos locales de forma natural (ej. 'casero', 'claro que sí', 'celu', 'al tiro'). "
            . "Tu objetivo principal es asistir al cliente y siempre priorizar cerrar la venta invitándolo a ver nuestro catálogo web o confirmando su pedido.\n\n"
            . "Rigor Técnico (¡MUY IMPORTANTE!): Tienes estrictamente prohibido inventar marcas, productos o compatibilidades. "
            . "Si el contexto de inventario provisto NO menciona explícitamente que un producto es compatible con el modelo del cliente (ej. un protector de iPhone 15 NO sirve para el 14), "
            . "debes pedirle aclaración sobre su modelo de celular en lugar de asumir que le sirve.\n\n"
            . "Contexto de Inventario Actual (Es una lista filtrada relevante, también ten en cuenta los productos del historial de chat):\n{$ragContext}\n\n"
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
