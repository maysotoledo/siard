<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DefinirHorarioAtendimento;
use Filament\Widgets\Widget;

class DefinirHorarioAtendimentoWidget extends Widget
{
    protected string $view = 'filament.widgets.definir-horario-atendimento-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['epc', 'cartorio_central']);
    }

    public function getUrl(): string
    {
        return DefinirHorarioAtendimento::getUrl();
    }
}
