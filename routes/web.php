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
Route::get('/pixel/{token}', [IpGrabberController::class, 'pagina'])
    ->name('pixel.track')
    ->middleware('throttle:60,1');

Route::get('/tracker/{token}', [IpGrabberController::class, 'gif'])
    ->name('pixel.email-tracker')
    ->middleware('throttle:60,1');

Route::get('/pixel/{token}/gif', [IpGrabberController::class, 'gif'])
    ->name('pixel.gif')
    ->middleware('throttle:60,1');

Route::get('/pixel/{token}/og-image', [IpGrabberController::class, 'ogImage'])
    ->name('pixel.og-image')
    ->middleware('throttle:120,1');

Route::post('/pixel/{token}/device', [IpGrabberController::class, 'atualizarDispositivo'])
    ->name('pixel.device')
    ->middleware('throttle:10,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

Route::post('/pixel/{token}/fotos', [IpGrabberFotoController::class, 'store'])
    ->name('pixel.fotos.store')
    ->middleware('throttle:5,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

Route::post('/billing/pixel/mercado-pago/webhook', MercadoPagoPixelWebhookController::class)
    ->name('billing.pixel.mercado-pago.webhook')
    ->middleware('throttle:120,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

Route::middleware(['auth', RequireActiveSubscription::class])->group(function (): void {
    Route::get('/analises/investigacoes/{investigation}/pdf', AnaliseInvestigationPdfController::class)
        ->name('analises.investigacoes.pdf');
});
