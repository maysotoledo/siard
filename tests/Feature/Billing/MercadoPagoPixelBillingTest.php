<?php

use App\Models\PixelPaymentRequest;
use App\Models\PixelSubscription;
use App\Models\User;
use App\Services\Billing\MercadoPagoPixelBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.mercado_pago.access_token' => 'TEST-ACCESS-TOKEN',
        'services.mercado_pago.webhook_secret' => 'test-webhook-secret',
        'services.mercado_pago.pixel_tracker_amount' => 19.90,
        'services.mercado_pago.public_url' => 'https://comprovante-pix.site',
    ]);
});

test('cria cobranca pix da mensalidade enviando payload correto ao mercado pago', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-19 10:00:00', 'America/Sao_Paulo'));

    $user = User::factory()->create([
        'name' => 'Investigador SIARD',
        'email' => 'investigador.siard@provedor.com.br',
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/payments' => Http::response([
            'id' => 123456789,
            'status' => 'pending',
            'status_detail' => 'pending_waiting_transfer',
            'transaction_amount' => 19.90,
            'external_reference' => 'pixel-subscription:' . $user->id . ':uuid-teste',
            'date_of_expiration' => '2026-05-19T10:10:00.000-04:00',
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code' => '000201PIX-COPIA-E-COLA',
                    'qr_code_base64' => 'BASE64-QR-CODE',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/123456789/ticket',
                ],
            ],
        ], 201),
    ]);

    $payment = app(MercadoPagoPixelBillingService::class)->createPayment($user);

    Http::assertSent(function ($request) use ($user): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.mercadopago.com/v1/payments'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer TEST-ACCESS-TOKEN')
            && $request->hasHeader('X-Idempotency-Key')
            && $payload['transaction_amount'] === 19.90
            && $payload['description'] === 'Mensalidade Pixel Tracker SACAT'
            && $payload['payment_method_id'] === 'pix'
            && $payload['notification_url'] === 'https://comprovante-pix.site/billing/pixel/mercado-pago/webhook'
            && str_starts_with($payload['external_reference'], 'pixel-subscription:' . $user->id . ':')
            && $payload['payer']['email'] === 'investigador.siard@provedor.com.br'
            && $payload['payer']['first_name'] === 'Investigador SIARD'
            && str_starts_with($payload['date_of_expiration'], '2026-05-19T10:10:00.');
    });

    expect($payment)
        ->mercado_pago_payment_id->toBe('123456789')
        ->status->toBe('pending')
        ->status_detail->toBe('pending_waiting_transfer')
        ->amount->toBe('19.90')
        ->pix_copy_paste->toBe('000201PIX-COPIA-E-COLA')
        ->qr_code_base64->toBe('BASE64-QR-CODE')
        ->ticket_url->toBe('https://www.mercadopago.com.br/payments/123456789/ticket');

    Carbon::setTestNow();
});

test('webhook assinado sincroniza pagamento aprovado e libera mensalidade', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-19 11:00:00'));

    $user = User::factory()->create([
        'email' => 'pagador.siard@provedor.com.br',
    ]);

    PixelPaymentRequest::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'external_reference' => 'pixel-subscription:' . $user->id . ':uuid-teste',
        'mercado_pago_payment_id' => '123456789',
        'amount' => 19.90,
        'status' => 'pending',
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/payments/123456789' => Http::response([
            'id' => 123456789,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => 19.90,
            'external_reference' => 'pixel-subscription:' . $user->id . ':uuid-teste',
            'date_approved' => '2026-05-19T11:00:00.000-04:00',
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code' => '000201PIX-COPIA-E-COLA',
                    'qr_code_base64' => 'BASE64-QR-CODE',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/123456789/ticket',
                ],
            ],
        ], 200),
    ]);

    $requestId = 'request-id-teste';
    $timestamp = '1779202800';
    $manifest = "id:123456789;request-id:{$requestId};ts:{$timestamp};";
    $signature = hash_hmac('sha256', $manifest, 'test-webhook-secret');

    $this->postJson(route('billing.pixel.mercado-pago.webhook'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], [
        'x-request-id' => $requestId,
        'x-signature' => "ts={$timestamp},v1={$signature}",
    ])->assertOk()
        ->assertJson([
            'ok' => true,
            'synced' => true,
        ]);

    $payment = PixelPaymentRequest::query()->first();
    $subscription = PixelSubscription::query()->where('user_id', $user->id)->first();

    expect($payment)
        ->status->toBe('approved')
        ->status_detail->toBe('accredited')
        ->approved_at->not->toBeNull()
        ->and($subscription)->not->toBeNull()
        ->and($subscription->access_enabled)->toBeTrue()
        ->and($subscription->expires_at->toDateString())->toBe('2026-06-19');

    Carbon::setTestNow();
});

test('webhook com assinatura invalida e recusado', function (): void {
    Http::fake();

    $this->postJson(route('billing.pixel.mercado-pago.webhook'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ], [
        'x-request-id' => 'request-id-teste',
        'x-signature' => 'ts=1779202800,v1=assinatura-invalida',
    ])->assertUnauthorized()
        ->assertJson([
            'ok' => false,
            'message' => 'invalid signature',
        ]);

    Http::assertNothingSent();
});

test('webhook com pagamento e sem assinatura e recusado quando segredo esta configurado', function (): void {
    Http::fake();

    $this->postJson(route('billing.pixel.mercado-pago.webhook'), [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ])->assertUnauthorized()
        ->assertJson([
            'ok' => false,
            'message' => 'missing signature',
        ]);

    Http::assertNothingSent();
});
