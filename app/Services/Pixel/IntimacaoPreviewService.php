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
            $blob = $this->viaImagick($fullPath) ?? $this->viaPdftoppm($fullPath);

            if (! $blob) {
                return null;
            }

            $destPath = "pixel-og/{$token}-intimacao-preview.jpg";
            Storage::disk('public')->put($destPath, $blob);

            return $destPath;
        } catch (\Throwable) {
            return null;
        }
    }

    private function viaImagick(string $fullPath): ?string
    {
        try {
            $imagick = new \Imagick();
            $imagick->setResolution(96, 96);
            $imagick->readImage($fullPath . '[0]');
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->flattenImages();

            $width  = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $imagick->cropImage($width, (int) ($height / 2), 0, 0);

            if ($width > 800) {
                $imagick->resizeImage(800, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(60);
            $imagick->stripImage();

            $blob = $imagick->getImageBlob();
            $imagick->clear();

            return $blob ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function viaPdftoppm(string $fullPath): ?string
    {
        $pdftoppm = trim((string) shell_exec('which pdftoppm 2>/dev/null'));

        if (! $pdftoppm) {
            return null;
        }

        $tmp = sys_get_temp_dir() . '/intim-' . uniqid();
        $cmd = escapeshellarg($pdftoppm)
             . ' -jpeg -r 96 -f 1 -l 1 '
             . escapeshellarg($fullPath) . ' '
             . escapeshellarg($tmp)
             . ' 2>/dev/null';

        exec($cmd);

        $generated = glob($tmp . '*.jpg')[0] ?? null;

        if (! $generated || ! file_exists($generated)) {
            return null;
        }

        try {
            $imagick = new \Imagick($generated);
            $width   = $imagick->getImageWidth();
            $height  = $imagick->getImageHeight();
            $imagick->cropImage($width, (int) ($height / 2), 0, 0);

            if ($width > 800) {
                $imagick->resizeImage(800, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            $imagick->setImageCompressionQuality(60);
            $imagick->stripImage();

            $blob = $imagick->getImageBlob();
            $imagick->clear();

            return $blob ?: null;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($generated);
        }
    }
}
