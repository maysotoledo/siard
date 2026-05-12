<?php

namespace App\Services\Pixel;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class IntimacaoPreviewService
{
    public function gerarPreview(string $pdfPath, string $token): ?string
    {
        $fullPath = Storage::disk('public')->path($pdfPath);

        if (! file_exists($fullPath)) {
            return null;
        }

        $jpegTmp = $this->pdfParaJpeg($fullPath);

        if (! $jpegTmp) {
            return null;
        }

        try {
            $manager = new ImageManager(new Driver());
            $img     = $manager->read($jpegTmp);

            $width  = $img->width();
            $height = $img->height();

            // Metade superior
            $img->crop($width, (int) ($height / 2), 0, 0);

            // Largura máxima 800px
            if ($img->width() > 800) {
                $img->scale(width: 800);
            }

            $destPath = "pixel-og/{$token}-intimacao-preview.jpg";

            Storage::disk('public')->put(
                $destPath,
                (string) $img->encode(new JpegEncoder(quality: 60, progressive: true, strip: true))
            );

            return $destPath;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($jpegTmp);
        }
    }

    private function pdfParaJpeg(string $fullPath): ?string
    {
        $pdftoppm = trim((string) shell_exec('which pdftoppm 2>/dev/null'));

        if (! $pdftoppm) {
            \Illuminate\Support\Facades\Log::warning('IntimacaoPreviewService: pdftoppm não encontrado');
            return null;
        }

        $tmp = sys_get_temp_dir() . '/intim-' . uniqid();
        $cmd = escapeshellarg($pdftoppm)
             . ' -jpeg -r 96 -f 1 -l 1 '
             . escapeshellarg($fullPath) . ' '
             . escapeshellarg($tmp)
             . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            \Illuminate\Support\Facades\Log::warning('IntimacaoPreviewService: pdftoppm falhou', [
                'exit_code' => $exitCode,
                'output'    => implode("\n", $output),
                'pdf'       => $fullPath,
            ]);
        }

        // pdftoppm gera sufixo -1.jpg, -000001.jpg ou -1.jpeg dependendo da versão
        foreach (['.jpg', '.jpeg'] as $ext) {
            $files = glob($tmp . '*' . $ext);
            if (is_array($files) && ! empty($files)) {
                return $files[0];
            }
        }

        \Illuminate\Support\Facades\Log::warning('IntimacaoPreviewService: nenhum arquivo gerado pelo pdftoppm', [
            'tmp_prefix' => $tmp,
            'pdf'        => $fullPath,
        ]);

        return null;
    }
}
