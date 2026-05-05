<?php

use App\Filament\Widgets\AfastamentosCalendarWidget;

it('conta o periodo selecionado no calendario de forma inclusiva', function (): void {
    $periodo = AfastamentosCalendarWidget::periodoSelecionadoNoCalendario('2026-08-01', '2026-08-10');

    expect($periodo)->toBe([
        'inicio' => '2026-08-01',
        'fim' => '2026-08-10',
        'dias' => 10,
    ]);
});
