<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        if (! auth()->user()?->hasRole('super_admin') && $this->record->hasRole('super_admin')) {
            $this->record->removeRole('super_admin');
        }
    }
}
