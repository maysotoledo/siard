<?php

namespace App\Filament\Resources\PixelAdmin\Pages;

use App\Filament\Resources\PixelAdmin\PixelAdminResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePixelAdmin extends CreateRecord
{
    protected static string $resource = PixelAdminResource::class;

    public function getTitle(): string
    {
        return 'Liberar Acesso Mensal';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Liberar Acesso');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['access_enabled'] ?? false) && empty($data['released_by'])) {
            $data['released_by'] = auth()->id();
            $data['released_at'] = now();
        }

        return $data;
    }
}
