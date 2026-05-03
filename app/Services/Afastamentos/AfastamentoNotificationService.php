<?php

namespace App\Services\Afastamentos;

use App\Models\AfastamentoSolicitacao;
use Filament\Notifications\Notification;

class AfastamentoNotificationService
{
    public function notificarDecisao(AfastamentoSolicitacao $solicitacao, string $titulo): void
    {
        if (! $solicitacao->user) {
            return;
        }

        Notification::make()
            ->title($titulo)
            ->body($solicitacao->tipo_afastamento->label() . ' de ' . $solicitacao->data_inicio?->format('d/m/Y') . ' a ' . $solicitacao->data_fim?->format('d/m/Y'))
            ->sendToDatabase($solicitacao->user);
    }
}
