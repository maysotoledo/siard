<?php

namespace App\Filament\Exports;

use App\Models\AnaliseRunEvent;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AnaliseRunEventExporter extends Exporter
{
    protected static ?string $model = AnaliseRunEvent::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('occurred_at')
                ->label('Data/Hora (UTC)')
                ->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s')),

            ExportColumn::make('datetime_local')->label('Data/Hora (GMT-3)'),
            ExportColumn::make('ip')->label('IP'),
            ExportColumn::make('logical_port')->label('Porta'),
            ExportColumn::make('provider_label')->label('Operadora/ISP'),
            ExportColumn::make('city_label')->label('Cidade'),
            ExportColumn::make('connection_type')->label('Tipo'),
            ExportColumn::make('period_flags')->label('Periodo'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Exportação de eventos concluída: ' . number_format($export->successful_rows) . ' registros.';
    }
}
