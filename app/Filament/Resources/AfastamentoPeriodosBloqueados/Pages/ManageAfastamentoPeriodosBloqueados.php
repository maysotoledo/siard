<?php

namespace App\Filament\Resources\AfastamentoPeriodosBloqueados\Pages;

use App\Filament\Resources\AfastamentoPeriodosBloqueados\AfastamentoPeriodoBloqueadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAfastamentoPeriodosBloqueados extends ManageRecords
{
    protected static string $resource = AfastamentoPeriodoBloqueadoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
