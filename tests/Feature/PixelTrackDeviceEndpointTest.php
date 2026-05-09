<?php

use App\Models\PixelTrack;
use App\Models\User;
use App\Notifications\IpGrabberAccessCapturedNotification;
use App\Services\Pixel\NewsPreviewMetadataService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function criarPixelCapturadoParaTeste(): PixelTrack
{
    $user = User::factory()->create();

    return PixelTrack::create([
        'token' => 'pixel-teste',
        'label' => 'Pixel teste',
        'created_by' => $user->id,
        'clicked_at' => now(),
    ]);
}

test('endpoint salva ip local privado capturado via webrtc', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $pixel = criarPixelCapturadoParaTeste();

    $this->postJson(route('pixel.device', $pixel->token), [
        'ip_local' => '192.168.1.25',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($pixel->fresh()->ip_local)->toBe('192.168.1.25');
});

test('endpoint salva hostname mdns quando navegador mascara o ip local', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $pixel = criarPixelCapturadoParaTeste();
    $hostname = '3f4d6b8c-5a7e-4f9a-b1c2-123456789abc.local';

    $this->postJson(route('pixel.device', $pixel->token), [
        'ip_local' => $hostname,
    ])->assertOk()->assertJson(['ok' => true]);

    expect($pixel->fresh()->ip_local)->toBe($hostname);
});

test('endpoint ignora ip publico recebido no campo local', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $pixel = criarPixelCapturadoParaTeste();

    $this->postJson(route('pixel.device', $pixel->token), [
        'ip_local' => '8.8.8.8',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($pixel->fresh()->ip_local)->toBeNull();
});

test('endpoint ignora ip local de loopback', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $pixel = criarPixelCapturadoParaTeste();

    $this->postJson(route('pixel.device', $pixel->token), [
        'ip_local' => '127.0.0.1',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($pixel->fresh()->ip_local)->toBeNull();
});

test('pagina nao registra captura de usuario autenticado', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-auth',
        'label' => 'Pixel autenticado',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/pixel/'.$pixel->token)
        ->assertOk();

    $pixel->refresh();

    expect($pixel->clicked_at)->toBeNull()
        ->and($pixel->total_acessos)->toBe(0)
        ->and($pixel->ip)->toBeNull();
});

test('pagina nao registra captura de crawler de preview', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-crawler',
        'label' => 'Pixel crawler',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'User-Agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    ])->assertOk();

    $pixel->refresh();

    expect($pixel->clicked_at)->toBeNull()
        ->and($pixel->total_acessos)->toBe(0)
        ->and($pixel->ip)->toBeNull();
});

test('pagina salva porta de origem do cliente quando proxy informa', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-porta-origem',
        'label' => 'Pixel porta origem',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'X-Real-Port' => '51234',
    ])->assertOk();

    expect($pixel->fresh()->porta)->toBe('51234');
});

test('pagina nao usa porta do servidor como porta de origem', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-porta-servidor',
        'label' => 'Pixel porta servidor',
        'created_by' => $user->id,
    ]);

    $this->withServerVariables([
        'SERVER_PORT' => '443',
        'REMOTE_PORT' => null,
    ])
        ->get('/pixel/'.$pixel->token)
        ->assertOk();

    expect($pixel->fresh()->porta)->toBeNull();
});

test('pagina extrai porta de origem quando x forwarded for inclui ip e porta', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-porta-xff',
        'label' => 'Pixel porta xff',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'X-Forwarded-For' => '203.0.113.10:49876',
    ])->assertOk();

    expect($pixel->fresh()->porta)->toBe('49876');
});

test('pagina cria historico para cada acesso valido ao mesmo pixel', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-historico',
        'label' => 'Pixel historico',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'X-Real-Port' => '50001',
        'User-Agent' => 'Mozilla/5.0 primeiro acesso',
    ])->assertOk();

    $this->get('/pixel/'.$pixel->token, [
        'X-Real-Port' => '50002',
        'User-Agent' => 'Mozilla/5.0 segundo acesso',
    ])->assertOk();

    $pixel->refresh();

    expect($pixel->total_acessos)->toBe(2)
        ->and($pixel->acessos()->count())->toBe(2)
        ->and($pixel->acessos()->orderBy('id')->pluck('porta')->all())->toBe(['50001', '50002'])
        ->and($pixel->acessos()->orderBy('id')->pluck('user_agent')->all())->toBe([
            'Mozilla/5.0 primeiro acesso',
            'Mozilla/5.0 segundo acesso',
        ]);
});

test('pagina envia email ao criador quando ip grabber recebe acesso valido', function () {
    Notification::fake();

    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-email-alerta',
        'label' => 'Pixel email alerta',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'X-Real-Port' => '50123',
        'User-Agent' => 'Mozilla/5.0 alerta email',
    ])->assertOk();

    Notification::assertSentTo($user, IpGrabberAccessCapturedNotification::class);
});

