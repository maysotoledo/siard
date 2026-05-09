<?php

namespace App\Filament\Resources\EmailTrackers\Pages;

use App\Filament\Resources\EmailTrackers\EmailTrackerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailTrackers extends ListRecords
{
    protected static string $resource = EmailTrackerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Enviar e-mail com tracker'),
        ];
    }
}
