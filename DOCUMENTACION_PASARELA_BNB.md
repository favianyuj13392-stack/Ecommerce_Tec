# Integración BNB (Código QR)

## 1. Resumen ejecutivo

Esta guía describe cómo integrar la pasarela de pagos **Banco Nacional de Bolivia (BNB)** mediante su API de **QR fijo/variable**. El ejemplo está basado en Laravel 10 y cubre:

- obtención de token JWT,
- generación de códigos QR,
- caché y reintentos,
- manejo de SSL en Windows,
- recepción de **webhooks** de pago,
- persistencia en base de datos,
- recomendaciones para entornos de desarrollo y producción,
- errores frecuentes y cómo evitarlos.

La implementación ha sido probada con el entorno **sandbox** (`test.bnb.com.bo`).

---

## 2. Estructura de carpetas y ficheros clave

```
fundacion-esperanza/
├── app/
│   ├── Http/Controllers/BnbWebhookController.php
│   ├── Services/BnbDonationService.php
│   └── Models/Qr.php
├── config/bnb.php
├── routes/api.php
├── storage/ca-bundle.crt        ← certificado SSL
└── .env
```

---

## 3. Variables de entorno

```ini
BNB_ACCOUNT_ID="YiEpYKXixSk0zhJoQlEcdw=="     # credenciales de prueba
BNB_AUTH_ID="Fundacion2026BNB*"
BNB_SERVICE_CODE="BNB-SERVICE-CODE"
BNB_MOCK_MODE=false                           # true en dev solo si no hay SSL
BNB_WEBHOOK_SECRET=380c2faf451c847f32ec61bbc5d5e452b0aa439dfec6db319ab597ef5d38f55f
APP_URL=http://127.0.0.1:8000                # apuntar a ngrok o similar en dev
```

**Nota**: después de modificar `.env`, ejecutar:

```bash
php artisan config:clear
php artisan cache:clear
```

y en producción `php artisan config:cache`.

---

## 4. Configuración de Laravel

`config/bnb.php` (carga con casting booleano):

```php
<?php
return [
    'account_id'        => trim((string) env('BNB_ACCOUNT_ID', '')),
    'authorization_id'  => trim((string) env('BNB_AUTH_ID', '')),
    'service_code'      => trim((string) env('BNB_SERVICE_CODE', '')),
    'mock_mode'         => filter_var(env('BNB_MOCK_MODE', false), FILTER_VALIDATE_BOOLEAN),
];
```

Añade en `bootstrap/app.php` el par de líneas para forzar el CA bundle:

```php
// Bootstrap SSL certificate (Windows/Laragon)
$bundle = __DIR__.'/../storage/ca-bundle.crt';
if (file_exists($bundle)) {
    putenv("CURL_CA_BUNDLE={$bundle}");
    ini_set('curl.cainfo', $bundle);
}
```

---

## 5. Servicio BnbDonationService

```php
// app/Services/BnbDonationService.php
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
        $this->accountId   = config('bnb.account_id');
        $this->authId      = config('bnb.authorization_id');
        $this->mockMode    = config('bnb.mock_mode');
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
            Log::info('BNB Auth Success', ['token_preview'=>substr($token,0,20).'...']);
            return $token;
        }

        Log::error('BNB Auth Failed', ['body'=>$response->body()]);
        return null;
    }

    public function generateFixedQR(float $amount, string $gloss, string $trackingId): ?array
    {
        if ($this->mockMode) {
            Log::info('BNB Mock Mode: Generating fake QR', ['amount'=>$amount]);
            return [
                'success' => true,
                'qr' => base64_encode('dummy'),
                'id' => 'mock_'.uniqid(),
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

        Log::debug('BNB QR Raw Payload', ['payload'=>$jsonPayload]);
        Log::debug('BNB QR Headers Prepared', $headers);

        try {
            $response = Http::withOptions([
                    'verify' => false, // only sandbox
                    'curl'   => [CURLOPT_CAINFO => storage_path('ca-bundle.crt')]
                ])
                ->withHeaders($headers)
                ->timeout(30)
                ->connectTimeout(10)
                ->withBody($jsonPayload, 'application/json')
                ->post("{$this->baseUrlQr}/getQRWithImageAsync");

            Log::debug('BNB QR Response Received', [
                'status'=>$response->status(),
                'content_type'=>$response->header('Content-Type'),
                'body_length'=>strlen($response->body()),
            ]);

            if ($response->status() === 401) {
                Cache::forget('bnb_token');
                Log::warning('BNB 401 received, retrying with new token');
                return $this->generateFixedQR($amount, $gloss, $trackingId);
            }

            if ($response->successful()) {
                $data = $response->json();
                // normalize
                $data['qrId'] = $data['id'] ?? $data['qrId'] ?? null;
                $data['qr_image'] = $data['qr'] ?? null;
                Log::info('BNB Fixed QR Success', ['qrId'=>$data['qrId'],'amount'=>$amount]);
                return $data;
            }

            Log::error('BNB Fixed QR Failure', ['body'=>$response->body()]);
        } catch (\Exception $e) {
            Log::error('BNB Fixed QR Exception', ['message'=>$e->getMessage()]);
        }

        return null;
    }

    // ...similar para generateVariableQR y checkStatus
}
```

