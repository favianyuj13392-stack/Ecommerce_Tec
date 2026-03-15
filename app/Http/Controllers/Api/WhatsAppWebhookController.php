<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Jobs\ProcessWhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Maneja la verificación inicial de Meta (Hub Challenge)
     */
    public function verify(Request $request)
    {
        $verifyToken = env('WHATSAPP_VERIFY_TOKEN');
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'No autorizado'], 403);
    }

    /**
     * Maneja los mensajes entrantes vía POST
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        // 1. Despachamos a la Cola para que sea Async y no bloquear la respuesta a Meta
        ProcessWhatsAppMessage::dispatch($payload);

        // 2. Retornamos inmediatamente el status 200 OK
        return response()->json(['status' => 'ok'], 200);
    }
}
