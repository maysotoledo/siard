<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\IpGrabberResource;
use App\Models\IpGrabber;
use App\Models\IpGrabberAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIpGrabber extends ViewRecord
{
    protected static string $resource = IpGrabberResource::class;

    protected string $view = 'filament.resources.pixel-tracks.pages.view-ip-grabber';

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('Excluir')];
    }

    public function getAcessos(): array
    {
        /** @var IpGrabber $record */
        $record = $this->record;

        return $record->acessos()
            ->latest('accessed_at')
            ->get()
            ->map(fn (IpGrabberAccess $acesso): array => [
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
                'localizacao' => implode(', ', array_filter([$acesso->cidade, $acesso->regiao, $acesso->pais])) ?: '-',
                'gps' => $acesso->gps_latitude !== null ? "{$acesso->gps_latitude}, {$acesso->gps_longitude}" : '-',
                'gps_url' => $acesso->gps_latitude !== null ? "https://www.google.com/maps?q={$acesso->gps_latitude},{$acesso->gps_longitude}" : null,
                'gps_accuracy' => $acesso->gps_accuracy !== null ? number_format($acesso->gps_accuracy, 2, ',', '.') . ' m' : '-',
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
