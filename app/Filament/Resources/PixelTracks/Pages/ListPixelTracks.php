<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\PixelTrackResource;
use App\Models\PixelModuleSetting;
use App\Models\PixelPaymentRequest;
use App\Services\Billing\MercadoPagoPixelBillingService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;
use RuntimeException;

class ListPixelTracks extends ListRecords
{
    protected static string $resource = PixelTrackResource::class;

    public ?string $billingError = null;

    public ?int $paymentRequestId = null;

    public bool $shouldOpenQrModal = false;

    public function mount(): void
    {
        parent::mount();

        $this->loadPaymentRequest();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Gerar novo pixel')
                ->visible(fn (): bool => ! $this->shouldShowPaywall()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->shouldShowPaywall()) {
            return parent::content($schema);
        }

        return $schema->components([
            View::make('filament.resources.pixel-tracks.pages.payment-wall')
                ->key('pixel-tracker-payment-wall')
                ->viewData([
                    'billingError' => $this->billingError,
                    'paymentRequest' => $this->getPaymentRequest(),
                    'monthlyAmount' => Number::currency($this->billingService()->monthlyAmount(), 'BRL', 'pt_BR'),
                    'shouldOpenQrModal' => $this->shouldOpenQrModal,
                    'subscription' => auth()->user()?->pixelSubscription,
                ]),
        ]);
    }

    protected function loadPaymentRequest(): void
    {
        $this->billingError = null;

        if (! $this->shouldShowPaywall()) {
            $this->paymentRequestId = null;
            $this->shouldOpenQrModal = false;

            return;
        }

        try {
            $paymentRequest = $this->billingService()->latestPendingPayment(auth()->user());
            $this->paymentRequestId = $paymentRequest?->getKey();
            $this->shouldOpenQrModal = false;
        } catch (RuntimeException $exception) {
            $this->paymentRequestId = null;
            $this->billingError = $exception->getMessage();
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

            $this->paymentRequestId = $paymentRequest->getKey();
            $this->shouldOpenQrModal = (bool) $paymentRequest->qr_code_base64;

            $this->dispatchQrModalIfReady($paymentRequest);

            Notification::make()
                ->title('Cobranca Pix gerada')
                ->body('O QR Code ficou disponivel por 10 minutos.')
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            $this->paymentRequestId = null;
            $this->billingError = $exception->getMessage();
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
                ->body('O acesso ao Pixel Tracker foi liberado automaticamente.')
                ->success()
                ->send();

            $this->loadPaymentRequest();

            return;
        }

        $this->paymentRequestId = $updatedPayment?->getKey() ?? $paymentRequest->getKey();
        $this->shouldOpenQrModal = (bool) ($updatedPayment?->qr_code_base64);

        if ($updatedPayment) {
            $this->dispatchQrModalIfReady($updatedPayment);
        }
    }

    public function openQrModal(): void
    {
        $this->shouldOpenQrModal = true;
        $paymentRequest = $this->getPaymentRequest();
        if ($paymentRequest) {
            $this->dispatchQrModalIfReady($paymentRequest);
        }
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

        $this->dispatch('pixel-open-qr-modal',
            qrCodeSrc: $qrCodeSrc,
            pixCopyPaste: $paymentRequest->pix_copy_paste ?? '',
        );
    }

    public function closeQrModal(): void
    {
        $this->shouldOpenQrModal = false;
    }

    public function regeneratePayment(): void
    {
        $this->startPayment();
    }

    protected function shouldShowPaywall(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return false;
        }

        if (! PixelModuleSetting::isPaymentEnabled()) {
            return false;
        }

        return ! $user->hasActivePixelSubscription();
    }

    protected function getPaymentRequest(): ?PixelPaymentRequest
    {
        if (! $this->paymentRequestId) {
            return null;
        }

        return PixelPaymentRequest::query()->find($this->paymentRequestId);
    }

    protected function billingService(): MercadoPagoPixelBillingService
    {
        return app(MercadoPagoPixelBillingService::class);
    }
}
