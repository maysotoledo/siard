<?php

use App\Http\Controllers\AnaliseInvestigationPdfController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Billing\MercadoPagoPixelWebhookController;
use App\Http\Middleware\RequireActiveSubscription;
use App\Http\Controllers\IpGrabberController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;

Route::get('/', function () {
   // return view('welcome');
    return redirect()->to(Filament::getUrl());
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

Route::post('/billing/pixel/mercado-pago/webhook', MercadoPagoPixelWebhookController::class)
    ->name('billing.pixel.mercado-pago.webhook')
    ->middleware('throttle:120,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

Route::middleware(['auth', RequireActiveSubscription::class])->group(function (): void {
    Route::get('/analises/investigacoes/{investigation}/pdf', AnaliseInvestigationPdfController::class)
        ->name('analises.investigacoes.pdf');
});
