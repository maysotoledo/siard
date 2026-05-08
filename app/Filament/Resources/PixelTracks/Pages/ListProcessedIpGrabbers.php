<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\ProcessedIpGrabbersResource;
use Filament\Resources\Pages\ListRecords;

class ListProcessedIpGrabbers extends ListRecords
{
    protected static string $resource = ProcessedIpGrabbersResource::class;

    protected static ?string $title = 'IP Grabber Processados';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
