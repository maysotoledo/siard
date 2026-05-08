<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\PixelTrackResource;
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

    public function mount(): void
    {
        parent::mount();

        $this->preparePaymentRequest();
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
                    'subscription' => auth()->user()?->pixelSubscription,
                ]),
        ]);
    }

    protected function preparePaymentRequest(bool $forceNew = false): void
    {
        $this->billingError = null;

        if (! $this->shouldShowPaywall()) {
            $this->paymentRequestId = null;

            return;
        }

        try {
            $service = $this->billingService();
            $paymentRequest = $forceNew
                ? $service->createPayment(auth()->user())
                : $service->ensurePendingPayment(auth()->user());

            $this->paymentRequestId = $paymentRequest->getKey();
        } catch (RuntimeException $exception) {
            $this->paymentRequestId = null;
            $this->billingError = $exception->getMessage();
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

            $this->preparePaymentRequest();

            return;
        }

        $this->paymentRequestId = $updatedPayment?->getKey() ?? $paymentRequest->getKey();
    }

    public function regeneratePayment(): void
    {
        $this->preparePaymentRequest(forceNew: true);

        if ($this->paymentRequestId) {
            Notification::make()
                ->title('Novo QR Code gerado')
                ->body('Use o novo Pix do Mercado Pago para liberar o acesso.')
                ->success()
                ->send();
        }
    }

    protected function shouldShowPaywall(): bool
    {
        return ! (auth()->user()?->hasActivePixelSubscription() ?? false);
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
