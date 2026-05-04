<?php

namespace App\Filament\Resources\PlantaoCqhExternos\Pages;

use App\Filament\Resources\PlantaoCqhExternos\PlantaoCqhExternoResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlantaoCqhExternos extends ManageRecords
{
    protected static string $resource = PlantaoCqhExternoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo servidor externo'),
        ];
    }
}
