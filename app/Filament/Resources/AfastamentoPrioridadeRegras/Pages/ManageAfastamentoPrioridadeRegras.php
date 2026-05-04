<?php

namespace App\Filament\Resources\AfastamentoPrioridadeRegras\Pages;

use App\Filament\Resources\AfastamentoPrioridadeRegras\AfastamentoPrioridadeRegraResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAfastamentoPrioridadeRegras extends ManageRecords
{
    protected static string $resource = AfastamentoPrioridadeRegraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
