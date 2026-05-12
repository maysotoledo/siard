<?php

namespace App\Services\Pixel;

use Illuminate\Support\Facades\Storage;

class IntimacaoPreviewService
{
    public function gerarPreview(string $pdfPath, string $token): ?string
    {
        $fullPath = Storage::disk('public')->path($pdfPath);

        if (! file_exists($fullPath)) {
            return null;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(96, 96);
            $imagick->readImage($fullPath . '[0]'); // primeira página
            $imagick->setImageFormat('jpeg');

            // Remove canal alfa (PDF pode ter fundo transparente)
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->flattenImages();

            $width  = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            // Metade superior
            $imagick->cropImage($width, (int) ($height / 2), 0, 0);

            // Redimensiona para largura máxima de 800px mantendo proporção
            if ($width > 800) {
                $imagick->resizeImage(800, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            // Compressão agressiva para preview
            $imagick->setImageCompressionQuality(60);
            $imagick->stripImage(); // remove metadados EXIF/ICC

            $destPath = "pixel-og/{$token}-intimacao-preview.jpg";
            Storage::disk('public')->put($destPath, $imagick->getImageBlob());
            $imagick->clear();

            return $destPath;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
