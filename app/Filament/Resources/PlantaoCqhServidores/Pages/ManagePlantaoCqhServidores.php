<?php

namespace App\Filament\Resources\PlantaoCqhServidores\Pages;

use App\Filament\Resources\PlantaoCqhServidores\PlantaoCqhServidorResource;
use App\Filament\Widgets\PlantaoCqhExternosWidget;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlantaoCqhServidores extends ManageRecords
{
    protected static string $resource = PlantaoCqhServidorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Adicionar servidor Confresa'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PlantaoCqhExternosWidget::class,
        ];
    }

}
