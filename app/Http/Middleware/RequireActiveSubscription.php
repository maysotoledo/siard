<?php

namespace App\Http\Middleware;

use App\Models\PixelModuleSetting;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Sem usuário autenticado — deixa o middleware de auth tratar
        if (! $user) {
            return $next($request);
        }

        // super_admin sempre tem acesso irrestrito
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Cobrança desabilitada no painel — libera todos
        if (! PixelModuleSetting::isPaymentEnabled()) {
            return $next($request);
        }

        // Assinatura ativa — libera acesso normal
        if ($user->hasActivePixelSubscription()) {
            return $next($request);
        }

        // Determina a URL base do painel (ex.: /admin)
        $panel       = Filament::getCurrentPanel();
        $panelPath   = $panel?->getPath() ?? 'admin';
        $requestPath = trim($request->path(), '/');

        // O dashboard/paywall precisa abrir, mas isso não pode liberar todo /admin/*.
        if ($requestPath === $panelPath) {
            return $next($request);
        }

        // Requisições Livewire: só libera se TODOS os componentes da requisição
        // pertencerem à lista de componentes permitidos sem assinatura (widgets do dashboard/paywall).
        // Não usamos Referer (forjável); validamos o nome do componente no body JSON,
        // que o servidor precisa resolver — portanto não pode ser abusado para acessar outras rotas.
        if ($request->hasHeader('X-Livewire')) {
            $allowedComponents = [
                'subscription-status-widget',         // widget de pagamento/paywall
                'dashboard-account-widget',           // widget de conta no dashboard
                'filament.widgets.account-widget',    // fallback nome Filament
            ];

            try {
                $components = $request->input('components', []);

                if (is_array($components) && count($components) > 0) {
                    $allAllowed = true;

                    foreach ($components as $component) {
                        $snapshot  = json_decode($component['snapshot'] ?? '{}', true);
                        $name      = $snapshot['memo']['name'] ?? ($snapshot['name'] ?? '');

                        if (! in_array($name, $allowedComponents, true)) {
                            $allAllowed = false;
                            break;
                        }
                    }

                    if ($allAllowed) {
                        return $next($request);
                    }
                }
            } catch (\Throwable) {
                // Qualquer erro no parsing → nega o bypass
            }
        }

        // Rotas sempre liberadas.
        $allowedPrefixes = [
            $panelPath . '/logout',
            $panelPath . '/email-verification',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if ($requestPath === $prefix || str_starts_with($requestPath, $prefix . '/')) {
                return $next($request);
            }
        }

        // Qualquer outra página do painel → redireciona ao dashboard com paywall
        return redirect()->to(url('/' . $panelPath));
    }
}
