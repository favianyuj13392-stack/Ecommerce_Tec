<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BnbPaymentService
{
    protected string $accountId;
    protected string $authId;
    protected bool $mockMode;

    public function __construct()
    {
        $this->accountId = config('services.bnb.account_id');
        $this->authId = config('services.bnb.auth_id');
        $this->mockMode = config('services.bnb.mock_mode', false);
    }

    /**
     * Authenticates with BNB and retrieves a JWT Token.
     */
    public function authenticate(): ?string
    {
        if ($this->mockMode) {
            return 'mock_token_123';
        }

        try {
            // Nota: Al tratarse de un Sandbox se asume la URL de pruebas del BNB (API SrvEnlace)
            $response = Http::timeout(5)->post('https://test.bnb.com.bo/ApiSrvEnlaceEmpresasQr/api/Qr/GetQrAuthentication', [
                'accountId' => $this->accountId,
                'authenticationId' => $this->authId
            ]);

            if ($response->successful()) {
                return $response->json('token');
            }
            
            Log::error("BNB Auth Failed: " . $response->body());
        } catch (\Exception $e) {
            Log::error("BNB Auth Exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Generates a single-use QR code for a given order logic.
     */
    public function generateQR(string $orderUuid, float $amount, string $gloss = 'Compra Accesorios'): ?string
    {
        if ($this->mockMode) {
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='; // 1x1 black pixel mock
        }

        $token = $this->authenticate();

        if (!$token) {
            Log::error("Cannot generate QR without BNB Auth Token.");
            return null;
        }

        try {
            // Endpoint estándar de generación de QR QrSimple
            $response = Http::timeout(5)->withToken($token)->post('https://test.bnb.com.bo/ApiSrvEnlaceEmpresasQr/api/Qr/GenerarQr', [
                'businessCode' => $this->accountId,
                'amount' => $amount,
                'currency' => 'BOB',
                'gloss' => $gloss,
                'singleUse' => true,
                'expirationDays' => 1,
                'additionalData' => $orderUuid
            ]);

            if ($response->successful()) {
                // Normalmente el BNB retorna un campo 'qrImage' base64 o un 'id' para construirlo
                // Adaptamos al payload estándar de respuesta QR del BNB
                return 'data:image/png;base64,' . $response->json('qrImage');
            }

            Log::error("BNB QR Gen Failed: " . $response->body());
        } catch (\Exception $e) {
            Log::error("BNB QR Gen Exception: " . $e->getMessage());
        }

        return null;
    }
}
