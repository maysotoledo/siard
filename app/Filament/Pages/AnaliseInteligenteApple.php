<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesPlatformLogAnalysis;
use App\Services\AnaliseInteligente\Apple\AppleLogParser;
use App\Services\AnaliseInteligente\Apple\AppleReportAggregator;
use App\Services\AnaliseInteligente\Platform\PlatformLogParser;
use App\Services\AnaliseInteligente\Platform\PlatformReportAggregator;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class AnaliseInteligenteApple extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use HandlesPlatformLogAnalysis;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Análise log APPLE';
    protected static ?string $title = 'Análise de log da APPLE';
    protected static ?string $slug = 'analise-inteligente-apple';

    protected string $view = 'filament.pages.analise-inteligente-platform';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    protected function platformSource(): string
    {
        return 'apple';
    }

    protected function platformLabel(): string
    {
        return 'Apple';
    }

    protected function makeLogParser(): PlatformLogParser
    {
        return new AppleLogParser();
    }

    protected function makeReportAggregator(): PlatformReportAggregator
    {
        return new AppleReportAggregator();
    }
}
