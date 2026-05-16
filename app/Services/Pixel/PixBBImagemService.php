<?php

namespace App\Services\Pixel;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class PixBBImagemService
{
    public function gerar(string $nome, string $valor, string $token): ?string
    {
        $conteudo = $this->renderJpeg($nome, $valor);

        if ($conteudo === null) {
            return null;
        }

        $path = "pixel-og/{$token}-pix-bb.jpg";
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
        $baseImage = public_path('images/bb.png');

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

        $dataHora = Carbon::now('America/Cuiaba')->format('d/m/Y \à\s H:i:s');
        $valorFormatado = 'R$ ' . $valor;

        $manager = new ImageManager(new Driver());
        $img = $manager->decodePath($baseImage);

        // Valor — grande, azul BB (espaço: y=360–500 antes do "Pix Enviado")
        $img->text($valorFormatado, 60, 398, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(76);
            $font->color('#1a56db');
            $font->align('left', 'center');
        });

        // Data e hora — logo abaixo do valor, acima do "Pix Enviado"
        $img->text($dataHora, 60, 457, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(32);
            $font->color('#64748b');
            $font->align('left', 'center');
        });

        // Nome do alvo — abaixo de "Recebedor" (y≈720)
        $img->text($nome, 60, 820, function (FontFactory $font) use ($fontBold, $temFontes): void {
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
