<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\PixelTrackResource;
use App\Models\PixelTrack;
use App\Services\Pixel\NewsPreviewMetadataService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreatePixelTrack extends CreateRecord
{
    protected static string $resource = PixelTrackResource::class;

    protected static ?string $title = 'Gerar Pixel de Rastreamento';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token']      = Str::random(40);
        $data['created_by'] = auth()->id();

        if (($data['preview_tipo'] ?? null) !== 'mensagem') {
            $data['tracking_domain'] = null;
        }

        if (($data['preview_tipo'] ?? null) === 'pix_bradesco') {
            $data['og_titulo']   = 'Comprovante PIX Bradesco';
            $data['og_descricao'] = 'Confirme sua chave pix clicando aqui.';
            $data['mensagem']    = 'Este documento não está mais disponível.';

            // Copia a imagem template para um path exclusivo deste pixel
            $this->preencherImagemPixBradesco($data);
        }

        if (($data['preview_tipo'] ?? null) === 'noticia' && filled($data['noticia_url'] ?? null)) {
            $metadata = app(NewsPreviewMetadataService::class)->fetch((string) $data['noticia_url']);

            $data['og_titulo'] = filled($data['og_titulo'] ?? null)
                ? $data['og_titulo']
                : ($metadata['og_titulo'] ?? null);

            $data['og_descricao'] = filled($data['og_descricao'] ?? null)
                ? $data['og_descricao']
                : ($metadata['og_descricao'] ?? null);

            $data['og_imagem'] = filled($data['og_imagem'] ?? null)
                ? $data['og_imagem']
                : ($metadata['og_imagem'] ?? null);

            if (empty($data['og_imagem_upload']) && filled($metadata['og_imagem'] ?? null)) {
                $data['og_imagem_upload'] = app(NewsPreviewMetadataService::class)
                    ->storeImage((string) $metadata['og_imagem'], (string) $data['token']);
            }

            $data['mensagem'] = 'Redirecionando para a notícia...';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var PixelTrack $pixel */
        $pixel  = $this->record;
        $url    = $pixel->trackingUrl();
        $previewUrl = $pixel->trackingUrlWithQuery(['preview' => 1]);
        $gifUrl = $pixel->trackingAssetUrl(route('pixel.gif', $pixel->token, false));
        $imgTag = htmlspecialchars("<img src=\"{$gifUrl}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\">", ENT_QUOTES, 'UTF-8');
        $destino = $pixel->preview_tipo === 'noticia' && $pixel->noticia_url
            ? "\n\nDestino da notícia: {$pixel->noticia_url}"
            : '';

        Notification::make()
            ->title('Pixel criado com sucesso!')
            ->body("URL real: {$url}\nURL de teste sem captura: {$previewUrl}{$destino}\n\nTag HTML segura: {$imgTag}")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    private function preencherImagemPixBradesco(array &$data): void
    {
        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            $template = "pixel-og/templates/pix-bradesco.{$extension}";

            if (! Storage::disk('public')->exists($template)) {
                continue;
            }

            $dest = 'pixel-og/' . $data['token'] . "-bradesco.{$extension}";
            Storage::disk('public')->copy($template, $dest);
            $data['og_imagem_upload'] = $dest;

            return;
        }
    }
}
