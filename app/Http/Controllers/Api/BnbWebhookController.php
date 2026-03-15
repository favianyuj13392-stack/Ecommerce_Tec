<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Qr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BnbWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = $request->query('secret');
        if ($secret !== env('BNB_WEBHOOK_SECRET')) {
            Log::warning('BNB Webhook: Invalid Secret', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        /*
        Payload esperado:
        {
            "id": "123",
            "qrId": "uuid-here",
            "amount": 100.50,
            "status": "PAID",
            "voucherId": "VOUCH-123",
            "paymentDate": "2026-02-23T15:30:00Z"
        }
        */

        $payload = $request->all();
        $externalQrId = $payload['qrId'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$externalQrId || $status !== 'PAID') {
            Log::info('BNB Webhook: Ignored payload or not PAID.', ['payload' => $payload]);
            return response()->json(['success' => true]); // Ignore but return 200 OK
        }

        try {
            DB::transaction(function () use ($externalQrId, $payload) {
                // Idempotencia: bloqueamos fila y verificamos si ya fue pagada
                $qr = Qr::where('external_qr_id', $externalQrId)->lockForUpdate()->first();

                if (!$qr) {
                    Log::warning('BNB Webhook: QR no encontrado en base de datos.', ['qrId' => $externalQrId]);
                    return;
                }

                if ($qr->status === 'paid' || $qr->status === 'expired') {
                    Log::info('BNB Webhook: QR ya procesado.', ['qrId' => $externalQrId, 'current_status' => $qr->status]);
                    return;
                }

                // Asegurar pago
                $qr->update([
                    'status' => 'paid',
                    'voucher_id' => $payload['voucherId'] ?? null,
                    'payment_date' => $payload['paymentDate'] ?? now(),
                ]);

                // Actualizar orden
                if ($qr->order_id) {
                    Order::where('id', $qr->order_id)->update(['status' => 'paid']);
                }

                Log::info('BNB Webhook: Pago procesado exitosamente.', ['qrId' => $externalQrId]);
            });
        } catch (\Exception $e) {
            Log::error('BNB Webhook Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unprocessable Entity'], 422);
        }

        return response()->json(['success' => true]);
    }
}
