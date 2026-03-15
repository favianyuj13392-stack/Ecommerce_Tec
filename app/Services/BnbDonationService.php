<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BnbDonationService
{
    protected string $baseUrlAuth;
    protected string $baseUrlQr;
    protected string $accountId;
    protected string $authId;
    protected bool   $mockMode;

    public function __construct()
    {
        $this->baseUrlAuth = 'http://test.bnb.com.bo/ClientAuthentication.API/api/v1';
        $this->baseUrlQr   = 'http://test.bnb.com.bo/QRSimple.API/api/v1/main';
        $this->accountId   = config('bnb.account_id', 'YiEpYKXixSk0zhJoQlEcdw==');
        $this->authId      = config('bnb.authorization_id', 'Fundacion2026BNB*');
        $this->mockMode    = config('bnb.mock_mode', false);
    }

    public function authenticate(): ?string
    {
        if ($token = Cache::get('bnb_token')) {
            return $token;
        }

        $jsonPayload = '{"accountId":"'.$this->accountId.'","authorizationId":"'.$this->authId.'"}';

        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'PostmanRuntime/7.32.0',
        ];

        Log::debug('BNB Auth Request', ['payload'=>$jsonPayload,'headers'=>$headers]);

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->connectTimeout(5)
            ->withBody($jsonPayload, 'application/json')
            ->post("{$this->baseUrlAuth}/auth/token");

        Log::debug('BNB Auth Response', ['status'=>$response->status(), 'body'=>$response->body()]);

        if ($response->successful() && $response->json('success')) {
            $token = $response->json('message');
            Cache::put('bnb_token', $token, 3000);
            return $token;
        }

        Log::error('BNB Auth Failed', ['body'=>$response->body()]);
        return null;
    }

    public function generateFixedQR(float $amount, string $gloss, string $trackingId): ?array
    {
        if ($this->mockMode) {
            return [
                'success' => true,
                'qrId' => 'mock_'.uniqid(),
                'qr_image' => base64_encode('dummy'),
            ];
        }

        $token = $this->authenticate();
        if (!$token) return null;

        $jsonPayload = '{"currency":"BOB","gloss":"'.$gloss.'","amount":'.$amount.',"singleUse":true,"expirationDate":"'.now()->addDay()->format('Y-m-d').'","additionalData":"'.$trackingId.'","destinationAccountId":"1"}';

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'PostmanRuntime/7.32.0',
            'cache-control' => 'no-cache',
        ];

        try {
            $response = Http::withOptions([
                    'verify' => false,
                ])
                ->withHeaders($headers)
                ->timeout(30)
                ->connectTimeout(10)
                ->withBody($jsonPayload, 'application/json')
                ->post("{$this->baseUrlQr}/getQRWithImageAsync");

            if ($response->status() === 401) {
                Cache::forget('bnb_token');
                return $this->generateFixedQR($amount, $gloss, $trackingId);
            }

            if ($response->successful()) {
                $data = $response->json();
                $data['qrId'] = $data['id'] ?? $data['qrId'] ?? null;
                $data['qr_image'] = $data['qr'] ?? null;
                return $data;
            }
        } catch (\Exception $e) {
            Log::error('BNB Fixed QR Exception', ['message'=>$e->getMessage()]);
        }

        return null;
    }
}
