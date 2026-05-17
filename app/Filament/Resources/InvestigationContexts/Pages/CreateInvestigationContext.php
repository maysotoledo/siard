<?php

namespace App\Filament\Resources\InvestigationContexts\Pages;

use App\Filament\Resources\InvestigationContexts\InvestigationContextResource;
use App\Services\Investigation\BoContextExtractorService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInvestigationContext extends CreateRecord
{
    protected static string $resource = InvestigationContextResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        if (isset($data['arquivo_path']) && $data['arquivo_path']) {
            $data['arquivo_mime'] = $this->detectMime($data['arquivo_path']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record->arquivo_path && $record->texto_extraido === null) {
            try {
                $text = app(BoContextExtractorService::class)->extract($record);

                if ($text !== '') {
                    $record->update(['texto_extraido' => $text]);

                    Notification::make()
                        ->title('Texto extraído com sucesso do BO.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Arquivo salvo. Preencha o campo "Texto do BO" manualmente se necessário.')
                        ->warning()
                        ->send();
                }
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('Arquivo salvo, mas extração automática falhou: ' . $e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'  => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