test('crawler de preview nao envia email ao criador', function () {
    Notification::fake();

    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-email-crawler',
        'label' => 'Pixel email crawler',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'User-Agent' => 'WhatsApp/2.24',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('endpoint de dispositivo atualiza a linha correta do historico', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-device-historico',
        'label' => 'Pixel device historico',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token)->assertOk();

    $acesso = $pixel->acessos()->firstOrFail();

    $this->postJson(route('pixel.device', $pixel->token), [
        'access_id' => $acesso->uuid,
        'ip_local' => '192.168.1.45',
        'idioma' => 'pt-BR',
        'plataforma' => 'macOS',
        'resolucao' => '1440x900',
    ])->assertOk()->assertJson(['ok' => true]);

    $acesso->refresh();

    expect($acesso->ip_local)->toBe('192.168.1.45')
        ->and($acesso->idioma)->toBe('pt-BR')
        ->and($acesso->plataforma)->toBe('macOS')
        ->and($acesso->resolucao)->toBe('1440x900');
});

test('endpoint de dispositivo salva gps autorizado no resumo e no historico', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-gps',
        'label' => 'Pixel GPS',
        'capture_gps' => true,
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token)->assertOk();

    $acesso = $pixel->acessos()->firstOrFail();

    $this->postJson(route('pixel.device', $pixel->token), [
        'access_id' => $acesso->uuid,
        'gps_latitude' => '-10.64212345',
        'gps_longitude' => '-51.56998765',
        'gps_accuracy' => '14.345',
    ])->assertOk()->assertJson(['ok' => true]);

    $pixel->refresh();
    $acesso->refresh();

    expect($pixel->gps_latitude)->toBe(-10.6421235)
        ->and($pixel->gps_longitude)->toBe(-51.5699877)
        ->and($pixel->gps_accuracy)->toBe(14.35)
        ->and($acesso->gps_latitude)->toBe(-10.6421235)
        ->and($acesso->gps_longitude)->toBe(-51.5699877)
        ->and($acesso->gps_accuracy)->toBe(14.35);
});

test('endpoint de dispositivo ignora gps fora da faixa valida', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $pixel = criarPixelCapturadoParaTeste();
    $pixel->update(['capture_gps' => true]);

    $this->postJson(route('pixel.device', $pixel->token), [
        'gps_latitude' => '-100',
        'gps_longitude' => '-200',
    ])->assertOk()->assertJson(['ok' => true]);

    $pixel->refresh();

    expect($pixel->gps_latitude)->toBeNull()
        ->and($pixel->gps_longitude)->toBeNull();
});

test('endpoint de dispositivo ignora gps quando pixel nao habilitou captura gps', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $pixel = criarPixelCapturadoParaTeste();

    $this->postJson(route('pixel.device', $pixel->token), [
        'gps_latitude' => '-10.64212345',
        'gps_longitude' => '-51.56998765',
        'gps_accuracy' => '14.345',
    ])->assertOk()->assertJson(['ok' => true]);

    $pixel->refresh();

    expect($pixel->gps_latitude)->toBeNull()
        ->and($pixel->gps_longitude)->toBeNull()
        ->and($pixel->gps_accuracy)->toBeNull();
});

test('preview usa url publica absoluta para imagem enviada por upload', function () {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');

    $user = User::factory()->create();
    $path = UploadedFile::fake()
        ->image('preview.jpg', 1200, 630)
        ->storeAs('pixel-og', 'preview.jpg', 'public');

    $pixel = PixelTrack::create([
        'token' => 'pixel-preview-upload',
        'label' => 'Pixel preview upload',
        'created_by' => $user->id,
        'og_titulo' => 'Documento Policial',
        'og_imagem_upload' => $path,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'Host' => 'rastreador.example',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'rastreador.example',
    ])
        ->assertOk()
        ->assertSee('property="og:image"', false)
        ->assertSee('content="https://rastreador.example/pixel/pixel-preview-upload/og-image"', false)
        ->assertSee('property="og:image:secure_url"', false)
        ->assertSee('property="og:image:type"  content="image/jpeg"', false)
        ->assertSee('property="og:url"         content="https://rastreador.example/pixel/pixel-preview-upload"', false);
});

