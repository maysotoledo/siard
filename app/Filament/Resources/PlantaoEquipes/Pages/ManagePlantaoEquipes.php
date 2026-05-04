<?php

namespace App\Filament\Resources\PlantaoEquipes\Pages;

use App\Filament\Resources\PlantaoEquipes\PlantaoEquipeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlantaoEquipes extends ManageRecords
{
    protected static string $resource = PlantaoEquipeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
