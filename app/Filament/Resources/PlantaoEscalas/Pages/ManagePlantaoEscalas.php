<?php

namespace App\Filament\Resources\PlantaoEscalas\Pages;

use App\Filament\Resources\PlantaoEscalas\PlantaoEscalaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlantaoEscalas extends ManageRecords
{
    protected static string $resource = PlantaoEscalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...PlantaoEscalaResource::gerarEscalaActions(),
            Actions\CreateAction::make(),
        ];
    }
}
