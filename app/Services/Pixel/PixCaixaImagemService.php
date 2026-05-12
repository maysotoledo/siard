<?php

namespace App\Services\Pixel;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class PixCaixaImagemService
{
    public function gerar(string $valor, string $token): ?string
    {
        $baseImage = public_path('images/comprovante-pix-caixa.png');

        if (! file_exists($baseImage)) {
            return null;
        }

        $fontBold    = $this->fontPath([
            public_path('fonts/Arial-Bold.ttf'),
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
        ]);
        $fontRegular = $this->fontPath([
            public_path('fonts/Arial.ttf'),
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
        ]);

        $temFontes = $fontBold !== null && $fontRegular !== null;

        $dataHora = Carbon::now('America/Cuiaba')
            ->format('d/m/Y, H:i:s');

        $valorFormatado = 'R$ ' . $valor;

        $manager = new ImageManager(new Driver());
        $img     = $manager->decodePath($baseImage);

        // Data e hora — abaixo de "Pix enviado" (y≈612), alinhado à esquerda
        $img->text($dataHora, 80, 685, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(42);
            $font->color('#666666');
            $font->align('left', 'center');
        });

        // Valor — linha "Valor" entre y=760-820, alinhado à direita
        $img->text($valorFormatado, 1220, 789, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(50);
            $font->color('#1a1a1a');
            $font->align('right', 'center');
        });

        $path = "pixel-og/{$token}-pix-caixa.jpg";

        Storage::disk('public')->put(
            $path,
            (string) $img->encode(new JpegEncoder(quality: 85, progressive: true, strip: true))
        );

        return $path;
    }

    /**
     * @param array<int, string> $paths
     */
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
