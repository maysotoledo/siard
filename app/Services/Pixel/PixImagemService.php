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
        $baseImage = public_path('images/pix-img-gerar.png');

        if (! file_exists($baseImage)) {
            return null;
        }

        $fontBold    = public_path('fonts/Arial-Bold.ttf');
        $fontRegular = public_path('fonts/Arial.ttf');

        $temFontes = file_exists($fontBold) && file_exists($fontRegular);

        $dataHora = strtoupper(
            Carbon::now('America/Cuiaba')
                ->locale('pt_BR')
                ->translatedFormat('d M Y H:i:s')
        );

        $manager = new ImageManager(new Driver());
        $img = $manager->decodePath($baseImage);

        // Nome do alvo
        $img->text($nome, 275, 470, function (FontFactory $font) use ($fontBold, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontBold);
            }
            $font->size(38);
            $font->color('#333333');
        });

        // Data e hora
        $img->text($dataHora, 330, 540, function (FontFactory $font) use ($fontRegular, $temFontes): void {
            if ($temFontes) {
                $font->filename($fontRegular);
            }
            $font->size(34);
            $font->color('#333333');
        });

        $path = "pixel-og/{$token}-pix-gerado.jpg";

        Storage::disk('public')->put($path, (string) $img->encode(new JpegEncoder(quality: 82, progressive: true, strip: true)));

        return $path;
    }
}
