<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\TodosPixelTracksResource;
use Filament\Resources\Pages\ListRecords;

class ListTodosPixelTracks extends ListRecords
{
    protected static string $resource = TodosPixelTracksResource::class;

    protected static ?string $title = 'Todos os Pixels de Rastreamento';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
