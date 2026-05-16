<?php

namespace App\Services\Pixel;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class PixMercadoPagoImagemService
{
    public function gerar(string $nome, string $valor, string $token): ?string
    {
        $conteudo = $this->renderJpeg($nome, $valor);

        if ($conteudo === null) {
            return null;
        }

        $path = "pixel-og/{$token}-pix-mp.jpg";
        Storage::disk('public')->put($path, $conteudo);

        return $path;
    }

    public function gerarDataUri(string $nome, string $valor): ?string
    {
        $conteudo = $this->renderJpeg($nome, $valor);

        if ($conteudo === null) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($conteudo);
    }

    private function renderJpeg(string $nome, string $valor): ?string
    {
        $baseImage = public_path('images/mercadopago.png');

        if (! file_exists($baseImage)) {
            return null;
        }

        $fontBold = $this->fontPath([
            public_path('fonts/Arial-Bold.ttf'),
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
        ]);
        $fontRegular = $this->fontPath([
            public_path('fonts/Arial.ttf'),
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
        ]);

        $temFontes = $fontBold !== null && $fontRegular !== null;

        $now = Carbon::now('America/Cuiaba')->locale('pt_BR');
        $dataHora = $now->translatedFormat('d/F/Y') . ' às ' . $now->format('H:i:s') . '.';
        $valorFormatado = 'R$ ' . $valor;

        $manager = new ImageManager(new Driver());
        $img = $manager->decodePath($baseImage);

        // Data e hora — abaixo de "Comprovante de Pix"
        $img->text($dataHora, 100, 375, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(34);
            $font->color('#64748b');
            $font->align('left', 'center');
        });

        // Valor — grande, preto, na seção central
        $img->text($valorFormatado, 60, 585, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(90);
            $font->color('#111111');
            $font->align('left', 'center');
        });

        // Cobre "De" com branco e escreve "Para"
        $img->drawRectangle(function ($rect): void {
            $rect->at(95, 855);
            $rect->size(130, 55);
            $rect->background('#ffffff');
        });
        $img->text('Para', 100, 883, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(34);
            $font->color('#64748b');
            $font->align('left', 'center');
        });

        // Nome do alvo — abaixo de "Para"
        $img->text($nome, 100, 960, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(52);
            $font->color('#111111');
            $font->align('left', 'center');
        });

        return (string) $img->encode(new JpegEncoder(quality: 85, progressive: true, strip: true));
    }

    /** @param array<int, string> $paths */
    private function fontPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
