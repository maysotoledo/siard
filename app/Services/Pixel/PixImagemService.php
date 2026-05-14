<?php

namespace App\Services\Pixel;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class PixImagemService
{
    /**
     * Gera uma imagem PIX com o nome do alvo e data/hora escritos,
     * salva em storage/app/public/pixel-og/{token}-pix-gerado.png
     * e retorna o path relativo (para og_imagem_upload).
     */
    public function gerar(string $nome, string $token): ?string
    {
        $conteudo = $this->renderJpeg($nome);

        if ($conteudo === null) {
            return null;
        }

        $path = "pixel-og/{$token}-pix-gerado.jpg";

        Storage::disk('public')->put($path, $conteudo);

        return $path;
    }

    public function gerarDataUri(string $nome): ?string
    {
        $conteudo = $this->renderJpeg($nome);

        if ($conteudo === null) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($conteudo);
    }

    private function renderJpeg(string $nome): ?string
    {
        $baseImage = public_path('images/pix-img-gerar.png');

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

        $dataHora = strtoupper(
            Carbon::now('America/Cuiaba')
                ->locale('pt_BR')
                ->translatedFormat('d M Y H:i:s')
        );

        $manager = new ImageManager(new Driver());
        $img = $manager->decodePath($baseImage);

        // Nome do alvo
        $img->text($nome, 650, 620, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(72);
            $font->color('#333333');
            $font->align('center', 'center');
        });

        // Data e hora
        $img->text($dataHora, 650, 700, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(39);
            $font->color('#333333');
            $font->align('center', 'center');
        });

        return (string) $img->encode(new JpegEncoder(quality: 82, progressive: true, strip: true));
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
