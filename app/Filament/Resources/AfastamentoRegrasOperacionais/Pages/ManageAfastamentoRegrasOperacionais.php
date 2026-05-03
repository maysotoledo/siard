<?php

namespace App\Filament\Resources\AfastamentoRegrasOperacionais\Pages;

use App\Filament\Resources\AfastamentoRegrasOperacionais\AfastamentoRegraOperacionalResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAfastamentoRegrasOperacionais extends ManageRecords
{
    protected static string $resource = AfastamentoRegraOperacionalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
