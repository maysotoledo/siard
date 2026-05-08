<?php

namespace App\Services\Billing;

use App\Models\PixelPaymentRequest;
use App\Models\PixelSubscription;
use App\Models\User;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoPixelBillingService
{
    public function latestPendingPayment(User $user): ?PixelPaymentRequest
    {
        $existing = $user->pixelPaymentRequests()
            ->whereIn('status', ['pending', 'in_process', 'waiting_payment', 'action_required'])
            ->latest('id')
            ->first();

        if (! $existing) {
            return null;
        }

        if ($existing->expires_at && $existing->expires_at->isPast()) {
            return null;
        }

        return $existing;
    }

    public function ensurePendingPayment(User $user): PixelPaymentRequest
    {
        $existing = $this->latestPendingPayment($user);

        if ($existing) {
            return $existing;
        }

        return $this->createPayment($user);
    }

    public function createPayment(User $user): PixelPaymentRequest
    {
        $payerEmail = $this->payerEmail($user);

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->withHeaders([
                'X-Idempotency-Key' => (string) Str::uuid(),
            ])
            ->post('https://api.mercadopago.com/v1/payments', [
                'transaction_amount' => $this->monthlyAmount(),
                'description' => 'Mensalidade Pixel Tracker SACAT',
                'payment_method_id' => 'pix',
                'notification_url' => $this->notificationUrl(),
                'external_reference' => 'pixel-subscription:' . $user->getKey() . ':' . Str::uuid(),
                'date_of_expiration' => $this->expirationDate(),
                'payer' => [
                    'email' => $payerEmail,
                    'first_name' => $user->name ?: 'Usuario',
                ],
            ]);

        if (! $response->successful()) {
            $message = data_get($response->json(), 'message')
                ?: data_get($response->json(), 'error')
                ?: 'Resposta inesperada do Mercado Pago.';

            throw new RuntimeException('Nao foi possivel criar a cobranca Pix no Mercado Pago: ' . $message);
        }

        $payload = $response->json();
        $pixCode = $this->pixCode($payload);
        $qrCodeBase64 = $this->qrCodeBase64($payload) ?: $this->generateQrCodeBase64($pixCode);

        Log::info('Mercado Pago Pixel: cobranca criada', [
            'payment_id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
            'status_detail' => $payload['status_detail'] ?? null,
            'has_qr_code' => filled($pixCode),
            'has_qr_code_base64' => filled($qrCodeBase64),
            'ticket_url' => data_get($payload, 'point_of_interaction.transaction_data.ticket_url'),
        ]);

        return PixelPaymentRequest::create([
            'user_id' => $user->getKey(),
            'provider' => 'mercado_pago',
            'external_reference' => (string) ($payload['external_reference'] ?? ''),
            'mercado_pago_payment_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            'amount' => (float) ($payload['transaction_amount'] ?? $this->monthlyAmount()),
            'status' => $this->normalizeStatus($payload),
            'status_detail' => $this->statusDetail($payload),
            'pix_copy_paste' => $pixCode,
            'qr_code_base64' => $qrCodeBase64,
            'ticket_url' => $this->ticketUrl($payload),
            'expires_at' => $this->parseDate($payload['date_of_expiration'] ?? null),
            'approved_at' => $this->parseDate($payload['date_approved'] ?? null),
            'provider_payload' => $payload,
        ]);
    }

    public function syncPaymentByMercadoPagoId(string $paymentId): ?PixelPaymentRequest
    {
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $payment = PixelPaymentRequest::query()
            ->where('mercado_pago_payment_id', (string) ($payload['id'] ?? $paymentId))
            ->orWhere('external_reference', (string) ($payload['external_reference'] ?? ''))
            ->latest('id')
            ->first();

        if (! $payment) {
            return null;
        }

        $pixCode = $this->pixCode($payload) ?: $payment->pix_copy_paste;
        $qrCodeBase64 = $this->qrCodeBase64($payload)
            ?: $payment->qr_code_base64
            ?: $this->generateQrCodeBase64($pixCode);

        $payment->forceFill([
            'status' => $this->normalizeStatus($payload),
            'status_detail' => $this->statusDetail($payload),
            'pix_copy_paste' => $pixCode,
            'qr_code_base64' => $qrCodeBase64,
            'ticket_url' => $this->ticketUrl($payload) ?: $payment->ticket_url,
            'expires_at' => $this->parseDate($payload['date_of_expiration'] ?? null) ?? $payment->expires_at,
            'approved_at' => $this->parseDate($payload['date_approved'] ?? null),
            'provider_payload' => $payload,
        ])->save();

        if ($payment->status === 'approved') {
            $this->activateSubscription($payment);
        }

        return $payment;
    }

    public function hydratePaymentDetails(PixelPaymentRequest $payment, int $attempts = 8, int $delayMs = 500): PixelPaymentRequest
    {
        if (! $payment->mercado_pago_payment_id) {
            return $payment;
        }

        $currentPayment = $payment;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $syncedPayment = $this->syncPaymentByMercadoPagoId($payment->mercado_pago_payment_id);

            if ($syncedPayment) {
                $currentPayment = $syncedPayment;
            }

            if ($currentPayment->qr_code_base64 || $currentPayment->pix_copy_paste) {
                break;
            }

            if ($attempt < ($attempts - 1)) {
                usleep($delayMs * 1000);
            }
        }

        return $currentPayment;
    }

    public function activateSubscription(PixelPaymentRequest $payment): PixelSubscription
    {
        $subscription = PixelSubscription::firstOrNew([
            'user_id' => $payment->user_id,
        ]);

        $baseDate = $subscription->exists && $subscription->expires_at && $subscription->expires_at->isFuture()
            ? $subscription->expires_at->copy()
            : now()->toDate();

        $subscription->paid_at = $payment->approved_at ?? now();
        $subscription->expires_at = Carbon::parse($baseDate)->addMonthNoOverflow()->toDateString();
        $subscription->access_enabled = true;
        $subscription->released_by = auth()->id() ?? $subscription->released_by;
        $subscription->released_at = now();

        $notes = trim((string) ($subscription->notes ?? ''));
        $historyLine = sprintf(
            '[%s] Liberado automaticamente via Mercado Pago. Payment ID: %s',
            now()->format('d/m/Y H:i'),
            $payment->mercado_pago_payment_id
        );
        $subscription->notes = trim($notes === '' ? $historyLine : $notes . PHP_EOL . $historyLine);
        $subscription->save();

        return $subscription;
    }

    public function monthlyAmount(): float
    {
        return (float) config('services.mercado_pago.pixel_tracker_amount', 29.90);
    }

    public function webhookSecret(): ?string
    {
        $secret = trim((string) config('services.mercado_pago.webhook_secret', ''));

        return $secret !== '' ? $secret : null;
    }

    public function validateWebhookSignature(string $signatureHeader, string $requestId, string $dataId): bool
    {
        $secret = $this->webhookSecret();

        if (! $secret) {
            return true;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return [$key => $value];
            });

        $ts = $parts->get('ts');
        $hash = $parts->get('v1');

        if (! $ts || ! $hash || ! $requestId || ! $dataId) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    private function accessToken(): string
    {
        $token = trim((string) config('services.mercado_pago.access_token', ''));

        if ($token === '') {
            throw new RuntimeException('Configure MERCADO_PAGO_ACCESS_TOKEN no .env.');
        }

        return $token;
    }

    private function normalizeStatus(array $payload): string
    {
        return (string) ($payload['status'] ?? $payload['status_detail'] ?? 'pending');
    }

    private function statusDetail(array $payload): ?string
    {
        return isset($payload['status_detail']) ? (string) $payload['status_detail'] : null;
    }

    private function pixCode(array $payload): ?string
    {
        return data_get($payload, 'point_of_interaction.transaction_data.qr_code')
            ?: data_get($payload, 'transaction_details.external_resource_url');
    }

    private function qrCodeBase64(array $payload): ?string
    {
        $value = data_get($payload, 'point_of_interaction.transaction_data.qr_code_base64');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function ticketUrl(array $payload): ?string
    {
        $value = data_get($payload, 'point_of_interaction.transaction_data.ticket_url');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function payerEmail(User $user): string
    {
        $email = strtolower(trim((string) $user->email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Cadastre um e-mail real no usuario para gerar a cobranca Pix do Mercado Pago.');
        }

        if (
            str_ends_with($email, '@example.com')
            || str_ends_with($email, '.local')
            || str_contains($email, 'testuser')
        ) {
            throw new RuntimeException('O Mercado Pago em producao exige um e-mail real do pagador. Atualize o e-mail do usuario antes de gerar a cobranca.');
        }

        return $email;
    }

    private function notificationUrl(): string
    {
        $baseUrl = rtrim((string) config('services.mercado_pago.public_url', 'https://comprovante-pix.site'), '/');
        $path = URL::route('billing.pixel.mercado-pago.webhook', [], false);

        return $baseUrl . $path;
    }

    private function expirationDate(): string
    {
        $expiresAt = now()->timezone('America/Sao_Paulo')->addMinutes(10);
        $milliseconds = str_pad((string) intdiv((int) $expiresAt->format('u'), 1000), 3, '0', STR_PAD_LEFT);

        return $expiresAt->format('Y-m-d\TH:i:s') . '.' . $milliseconds . $expiresAt->format('P');
    }

    private function generateQrCodeBase64(?string $content): ?string
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => 8,
            'imageTransparent' => false,
        ])))->render($content);
    }
}
