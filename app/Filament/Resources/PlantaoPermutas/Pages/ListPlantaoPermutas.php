<?php

namespace App\Filament\Resources\PlantaoPermutas\Pages;

use App\Filament\Resources\PlantaoPermutas\PlantaoPermutaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlantaoPermutas extends ListRecords
{
    protected static string $resource = PlantaoPermutaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('permutar')
                ->label('Permutar')
                ->icon('heroicon-o-arrows-right-left')
                ->modalSubmitActionLabel('Permutar')
                ->schema(PlantaoPermutaResource::permutaSchema())
                ->action(function (array $data): void {
                    app(\App\Services\Plantao\PlantaoPermutaService::class)->permutarEntreDias(
                        (int) $data['escala_origem_id'],
                        (int) $data['escala_destino_id'],
                        $data['servidor_original_id'],
                        $data['servidor_substituto_id'],
                        (string) $data['tipo_funcao'],
                        $data['motivo'] ?? null,
                    );
                }),
        ];
    }
}
