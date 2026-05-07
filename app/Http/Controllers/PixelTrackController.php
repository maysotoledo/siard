<?php

namespace App\Http\Controllers;

use App\Models\PixelTrack;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PixelTrackController extends Controller
{
    private const TRANSPARENT_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Endpoint do link — registra o acesso e exibe a página de mensagem.
     */
    public function pagina(Request $request, string $token): View
    {
        $pixel = PixelTrack::where('token', $token)->first();

        if ($pixel) {
            $this->registrarAcesso($request, $pixel);
        }

        $mensagem    = $pixel?->mensagem    ?? 'Este documento não está mais disponível.';
        $ogTitulo    = $pixel?->og_titulo   ?? $mensagem;
        $ogDescricao = $pixel?->og_descricao ?? '';

        // Upload tem prioridade sobre URL externa
        $ogImagem = null;
        if ($pixel?->og_imagem_upload) {
            $ogImagem = Storage::disk('public')->url($pixel->og_imagem_upload);
        } elseif ($pixel?->og_imagem) {
            $ogImagem = $pixel->og_imagem;
        }

        return view('pixel.landing', compact('mensagem', 'token', 'ogTitulo', 'ogDescricao', 'ogImagem'));
    }

    /**
     * Endpoint do GIF — para uso como <img> em e-mails.
     */
    public function gif(Request $request, string $token): Response
    {
        $pixel = PixelTrack::where('token', $token)->first();

        if ($pixel) {
            $this->registrarAcesso($request, $pixel);
        }

        return response(base64_decode(self::TRANSPARENT_GIF), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * Recebe dados reais do dispositivo via JS e atualiza o registro.
     */
    public function atualizarDispositivo(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        $pixel = PixelTrack::where('token', $token)->whereNotNull('clicked_at')->first();

        if (! $pixel) {
            return response()->json(['ok' => false]);
        }

        $dados = [];

        if ($gmt = $request->input('gmt')) {
            $dados['gmt'] = substr((string) $gmt, 0, 60);
        }

        if ($ipLocal = $request->input('ip_local')) {
            // Aceitar apenas IPs privados válidos (RFC 1918 / link-local)
            if (filter_var($ipLocal, FILTER_VALIDATE_IP)) {
                $dados['ip_local'] = $ipLocal;
            }
        }

        if ($idioma = $request->input('idioma')) {
            $dados['idioma'] = substr((string) $idioma, 0, 20);
        }

        if ($plataforma = $request->input('plataforma')) {
            $dados['plataforma'] = substr((string) $plataforma, 0, 100);
        }

        if ($resolucao = $request->input('resolucao')) {
            $dados['resolucao'] = substr((string) $resolucao, 0, 20);
        }

        if (! empty($dados)) {
            $pixel->update($dados);
        }

        return response()->json(['ok' => true]);
    }

    private function registrarAcesso(Request $request, PixelTrack $pixel): void
    {
        try {
            $ip    = $this->resolverIp($request);
            $porta = $request->headers->get('X-Forwarded-Port')
                ?? $request->server('SERVER_PORT')
                ?? '80';

            $geo = $this->consultarGeolocalizacao($ip);
            $gmt = $this->offsetParaGmt($geo['offset'] ?? null, $geo['timezone'] ?? null);

            $pixel->update([
                'ip'            => $ip,
                'porta'         => (string) $porta,
                'gmt'           => $gmt,
                'cidade'        => $geo['city']       ?? null,
                'regiao'        => $geo['regionName'] ?? null,
                'pais'          => $geo['country']    ?? null,
                'latitude'      => isset($geo['lat']) ? (float) $geo['lat'] : null,
                'longitude'     => isset($geo['lon']) ? (float) $geo['lon'] : null,
                'isp'           => $geo['isp']        ?? null,
                'user_agent'    => $request->userAgent(),
                'total_acessos' => $pixel->total_acessos + 1,
                'clicked_at'    => $pixel->clicked_at ?? now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("PixelTrack: erro ao registrar acesso [{$pixel->token}]: " . $e->getMessage());
        }
    }

    private function resolverIp(Request $request): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            $valor = $request->server($header);
            if ($valor) {
                return trim(explode(',', $valor)[0]);
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    private function consultarGeolocalizacao(string $ip): array
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['city' => 'Local/Privado', 'timezone' => 'America/Sao_Paulo', 'offset' => -10800];
        }

        try {
            $resposta = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,country,regionName,city,lat,lon,timezone,isp,offset',
            ]);

            if ($resposta->successful() && $resposta->json('status') === 'success') {
                return $resposta->json();
            }
        } catch (\Throwable) {
            // geolocalização é melhor esforço
        }

        return [];
    }

    private function offsetParaGmt(?int $offsetSegundos, ?string $timezone): string
    {
        if ($offsetSegundos === null) {
            return $timezone ?? 'Desconhecido';
        }

        $horas   = abs((int) floor($offsetSegundos / 3600));
        $minutos = abs((int) (($offsetSegundos % 3600) / 60));
        $sinal   = $offsetSegundos >= 0 ? '+' : '-';
        $gmt     = sprintf('GMT%s%02d:%02d', $sinal, $horas, $minutos);

        if ($timezone) {
            $gmt .= " ({$timezone})";
        }

        return $gmt;
    }
}
