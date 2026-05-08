<?php

namespace App\Filament\Resources\PixelAdmin\Pages;

use App\Filament\Resources\PixelAdmin\PixelAdminResource;
use Filament\Resources\Pages\EditRecord;

class EditPixelAdmin extends EditRecord
{
    protected static string $resource = PixelAdminResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['access_enabled'] ?? false)) {
            $data['released_by'] = $this->record->released_by ?? auth()->id();
            $data['released_at'] = $this->record->released_at ?? now();
        }

        return $data;
    }
}
