<?php

namespace App\Http\Controllers;

use App\Models\PixelTrack;
use App\Models\PixelTrackAccess;
use App\Services\Pixel\NewsPreviewMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $accessUuid = null;

        if ($pixel && $this->deveRegistrarCaptura($request)) {
            $accessUuid = $this->registrarAcesso($request, $pixel, 'pagina')?->uuid;
        }

        if ($pixel) {
            $this->preencherPreviewDaNoticiaSeNecessario($pixel);
        }

        $mensagem    = $pixel?->mensagem    ?? 'Este documento não está mais disponível.';
        $ogTitulo    = $pixel?->og_titulo   ?? $mensagem;
        $ogDescricao = $pixel?->og_descricao ?? '';

        $ogImagem = $this->resolverImagemOpenGraph($request, $pixel);
        $ogUrl = $this->urlAbsolutaDaRequisicao($request, $request->getPathInfo());
        $captureGps = (bool) $pixel?->capture_gps;
        $redirectUrl = $this->deveRedirecionarParaNoticia($request, $pixel)
            ? $pixel->noticia_url
            : null;

        if ($this->requisicaoDePreviewOuPrefetch($request)) {
            return view('pixel.preview', compact('ogTitulo', 'ogDescricao', 'ogImagem', 'ogUrl'));
        }

        return view('pixel.landing', compact('mensagem', 'token', 'accessUuid', 'captureGps', 'redirectUrl', 'ogTitulo', 'ogDescricao', 'ogImagem', 'ogUrl'));
    }

    /**
     * Endpoint do GIF — para uso como <img> em e-mails.
     */
    public function gif(Request $request, string $token): Response
    {
        $pixel = PixelTrack::where('token', $token)->first();

        if ($pixel && $this->deveRegistrarCaptura($request)) {
            $this->registrarAcesso($request, $pixel, 'gif');
        }

        return response(base64_decode(self::TRANSPARENT_GIF), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    public function ogImage(string $token): Response
    {
        $pixel = PixelTrack::where('token', $token)->firstOrFail();

        abort_unless($pixel->og_imagem_upload, 404);

        $path = ltrim($pixel->og_imagem_upload, '/');

        abort_unless(Storage::disk('public')->exists($path), 404);

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => $this->mimeTypePorExtensao($path) ?: Storage::disk('public')->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Recebe dados reais do dispositivo via JS e atualiza o registro.
     */
    public function atualizarDispositivo(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        if (! $this->deveRegistrarCaptura($request)) {
            return response()->json(['ok' => false]);
        }

        $pixel = PixelTrack::where('token', $token)->whereNotNull('clicked_at')->first();

        if (! $pixel) {
            return response()->json(['ok' => false]);
        }

        $dados = [];

        if ($gmt = $request->input('gmt')) {
            $dados['gmt'] = substr((string) $gmt, 0, 60);
        }

        if ($ipLocal = $request->input('ip_local')) {
            $ipLocal = substr((string) $ipLocal, 0, 45);

            if ($this->enderecoWebRtcLocalValido($ipLocal)) {
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

        $dadosGps = $pixel->capture_gps ? $this->dadosGpsValidados($request) : [];

        if (! empty($dadosGps)) {
            $dados = array_merge($dados, $dadosGps);
        }

        if (! empty($dados)) {
            $pixel->update($dados);

            if ($acesso = $this->resolverAcessoParaAtualizacao($request, $pixel)) {
                $acesso->update($dados);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function registrarAcesso(Request $request, PixelTrack $pixel, string $endpoint): ?PixelTrackAccess
    {
        try {
            $ip = $this->resolverIp($request);
            $porta = $this->resolverPortaOrigem($request);

            $geo = $this->consultarGeolocalizacao($ip);
            $gmt = $this->offsetParaGmt($geo['offset'] ?? null, $geo['timezone'] ?? null);

            $dados = [
                'ip'            => $ip,
                'porta'         => $porta,
                'gmt'           => $gmt,
                'cidade'        => $geo['city']       ?? null,
                'regiao'        => $geo['regionName'] ?? null,
                'pais'          => $geo['country']    ?? null,
                'latitude'      => isset($geo['lat']) ? (float) $geo['lat'] : null,
                'longitude'     => isset($geo['lon']) ? (float) $geo['lon'] : null,
                'isp'           => $geo['isp']        ?? null,
                'user_agent'    => $request->userAgent(),
            ];

            $acesso = $pixel->acessos()->create($dados + [
                'uuid' => (string) Str::uuid(),
                'endpoint' => $endpoint,
                'referer' => $request->headers->get('referer')
                    ? substr((string) $request->headers->get('referer'), 0, 255)
                    : null,
                'accessed_at' => now(),
            ]);

            $pixel->update($dados + [
                'total_acessos' => $pixel->total_acessos + 1,
                'clicked_at'    => $pixel->clicked_at ?? now(),
            ]);

            return $acesso;
        } catch (\Throwable $e) {
            Log::warning("PixelTrack: erro ao registrar acesso [{$pixel->token}]: " . $e->getMessage());

            return null;
        }
    }

    private function resolverAcessoParaAtualizacao(Request $request, PixelTrack $pixel): ?PixelTrackAccess
    {
        $uuid = $request->input('access_id');

        if (is_string($uuid) && Str::isUuid($uuid)) {
            return $pixel->acessos()
                ->where('uuid', $uuid)
                ->latest('accessed_at')
                ->first();
        }

        return $pixel->acessos()
            ->where('endpoint', 'pagina')
            ->latest('accessed_at')
            ->first();
    }

    private function preencherPreviewDaNoticiaSeNecessario(PixelTrack $pixel): void
    {
        if ($pixel->preview_tipo !== 'noticia' || ! $pixel->noticia_url) {
            return;
        }

        if ($pixel->og_titulo && $pixel->og_descricao && $pixel->og_imagem_upload) {
            return;
        }

        $metadata = app(NewsPreviewMetadataService::class)->fetch($pixel->noticia_url);

        $dados = [];

        if (! $pixel->og_titulo && filled($metadata['og_titulo'] ?? null)) {
            $dados['og_titulo'] = $metadata['og_titulo'];
        }

        if (! $pixel->og_descricao && filled($metadata['og_descricao'] ?? null)) {
            $dados['og_descricao'] = $metadata['og_descricao'];
        }

        if (! $pixel->og_imagem && ! $pixel->og_imagem_upload && filled($metadata['og_imagem'] ?? null)) {
            $dados['og_imagem'] = $metadata['og_imagem'];
        }

        $imagemParaCache = $metadata['og_imagem'] ?? $pixel->og_imagem;

        if (! $pixel->og_imagem_upload && filled($imagemParaCache)) {
            $path = app(NewsPreviewMetadataService::class)
                ->storeImage((string) $imagemParaCache, $pixel->token);

            if ($path) {
                $dados['og_imagem_upload'] = $path;
            }
        }

        if (! empty($dados)) {
            $pixel->forceFill($dados)->save();
            $pixel->refresh();
        }
    }

    private function dadosGpsValidados(Request $request): array
    {
        if (! $request->filled('gps_latitude') || ! $request->filled('gps_longitude')) {
            return [];
        }

        $latitude = filter_var($request->input('gps_latitude'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($request->input('gps_longitude'), FILTER_VALIDATE_FLOAT);

        if ($latitude === false || $longitude === false) {
            return [];
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return [];
        }

        $dados = [
            'gps_latitude' => round((float) $latitude, 7),
            'gps_longitude' => round((float) $longitude, 7),
        ];

        if ($request->filled('gps_accuracy')) {
            $accuracy = filter_var($request->input('gps_accuracy'), FILTER_VALIDATE_FLOAT);

            if ($accuracy !== false && $accuracy >= 0) {
                $dados['gps_accuracy'] = round((float) $accuracy, 2);
            }
        }

        return $dados;
    }

    private function deveRegistrarCaptura(Request $request): bool
    {
        if ($request->user()) {
            return false;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('POST')) {
            return false;
        }

        return ! $this->requisicaoDePreviewOuPrefetch($request);
    }

    private function deveRedirecionarParaNoticia(Request $request, ?PixelTrack $pixel): bool
    {
        if (! $pixel || $pixel->preview_tipo !== 'noticia' || ! $pixel->noticia_url) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        return ! $this->requisicaoDePreviewOuPrefetch($request);
    }

    private function requisicaoDePreviewOuPrefetch(Request $request): bool
    {
        foreach (['Purpose', 'Sec-Purpose', 'X-Purpose'] as $header) {
            if (str_contains(strtolower((string) $request->headers->get($header)), 'prefetch')) {
                return true;
            }
        }

        $userAgent = strtolower((string) $request->userAgent());

        foreach ([
            'facebookexternalhit',
            'facebot',
            'whatsapp',
            'telegrambot',
            'twitterbot',
            'linkedinbot',
            'slackbot',
            'discordbot',
        ] as $crawler) {
            if (str_contains($userAgent, $crawler)) {
                return true;
            }
        }

        return false;
    }

    private function resolverImagemOpenGraph(Request $request, ?PixelTrack $pixel): array
    {
        if (! $pixel) {
            return ['url' => null, 'type' => null];
        }

        if ($pixel->og_imagem_upload) {
            $path = ltrim($pixel->og_imagem_upload, '/');

            return [
                'url' => $this->urlAbsolutaDaRequisicao($request, route('pixel.og-image', $pixel->token, false)),
                'type' => $this->mimeTypePorExtensao($path) ?: Storage::disk('public')->mimeType($path),
            ];
        }

        if ($pixel->og_imagem) {
            return [
                'url' => $pixel->og_imagem,
                'type' => $this->mimeTypePorExtensao($pixel->og_imagem),
            ];
        }

        return ['url' => null, 'type' => null];
    }

    private function urlAbsolutaDaRequisicao(Request $request, string $path): string
    {
        $scheme = $request->headers->get('X-Forwarded-Proto')
            ? explode(',', $request->headers->get('X-Forwarded-Proto'))[0]
            : $request->getScheme();

        $host = $request->headers->get('X-Forwarded-Host')
            ? explode(',', $request->headers->get('X-Forwarded-Host'))[0]
            : $request->getHttpHost();

        return trim($scheme).'://'.trim($host).'/'.ltrim($path, '/');
    }

    private function mimeTypePorExtensao(string $path): ?string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
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

    private function resolverPortaOrigem(Request $request): ?string
    {
        foreach ([
            'X-Forwarded-Client-Port',
            'X-Client-Port',
            'X-Real-Port',
            'CF-Connecting-Port',
            'True-Client-Port',
        ] as $header) {
            if ($porta = $this->normalizarPorta($request->headers->get($header))) {
                return $porta;
            }
        }

        if ($porta = $this->portaNoXForwardedFor($request->headers->get('X-Forwarded-For'))) {
            return $porta;
        }

        return $this->normalizarPorta($request->server('REMOTE_PORT'));
    }

    private function portaNoXForwardedFor(?string $valor): ?string
    {
        if (! $valor) {
            return null;
        }

        $primeiro = trim(explode(',', $valor)[0]);

        if (preg_match('/^\[[^\]]+\]:(\d{1,5})$/', $primeiro, $matches)) {
            return $this->normalizarPorta($matches[1]);
        }

        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:(\d{1,5})$/', $primeiro, $matches)) {
            return $this->normalizarPorta($matches[1]);
        }

        return null;
    }

    private function normalizarPorta(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        if (! preg_match('/^\d{1,5}$/', $valor)) {
            return null;
        }

        $porta = (int) $valor;

        if ($porta < 1 || $porta > 65535) {
            return null;
        }

        return (string) $porta;
    }

    private function enderecoWebRtcLocalValido(string $endereco): bool
    {
        if (preg_match('/^[a-z0-9-]{1,63}(\.[a-z0-9-]{1,63})*\.local$/i', $endereco)) {
            return true;
        }

        if (! filter_var($endereco, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($endereco, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return filter_var($endereco, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
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
