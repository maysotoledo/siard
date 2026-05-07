<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\PixelTrackResource;
use App\Models\PixelTrack;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePixelTrack extends CreateRecord
{
    protected static string $resource = PixelTrackResource::class;

    protected static ?string $title = 'Gerar Pixel de Rastreamento';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token']      = Str::random(40);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var PixelTrack $pixel */
        $pixel  = $this->record;
        $url    = route('pixel.track', $pixel->token);
        $imgTag = "<img src=\"{$url}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\">";

        Notification::make()
            ->title('Pixel criado com sucesso!')
            ->body("URL: {$url}\n\nTag HTML: {$imgTag}")
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
}
