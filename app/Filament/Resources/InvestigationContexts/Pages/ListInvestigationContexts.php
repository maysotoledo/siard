<?php

namespace App\Filament\Resources\InvestigationContexts\Pages;

use App\Filament\Resources\InvestigationContexts\InvestigationContextResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvestigationContexts extends ListRecords
{
    protected static string $resource = InvestigationContextResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
