<?php

namespace App\Filament\Resources\PixelAdmin\Pages;

use App\Filament\Resources\PixelAdmin\PixelAdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePixelAdmin extends CreateRecord
{
    protected static string $resource = PixelAdminResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['access_enabled'] ?? false) && empty($data['released_by'])) {
            $data['released_by'] = auth()->id();
            $data['released_at'] = now();
        }

        return $data;
    }
}
