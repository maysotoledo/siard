<?php

namespace App\Services\Pixel;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class PixNubankImagemService
{
    private const MESES = [
        1 => 'JAN', 2 => 'FEV', 3 => 'MAR', 4 => 'ABR',
        5 => 'MAI', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
        9 => 'SET', 10 => 'OUT', 11 => 'NOV', 12 => 'DEZ',
    ];

    public function gerar(string $valor, string $token): ?string
    {
        $conteudo = $this->renderJpeg($valor);

        if ($conteudo === null) {
            return null;
        }

        $path = "pixel-og/{$token}-pix-nu.jpg";
        Storage::disk('public')->put($path, $conteudo);

        return $path;
    }

    public function gerarDataUri(string $valor): ?string
    {
        $conteudo = $this->renderJpeg($valor);

        if ($conteudo === null) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($conteudo);
    }

    private function renderJpeg(string $valor): ?string
    {
        $baseImage = public_path('images/nubank.png');

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

        $now = Carbon::now('America/Cuiaba');
        $dataHora = $now->format('d') . ' ' . self::MESES[$now->month] . ' ' . $now->format('Y') . ' - ' . $now->format('H:i:s');
        $valorFormatado = 'R$ ' . $valor;

        $manager = new ImageManager(new Driver());
        $img = $manager->decodePath($baseImage);

        // Data e hora — entre o título e a linha "Valor"
        $img->text($dataHora, 48, 615, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(38);
            $font->color('#333333');
            $font->align('left', 'center');
        });

        // Valor — alinhado à direita, na mesma linha de "Valor"
        $img->text($valorFormatado, 1150, 725, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(40);
            $font->color('#111111');
            $font->align('right', 'center');
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
