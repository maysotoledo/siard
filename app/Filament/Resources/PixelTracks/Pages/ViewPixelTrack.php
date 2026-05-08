<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\PixelTrackResource;
use App\Models\PixelTrack;
use App\Models\PixelTrackAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPixelTrack extends ViewRecord
{
    protected static string $resource = PixelTrackResource::class;

    protected string $view = 'filament.resources.pixel-tracks.pages.view-pixel-track';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Excluir'),
        ];
    }

    public function getAcessos(): array
    {
        /** @var PixelTrack $record */
        $record = $this->record;

        return $record->acessos()
            ->latest('accessed_at')
            ->get()
            ->map(fn (PixelTrackAccess $acesso): array => [
                'accessed_at' => $acesso->accessed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '-',
                'endpoint' => match ($acesso->endpoint) {
                    'gif' => 'GIF',
                    'historico' => 'Histórico anterior',
                    default => 'Página',
                },
                'ip' => $acesso->ip ?: '-',
                'porta' => $acesso->porta ?: '-',
                'ip_local' => $acesso->ip_local ?: '-',
                'gmt' => $acesso->gmt ?: '-',
                'localizacao' => implode(', ', array_filter([
                    $acesso->cidade,
                    $acesso->regiao,
                    $acesso->pais,
                ])) ?: '-',
                'gps' => $acesso->gps_latitude !== null
                    ? "{$acesso->gps_latitude}, {$acesso->gps_longitude}"
                    : '-',
                'gps_accuracy' => $acesso->gps_accuracy !== null
                    ? number_format($acesso->gps_accuracy, 2, ',', '.').' m'
                    : '-',
                'isp' => $acesso->isp ?: '-',
                'idioma' => $acesso->idioma ?: '-',
                'plataforma' => $acesso->plataforma ?: '-',
                'resolucao' => $acesso->resolucao ?: '-',
                'referer' => $acesso->referer ?: '-',
                'user_agent' => $acesso->user_agent ?: '-',
            ])
            ->toArray();
    }
}
