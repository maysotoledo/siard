<?php

namespace App\Filament\Resources\EmailTrackers\Pages;

use App\Filament\Resources\EmailTrackers\EmailTrackerResource;
use App\Models\IpGrabber;
use App\Services\EmailTrackers\EmailTrackerMailer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class CreateEmailTracker extends CreateRecord
{
    protected static string $resource = EmailTrackerResource::class;

    protected static ?string $title = 'Enviar E-mail com Tracker';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Str::random(40);
        $data['created_by'] = auth()->id();
        $data['tracking_channel'] = 'email';
        $data['preview_tipo'] = 'mensagem';
        $data['mensagem'] = IpGrabber::DEFAULT_CLICK_MESSAGE;
        $data['capture_gps'] = false;
        $data['tracking_domain'] = 'agenciadanoticia.online';

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var IpGrabber $tracker */
        $tracker = static::getModel()::query()->create($data);

        try {
            app(EmailTrackerMailer::class)->send($tracker);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Não foi possível enviar o e-mail com tracker: ' . $exception->getMessage(), previous: $exception);
        }

        return $tracker;
    }

    protected function afterCreate(): void
    {
        /** @var IpGrabber $tracker */
        $tracker = $this->record;

        Notification::make()
            ->title('E-mail enviado com tracker')
            ->body("Destino: {$tracker->target_email}\nURL do pixel: {$tracker->emailTrackingUrl()}")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Enviar e-mail');
    }
}
