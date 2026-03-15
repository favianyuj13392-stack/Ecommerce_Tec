<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send a text message via WhatsApp Cloud API.
     *
     * @param string $to The recipient's phone number.
     * @param string $text The message to send.
     * @return bool True if successful, false otherwise.
     */
    public function sendMessage(string $to, string $text): bool
    {
        $token = env('WHATSAPP_TOKEN');
        $phoneId = env('WHATSAPP_PHONE_ID');

        if (!$token || !$phoneId) {
            Log::error('WhatsAppService Error: Missing WHATSAPP_TOKEN or WHATSAPP_PHONE_ID in .env');
            return false;
        }

        $url = "https://graph.facebook.com/v21.0/{$phoneId}/messages";

        $response = Http::withToken($token)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => [
                    'body' => $text,
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('WhatsAppService Error: Failed to send message.', [
            'to'       => $to,
            'status'   => $response->status(),
            'response' => $response->json(),
        ]);

        return false;
    }
}
