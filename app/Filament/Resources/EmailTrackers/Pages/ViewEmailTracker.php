<?php

namespace App\Filament\Resources\EmailTrackers\Pages;

use App\Filament\Resources\EmailTrackers\EmailTrackerResource;
use App\Models\IpGrabber;
use App\Models\IpGrabberAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailTracker extends ViewRecord
{
    protected static string $resource = EmailTrackerResource::class;

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
                    'gif' => 'GIF / E-mail',
                    'historico' => 'Histórico anterior',
                    default => 'Página',
                },
                'ip' => $acesso->ip ?: '-',
                'porta' => $acesso->porta ?: '-',
                'ip_local' => $acesso->ip_local ?: '-',
                'gmt' => $acesso->gmt ?: '-',
                'localizacao' => implode(', ', array_filter([$acesso->cidade, $acesso->regiao, $acesso->pais])) ?: '-',
                'gps' => '-',
                'gps_url' => null,
                'gps_accuracy' => '-',
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
