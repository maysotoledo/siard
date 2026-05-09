<?php

namespace App\Filament\Widgets;

use App\Models\PixelModuleSetting;
use App\Models\PixelPaymentRequest;
use App\Services\Billing\MercadoPagoPixelBillingService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use RuntimeException;

class SubscriptionStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.subscription-status-widget';

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public ?string $billingError = null;

    public ?int $paymentRequestId = null;

    public bool $shouldOpenQrModal = false;

    public function mount(): void
    {
        $this->loadPaymentRequest();
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'billingError'      => $this->billingError,
            'paymentRequest'    => $this->getPaymentRequest(),
            'monthlyAmount'     => Number::currency($this->billingService()->monthlyAmount(), 'BRL', 'pt_BR'),
            'shouldOpenQrModal' => $this->shouldOpenQrModal,
            'subscription'      => $user?->pixelSubscription,
            'shouldShowPaywall' => $this->shouldShowPaywall(),
        ];
    }

    // ─── Payment wall logic ───────────────────────────────────────────────────

    protected function shouldShowPaywall(): bool
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin') || ! PixelModuleSetting::isPaymentEnabled()) {
            return false;
        }

        return ! $user->hasActivePixelSubscription();
    }

    protected function loadPaymentRequest(): void
    {
        $this->billingError = null;

        if (! $this->shouldShowPaywall()) {
            $this->paymentRequestId  = null;
            $this->shouldOpenQrModal = false;

            return;
        }

        try {
            $paymentRequest         = $this->billingService()->latestPendingPayment(auth()->user());
            $this->paymentRequestId = $paymentRequest?->getKey();
            $this->shouldOpenQrModal = false;
        } catch (RuntimeException $exception) {
            $this->paymentRequestId  = null;
            $this->billingError      = $exception->getMessage();
            $this->shouldOpenQrModal = false;
        }
    }

    public function startPayment(): void
    {
        $this->billingError = null;

        try {
            $paymentRequest = $this->billingService()->createPayment(auth()->user());

            if ($paymentRequest->mercado_pago_payment_id) {
                $paymentRequest = $this->billingService()->hydratePaymentDetails($paymentRequest);
            }

            $this->paymentRequestId  = $paymentRequest->getKey();
            $this->shouldOpenQrModal = (bool) $paymentRequest->qr_code_base64;
            $this->dispatchQrModalIfReady($paymentRequest);

            Notification::make()
                ->title('Cobrança Pix gerada')
                ->body('O QR Code ficou disponível por 10 minutos.')
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            $this->paymentRequestId  = null;
            $this->billingError      = $exception->getMessage();
            $this->shouldOpenQrModal = false;
        }
    }

    public function refreshPaymentStatus(): void
    {
        if (! $this->shouldShowPaywall()) {
            return;
        }

        $paymentRequest = $this->getPaymentRequest();

        if (! $paymentRequest?->mercado_pago_payment_id) {
            return;
        }

        try {
            $updatedPayment = $this->billingService()->syncPaymentByMercadoPagoId($paymentRequest->mercado_pago_payment_id);
        } catch (RuntimeException $exception) {
            $this->billingError = $exception->getMessage();

            return;
        }

        if ($updatedPayment?->status === 'approved') {
            Notification::make()
                ->title('Pagamento confirmado')
                ->body('O acesso ao sistema foi liberado automaticamente.')
                ->success()
                ->send();

            $this->loadPaymentRequest();

            return;
        }

        $this->paymentRequestId  = $updatedPayment?->getKey() ?? $paymentRequest->getKey();
        $this->shouldOpenQrModal = (bool) ($updatedPayment?->qr_code_base64);

        if ($updatedPayment) {
            $this->dispatchQrModalIfReady($updatedPayment);
        }
    }

    public function openQrModal(): void
    {
        $this->shouldOpenQrModal = true;

        if ($paymentRequest = $this->getPaymentRequest()) {
            $this->dispatchQrModalIfReady($paymentRequest);
        }
    }

    public function closeQrModal(): void
    {
        $this->shouldOpenQrModal = false;
    }

    public function regeneratePayment(): void
    {
        $this->startPayment();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function getPaymentRequest(): ?PixelPaymentRequest
    {
        return $this->paymentRequestId
            ? PixelPaymentRequest::query()->find($this->paymentRequestId)
            : null;
    }

    protected function billingService(): MercadoPagoPixelBillingService
    {
        return app(MercadoPagoPixelBillingService::class);
    }

    private function dispatchQrModalIfReady(PixelPaymentRequest $paymentRequest): void
    {
        $qrCodeBase64 = $paymentRequest->qr_code_base64;

        if (! filled($qrCodeBase64)) {
            return;
        }

        $qrCodeSrc = str_starts_with($qrCodeBase64, 'data:image')
            ? $qrCodeBase64
            : 'data:image/png;base64,' . $qrCodeBase64;

        $this->dispatch('pixel-open-qr-modal', qrCodeSrc: $qrCodeSrc, pixCopyPaste: $paymentRequest->pix_copy_paste ?? '');
    }
}
