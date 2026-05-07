<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\PixelTrackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPixelTracks extends ListRecords
{
    protected static string $resource = PixelTrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Gerar novo pixel'),
        ];
    }
}
