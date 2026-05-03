<?php

namespace App\Filament\Resources\AfastamentoRegras\Pages;

use App\Filament\Resources\AfastamentoRegras\AfastamentoRegraResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAfastamentoRegras extends ManageRecords
{
    protected static string $resource = AfastamentoRegraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