test('servico extrai metadados open graph de noticia', function () {
    Http::fake([
        'noticias.example/*' => Http::response(<<<'HTML'
            <html>
                <head>
                    <meta property="og:title" content="Título da notícia">
                    <meta property="og:description" content="Descrição da notícia">
                    <meta property="og:image" content="/img/noticia.jpg">
                </head>
            </html>
        HTML, 200),
    ]);

    $metadata = app(NewsPreviewMetadataService::class)->fetch('https://noticias.example/materia/123');

    expect($metadata)->toMatchArray([
        'og_titulo' => 'Título da notícia',
        'og_descricao' => 'Descrição da notícia',
        'og_imagem' => 'https://noticias.example/img/noticia.jpg',
    ]);
});

test('pixel de noticia mostra preview da noticia e prepara redirecionamento para clique humano', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-noticia',
        'label' => 'Pixel noticia',
        'preview_tipo' => 'noticia',
        'noticia_url' => 'https://noticias.example/materia/123',
        'og_titulo' => 'Título da notícia',
        'og_descricao' => 'Descrição da notícia',
        'og_imagem' => 'https://noticias.example/img/noticia.jpg',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token)
        ->assertOk()
        ->assertSee('property="og:title"', false)
        ->assertSee('content="Título da notícia"', false)
        ->assertSee('property="og:description"', false)
        ->assertSee('content="Descrição da notícia"', false)
        ->assertSee('property="og:image"       content="https://noticias.example/img/noticia.jpg"', false)
        ->assertSee('var redirectUrl = "https:\/\/noticias.example\/materia\/123";', false);

    expect($pixel->fresh()->total_acessos)->toBe(1);
});

test('crawler ve preview da noticia sem registrar acesso nem redirecionar', function () {
    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-noticia-crawler',
        'label' => 'Pixel noticia crawler',
        'preview_tipo' => 'noticia',
        'noticia_url' => 'https://noticias.example/materia/123',
        'og_titulo' => 'Título da notícia',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'User-Agent' => 'WhatsApp/2.24',
    ])
        ->assertOk()
        ->assertSee('property="og:title"', false)
        ->assertSee('content="Título da notícia"', false)
        ->assertDontSee('redirectUrl', false);

    expect($pixel->fresh()->total_acessos)->toBe(0);
});

test('pagina do pixel busca metadados da noticia se eles ainda estiverem vazios', function () {
    Storage::fake('public');

    Http::fake([
        'noticias.example/*' => Http::response(<<<'HTML'
            <html>
                <head>
                    <meta property="og:title" content="Título carregado tarde">
                    <meta property="og:description" content="Descrição carregada tarde">
                    <meta property="og:image" content="https://cdn.example/noticia.jpg">
                </head>
            </html>
        HTML, 200),
        'cdn.example/*' => Http::response('fake image bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $user = User::factory()->create();

    $pixel = PixelTrack::create([
        'token' => 'pixel-noticia-late',
        'label' => 'Pixel noticia late',
        'preview_tipo' => 'noticia',
        'noticia_url' => 'https://noticias.example/materia/late',
        'created_by' => $user->id,
    ]);

    $this->get('/pixel/'.$pixel->token, [
        'User-Agent' => 'WhatsApp/2.24',
    ])
        ->assertOk()
        ->assertSee('property="og:title" content="Título carregado tarde"', false)
        ->assertSee('property="og:description" content="Descrição carregada tarde"', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('/pixel/pixel-noticia-late/og-image', false);

    $pixel->refresh();

    expect($pixel->og_titulo)->toBe('Título carregado tarde')
        ->and($pixel->og_descricao)->toBe('Descrição carregada tarde')
        ->and($pixel->og_imagem)->toBe('https://cdn.example/noticia.jpg')
        ->and($pixel->og_imagem_upload)->toBe('pixel-og/noticias/pixel-noticia-late.jpg')
        ->and($pixel->total_acessos)->toBe(0);

    Storage::disk('public')->assertExists('pixel-og/noticias/pixel-noticia-late.jpg');
});

test('endpoint publico serve imagem og enviada por upload', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    Storage::disk('public')->put('pixel-og/preview-endpoint.jpg', 'fake image bytes');

    $pixel = PixelTrack::create([
        'token' => 'pixel-og-image-endpoint',
        'label' => 'Pixel og image endpoint',
        'created_by' => $user->id,
        'og_imagem_upload' => 'pixel-og/preview-endpoint.jpg',
    ]);

    $this->get(route('pixel.og-image', $pixel->token))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('excluir pixel remove imagem de preview enviada por upload', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $path = UploadedFile::fake()
        ->image('preview-delete.jpg', 1200, 630)
        ->storeAs('pixel-og', 'preview-delete.jpg', 'public');

    $pixel = PixelTrack::create([
        'token' => 'pixel-delete-upload',
        'label' => 'Pixel delete upload',
        'created_by' => $user->id,
        'og_imagem_upload' => $path,
    ]);

    Storage::disk('public')->assertExists($path);

    $pixel->delete();

    Storage::disk('public')->assertMissing($path);
});
