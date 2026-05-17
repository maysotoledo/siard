<?php

namespace App\Services\Investigation;

use App\Models\InvestigationContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extrai texto de um Boletim de Ocorrência (PDF ou imagem).
 *
 * Para PDF com texto seleccionável usa smalot/pdfparser (já instalado).
 * Para PDF escaneado ou imagem, deixa texto_extraido em branco para
 * preenchimento manual pelo usuário.
 *
 * OCR não é obrigatório neste momento; o campo pode ser preenchido manualmente.
 */
class BoContextExtractorService
{
    public function extract(InvestigationContext $context): string
    {
        $path = $context->arquivo_path;
        $mime = $context->arquivo_mime ?? '';

        if (! $path) {
            return '';
        }

        $fullPath = Storage::disk('local')->path($path);

        if (! file_exists($fullPath)) {
            Log::warning('BoContextExtractorService: arquivo não encontrado', ['path' => $path]);
            return '';
        }

        if (str_contains($mime, 'pdf') || str_ends_with(strtolower($path), '.pdf')) {
            return $this->extractFromPdf($fullPath);
        }

        // Para imagens (jpg, png, webp) não há OCR configurado —
        // o usuário deve preencher o texto manualmente.
        Log::info('BoContextExtractorService: arquivo é imagem, extração manual necessária.', ['path' => $path]);
        return '';
    }

    private function extractFromPdf(string $fullPath): string
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            // Dependência: smalot/pdfparser (já incluída no composer.json)
            Log::warning('BoContextExtractorService: smalot/pdfparser não encontrado.');
            return '';
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($fullPath);
            $text   = $pdf->getText();

            $text = trim((string) $text);

            if ($text === '') {
                Log::info('BoContextExtractorService: PDF sem texto seleccionável (provavelmente escaneado). Preencha manualmente.');
            }

            return $text;
        } catch (Throwable $e) {
            Log::error('BoContextExtractorService: erro ao parsear PDF — ' . $e->getMessage());
            return '';
        }
    }
}