- **Reintentos automáticos**: si 401 se devuelve se limpia caché y vuelve a solicitar token.
- **Timeouts**: `timeout(30)` y `connectTimeout(10)` para evitar bloqueos.
- **SSL**: `withOptions(['verify'=>false,'curl'=>[CURLOPT_CAINFO=>storage_path('ca-bundle.crt')]])`
- **Mock mode**: sólo usado en desarrollo; comprueba `config('bnb.mock_mode') === true`.

---

## 6. Controlador de solicitud de QR

```php
// app/Http/Controllers/NationServiceController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\BnbDonationService;
use App\Models\Qr;

class NationServiceController extends Controller
{
    public function requestQr(Request $request, BnbDonationService $bnbService)
    {
        Log::info('requestQr: Started');
        $request->validate(['custom_amount'=>'required|numeric|min:1']);
        Log::notice('requestQr: Validation passed');

        $amount = number_format($request->input('custom_amount'), 2, '.', '');
        Log::notice('requestQr: Amount set', ['amount'=>$amount]);

        $gloss = 'FNE-D-'.time().' Donacion: '.$request->input('name','Anónimo');
        $internalId = 'don_'.uniqid();

        $response = $bnbService->generateFixedQR($amount, $gloss, $internalId);

        Log::notice('requestQr: generateFixedQR returned', ['response_keys'=>array_keys((array)$response)]);
        if (!$response || empty($response['qrId'])) {
            Log::error('requestQr: QR generation failed', ['response'=>$response]);
            return response()->json(['success'=>false,'message'=>'Service Unavailable'], 503);
        }

        // persistir en BD
        $qr = Qr::create([
            'code' => $response['qrId'],
            'external_qr_id' => $response['qrId'],
            'qr' => $response['qr_image'],
            'amount' => $amount,
            'status' => 'new',
            'donor_name' => $request->input('name','Anónimo'),
            'bnb_blob' => json_encode($response),
            'expiration_date' => now()->addDay(),
        ]);

        Log::notice('requestQr: QR record created', ['qr_id'=>$qr->id]);
        return response()->json([
            'qr_image'=>$response['qr_image'],
            'qr_id'=>$response['qrId'],
            'expiration'=>$qr->expiration_date,
            'success'=>true
        ]);
    }
}
```

---

## 7. Webhook de pago

Ruta en `routes/api.php`:

```php
Route::post('/webhooks/bnb', [BnbWebhookController::class, 'handle']);
```

Controlador ya implementado (mismo contenido que se muestra en tu fichero `BnbWebhookController.php` adjunto): validación de `?secret=`, verificación de estado, actualización de registro y creación de donación.

---

## 8. Esquema de la tabla `qrs`

```sql
CREATE TABLE `qrs` (
  `id` bigint unsigned AUTO_INCREMENT PRIMARY KEY,
  `code` varchar(100) NOT NULL,
  `external_qr_id` varchar(100) NOT NULL,
  `qr` text,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('new','paid','expired') DEFAULT 'new',
  `donor_name` varchar(255) DEFAULT NULL,
  `voucher_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `bnb_blob` longtext,
  `expiration_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL
);
```

---

## 9. Errores comunes y soluciones

[Listados como antes]

---

## 10. Procedimiento para replicar en otro proyecto

1. Copiar config, servicios y controladores.
2. Añadir migración de tabla `qrs` y modelo.
3. Ajustar `.env`.
4. Descargar CA bundle.
5. Limpiar caché y probar con Postman.

---

## 11. Simulación de pagos (sandbox)

[...contenido de la guía de simulación tal como en versión anterior...]

---

## 12. Checklist antes de deploy

[Listado de validaciones]

---

## 13. Referencias

- Documentación BNB PDF
- Colección Postman propiedad del proyecto
- Errores cURL

---

Este documento está actualizado al 23 de febrero de 2026 y puede usarse como base para replicar el módulo en otros sistemas Laravel.

```
