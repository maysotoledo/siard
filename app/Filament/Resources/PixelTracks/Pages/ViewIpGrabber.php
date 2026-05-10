<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\IpGrabberResource;
use App\Models\IpGrabber;
use App\Models\IpGrabberAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

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

        // Fotos indexadas por access_uuid — igual ao GPS, cada acesso tem a sua
        $fotosPorUuid = $record->fotos()
            ->get()
            ->keyBy('access_uuid');

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
                'gps_status' => $acesso->gps_status ?: null,
                'gps_error' => $acesso->gps_error ?: null,
                'gps_accuracy' => $acesso->gps_accuracy !== null ? number_format($acesso->gps_accuracy, 2, ',', '.') . ' m' : '-',
                'isp' => $acesso->isp ?: '-',
                'idioma' => $acesso->idioma ?: '-',
                'plataforma' => $acesso->plataforma ?: '-',
                'resolucao' => $acesso->resolucao ?: '-',
                'referer' => $acesso->referer ?: '-',
                'user_agent' => $acesso->user_agent ?: '-',
                // Identidade Digital
                'identidade_nome'     => $acesso->identidade_nome ?: null,
                'identidade_email'    => $acesso->identidade_email ?: null,
                'identidade_telefone' => $acesso->identidade_telefone ?: null,
                'identidade_redes'    => ! empty($acesso->identidade_redes) ? $acesso->identidade_redes : [],
                // Foto — ligada ao access_uuid deste acesso específico
                'foto_url' => isset($fotosPorUuid[$acesso->uuid])
                    ? Storage::disk('public')->url($fotosPorUuid[$acesso->uuid]->path)
                    : null,
            ])
            ->toArray();
    }

    public function getIdentidadeDigital(): array
    {
        /** @var IpGrabber $record */
        $record = $this->record;

        return [
            'nome'     => $record->identidade_nome ?: null,
            'email'    => $record->identidade_email ?: null,
            'telefone' => $record->identidade_telefone ?: null,
            'redes'    => ! empty($record->identidade_redes) ? $record->identidade_redes : [],
        ];
    }
}
