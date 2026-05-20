<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\MercadoPagoPixelBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoPixelWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoPixelBillingService $billingService): JsonResponse
    {
        $paymentId = (string) ($request->query('data.id')
            ?? $request->input('data.id')
            ?? $request->query('id')
            ?? '');

        $signature = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        $signatureDataId = (string) ($request->query('data.id') ?? $paymentId);

        if ($paymentId !== '' && $billingService->webhookSecret() && $signature === '') {
            return response()->json([
                'ok' => false,
                'message' => 'missing signature',
            ], 401);
        }

        if (
            $signature !== ''
            && ! $billingService->validateWebhookSignature($signature, $requestId, $signatureDataId)
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'invalid signature',
            ], 401);
        }

        if ($paymentId === '') {
            return response()->json([
                'ok' => true,
                'message' => 'notification received without payment id',
            ]);
        }

        $paymentRequest = $billingService->syncPaymentByMercadoPagoId($paymentId);

        return response()->json([
            'ok' => true,
            'synced' => $paymentRequest !== null,
        ]);
    }
}
