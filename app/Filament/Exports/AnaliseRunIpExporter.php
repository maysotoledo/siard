<?php

namespace App\Filament\Exports;

use App\Models\AnaliseRunIp;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AnaliseRunIpExporter extends Exporter
{
    protected static ?string $model = AnaliseRunIp::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('ip')->label('IP'),
            ExportColumn::make('occurrences')->label('Ocorrencias'),
            ExportColumn::make('last_seen_at')->label('Ultimo acesso'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Exportação de IPs concluída: ' . number_format($export->successful_rows) . ' registros.';
    }
}
